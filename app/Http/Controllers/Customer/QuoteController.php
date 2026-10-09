<?php

namespace App\Http\Controllers\Customer;

use App\Exceptions\QuoteException;
use App\Http\Controllers\Controller;
use App\Models\BusinessProfile;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\QuoteRequest;
use App\Models\User;
use App\Rules\MiraTin;
use App\Services\DeliveryService;
use App\Services\GstService;
use App\Services\NotificationService;
use App\Services\QuoteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Bulk quotes, the customer's side: ask a shop for a price (from the product page, or the shop page
 * for any of its products), follow the request under My account → Quotes, accept the quote into the
 * cart or decline it, and write in the request's messages. A customer only ever sees their own.
 */
class QuoteController extends Controller
{
    public function __construct(protected QuoteService $quotes) {}

    public function index(Request $request)
    {
        $this->quotes->expireDue();

        $requests = QuoteRequest::where('customer_id', $request->user()->id)
            ->with(['seller', 'product.mainImage'])
            ->latest('id')
            ->paginate(15);

        return view('quotes.index', ['requests' => $requests]);
    }

    /**
     * The request form for one product (?product=id), or, from a shop page (?shop=id), the shop's
     * products to choose from first.
     */
    public function create(Request $request)
    {
        $user = $request->user();

        if (! $request->filled('product') && $request->filled('shop')) {
            $shop = User::find($request->integer('shop'));
            abort_unless($shop && $shop->isSeller(), 404);
            $products = $shop->products()->where('is_active', true)->orderBy('id')->get()
                ->sortBy(fn (Product $product) => mb_strtolower((string) $product->name))->values();

            return view('quotes.create', ['shop' => $shop, 'products' => $products, 'product' => null]);
        }

        $product = Product::where('is_active', true)->find($request->integer('product'));
        if (! $product) {
            abort(404);
        }

        if ($problem = $this->quotes->requestProblem($user, $product)) {
            NotificationService::error($problem);

            return redirect()->route('products.show', $product);
        }
        if ($open = $this->quotes->openRequest($user, $product)) {
            NotificationService::info(__('You already have an open quote request for this product.'));

            return redirect()->route('quotes.show', $open);
        }

        $product->load(['seller', 'mainImage', 'variants' => fn ($q) => $q->where('is_active', true)->ordered()]);
        $product->variants->each(fn (ProductVariant $variant) => $variant->setRelation('product', $product));

        return view('quotes.create', [
            'product' => $product,
            'shop' => $product->seller,
            'minimum' => $this->quotes->minimumFor($product),
            'business' => BusinessProfile::forUser($user->id),
            'address' => $user->defaultAddress(),
            'islandsByAtoll' => app(DeliveryService::class)->islandsByAtoll(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $product = Product::where('is_active', true)->find($request->integer('product_id'));
        if (! $product) {
            return back()->withErrors(['product_id' => __('This product is not available.')]);
        }

        $minimum = $this->quotes->minimumFor($product);
        $request->merge([
            'buyer_tin' => GstService::normaliseTin($request->input('buyer_tin')) ?: null,
            'island_id' => $request->filled('island_id') ? $request->input('island_id') : null,
        ]);
        $data = $request->validate([
            'product_variant_id' => [$product->has_variants ? 'required' : 'nullable', 'integer'],
            'quantity' => 'required|integer|min:'.$minimum.'|max:'.QuoteService::MAX_QUANTITY,
            'island_id' => 'nullable|integer|exists:islands,id',
            'island' => 'required_without:island_id|nullable|string|max:100',
            'atoll' => 'nullable|string|max:100',
            'needed_by' => 'nullable|date|after_or_equal:today|before:'.now()->addYear()->toDateString(),
            'notes' => 'nullable|string|max:2000',
            'buyer_business_name' => 'required|string|max:150',
            'buyer_tin' => ['nullable', 'string', 'max:30', new MiraTin],
            'buyer_business_address' => 'required|string|max:300',
        ], [
            'quantity.min' => __('Bulk quotes start at :count units for this product.', ['count' => $minimum]),
            'island.required_without' => __('Please pick the island to deliver to, or type its name.'),
            'product_variant_id.required' => __('Please choose an option (size, colour...) first.'),
        ], GstService::buyerAttributes() + ['needed_by' => __('needed-by date')]);

        $variant = null;
        if ($product->has_variants) {
            $variant = ProductVariant::where('product_id', $product->id)->where('is_active', true)->find((int) $data['product_variant_id']);
            if (! $variant) {
                return back()->withInput()->withErrors(['product_variant_id' => __('This option is not available.')]);
            }
        }

        try {
            $quote = $this->quotes->request($user, $product, $variant, $data + ['save_business' => $request->boolean('save_business')]);
        } catch (QuoteException $e) {
            NotificationService::error($e->getMessage());

            return $e->existing ? redirect()->route('quotes.show', $e->existing) : redirect()->route('products.show', $product);
        }

        NotificationService::success(__('Your request was sent to :shop. We will email you when they reply.', ['shop' => $quote->shopName()]));

        return redirect()->route('quotes.show', $quote);
    }

    public function show(Request $request, QuoteRequest $quote)
    {
        $this->authorizeCustomer($request, $quote);
        $this->quotes->expireDue();
        $quote->refresh()->load(['seller', 'product.mainImage', 'order', 'messages.sender']);
        $this->quotes->markRead($quote, 'customer');

        return view('quotes.show', [
            'quote' => $quote,
            'inCart' => $this->quotes->cartLineFor($quote, $request->user()) !== null,
        ]);
    }

    public function accept(Request $request, QuoteRequest $quote): RedirectResponse
    {
        $this->authorizeCustomer($request, $quote);

        try {
            $this->quotes->accept($quote, $request->user());
        } catch (QuoteException $e) {
            NotificationService::error($e->getMessage());

            return redirect()->route('quotes.show', $quote);
        }

        NotificationService::success(__(':label is in your cart: :line. Check out by :date.', [
            'label' => $quote->label(),
            'line' => QuoteService::priceLine($quote),
            'date' => $quote->valid_until?->translatedFormat('j M Y'),
        ]));

        return redirect()->route('cart');
    }

    public function addToCart(Request $request, QuoteRequest $quote): RedirectResponse
    {
        $this->authorizeCustomer($request, $quote);

        try {
            $this->quotes->addToCartAgain($quote, $request->user());
        } catch (QuoteException $e) {
            NotificationService::error($e->getMessage());

            return redirect()->route('quotes.show', $quote);
        }

        NotificationService::success(__(':label is back in your cart.', ['label' => $quote->label()]));

        return redirect()->route('cart');
    }

    public function decline(Request $request, QuoteRequest $quote): RedirectResponse
    {
        $this->authorizeCustomer($request, $quote);
        $data = $request->validate(['reason' => 'nullable|string|max:500']);

        try {
            $this->quotes->decline($quote, 'customer', $data['reason'] ?? null);
        } catch (QuoteException $e) {
            NotificationService::error($e->getMessage());

            return redirect()->route('quotes.show', $quote);
        }

        NotificationService::success(__('Done: the request is closed and :shop has been told.', ['shop' => $quote->shopName()]));

        return redirect()->route('quotes.show', $quote);
    }

    public function message(Request $request, QuoteRequest $quote): RedirectResponse
    {
        $this->authorizeCustomer($request, $quote);
        $data = $request->validateWithBag('quoteMessage', ['body' => 'required|string|max:'.QuoteService::MESSAGE_MAX_LENGTH]);

        try {
            $this->quotes->postMessage($quote, $request->user(), 'customer', $data['body']);
        } catch (QuoteException $e) {
            NotificationService::error($e->getMessage());

            return redirect()->route('quotes.show', $quote);
        }

        NotificationService::success(__('Message sent.'));

        return redirect()->to(route('quotes.show', $quote).'#messages');
    }

    /**
     * Only the customer who asked can see or act on a request.
     */
    protected function authorizeCustomer(Request $request, QuoteRequest $quote): void
    {
        abort_unless((int) $quote->customer_id === (int) $request->user()->id, 403);
    }
}
