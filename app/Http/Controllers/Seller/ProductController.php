<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Http\Requests\Seller\ProductRequest;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductChangeRequest;
use App\Services\ProductEditService;
use App\Services\ProductReviewService;
use App\Support\CurrentShop;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $products = CurrentShop::get()->products()
            ->with(['category', 'variants', 'pendingChange'])
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->q.'%';
                $q->where(fn ($w) => $w->where('name', 'like', $term)->orWhere('sku', 'like', $term));
            })
            ->when($request->status === 'active', fn ($q) => $q->where('is_active', true))
            ->when($request->status === 'pending', fn ($q) => $q->where('is_active', false))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('seller.products.index', compact('products'));
    }

    public function create()
    {
        return view('seller.products.form', [
            'product' => new Product(['stock_quantity' => 0, 'reorder_point' => 5, 'has_variants' => false]),
            'categories' => $this->categories(),
            'variants' => collect(),
            'brandOptions' => \App\Models\Brand::orderBy('name')->pluck('name'),
        ]);
    }

    public function store(ProductRequest $request, ProductEditService $editor, ProductReviewService $review)
    {
        $product = new Product;
        $product->seller_id = CurrentShop::id();
        $product->is_active = false; // new listings wait for admin approval
        $editor->apply($product, $editor->formFromRequest($request));
        app(\App\Services\OnboardingService::class)->refresh(CurrentShop::get()->fresh());

        // A trusted shop (an admin turned on auto-approval for it) goes live at once
        if ($review->publishIfTrusted($product, CurrentShop::get())) {
            return redirect()->route('seller.products.index')
                ->with('success', __('Product published. It is live in the shop now.'));
        }

        \App\Support\TeamAlerts::send('product_review', 'iruali: new product to review: '.$product->getTranslation('name', 'en', false).' ('.CurrentShop::get()->shopName().').', route('admin.products'));

        return redirect()->route('seller.products.index')
            ->with('success', 'Product submitted. It will be visible in the shop once an admin approves it.');
    }

    public function edit(Product $product, ProductReviewService $review)
    {
        $this->authorizeOwner($product);

        // An edit waiting for review: the form shows it, so saving again builds on it
        $pending = $product->pendingChange;
        if ($pending) {
            ['product' => $draft, 'variants' => $variants] = $review->preview($product, $pending);
        }

        return view('seller.products.form', [
            'product' => $draft ?? $product,
            'categories' => $this->categories(),
            'variants' => $variants ?? $product->variants()->ordered()->get(),
            'brandOptions' => \App\Models\Brand::orderBy('name')->pluck('name'),
            'pendingChange' => $pending,
            'lastReview' => $pending ? null : $product->changeRequests()->whereIn('status', [ProductChangeRequest::APPROVED, ProductChangeRequest::REJECTED])->latest('reviewed_at')->latest('id')->first(),
        ]);
    }

    public function update(ProductRequest $request, Product $product, ProductEditService $editor, ProductReviewService $review)
    {
        $this->authorizeOwner($product);

        $form = $editor->formFromRequest($request);

        // An approved product of a shop that is not trusted: stock now, the rest waits for an admin
        if ($review->needsReview($product)) {
            $change = $review->submitForm($product, $form, $request->user());
            if ($change) {
                \App\Support\TeamAlerts::send('product_review', 'iruali: product change to review: '.$product->getTranslation('name', 'en', false).' ('.$product->seller?->shopName().').', route('admin.product-changes.show', $change));
            }

            return redirect()->route('seller.products.index')->with('success', $change
                ? __('Saved. Your changes are waiting for review; shoppers see the current version until an admin approves them. Stock changes are live now.')
                : __('Product updated successfully.'));
        }

        // Never approved (not public yet), or a trusted shop: straight to the product
        $editor->apply($product, $form);
        if ($product->pendingChange) {
            $review->withdraw($product->pendingChange); // the form showed it and has now been saved over it
        }
        $published = $review->publishIfTrusted($product);

        return redirect()->route('seller.products.index')
            ->with('success', $published ? __('Product published. It is live in the shop now.') : 'Product updated successfully.');
    }

    /**
     * Take back the edit waiting for review: the product stays as shoppers see it now.
     */
    public function withdrawChange(Product $product, ProductReviewService $review)
    {
        $this->authorizeOwner($product);

        if ($product->pendingChange) {
            $review->withdraw($product->pendingChange);
        }

        return redirect()->route('seller.products.edit', $product)
            ->with('success', __('Your waiting changes were withdrawn. The product stays as shoppers see it now.'));
    }

    public function destroy(Product $product)
    {
        $this->authorizeOwner($product);

        $product->delete();

        return redirect()->route('seller.products.index')
            ->with('success', 'Product deleted.');
    }

    protected function authorizeOwner(Product $product): void
    {
        abort_unless((int) $product->seller_id === (int) CurrentShop::id(), 403);
    }

    protected function categories()
    {
        return Category::where('status', 'active')->orderBy('name')->get();
    }
}
