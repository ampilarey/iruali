<?php

namespace Tests\Feature;

use App\Models\CartItem;
use App\Models\Category;
use App\Models\ItemRequest;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\ItemRequestDeclined;
use App\Notifications\ItemRequestQuoted;
use App\Services\ItemRequestService;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Support\PreorderFixtures;
use Tests\TestCase;

/**
 * "Can't find it? We'll bring it for you" (App\Services\ItemRequestService): a customer asks, an admin
 * prices it (a pre-order product only that customer can buy, never listed), the customer accepts it
 * into the cart and orders it like any booking-fee pre-order.
 */
class ItemRequestTest extends TestCase
{
    use PreorderFixtures, RefreshDatabase;

    protected User $shop;

    protected User $admin;

    protected Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        Storage::fake('public');
        $this->travelTo(Carbon::parse('2026-10-25 10:00'));
        Setting::set(['free_delivery_over' => 0, 'delivery_fee_islands' => 75]);
        $this->shop = $this->seller('iruali Direct');
        $this->admin = $this->staffMember('admin');
        $this->category = Category::factory()->create(['status' => 'active']);
    }

    protected function ask(User $customer, array $fields = []): ItemRequest
    {
        $this->actingAs($customer)->post(route('item-requests.store'), array_merge([
            'link' => 'https://www.example-shop.com/products/running-shoes',
            'title' => 'Trail running shoes',
            'options' => 'size 42, black',
            'quantity' => 2,
            'details' => 'The waterproof version please.',
        ], $fields))->assertRedirect();

        return ItemRequest::latest('id')->firstOrFail();
    }

    protected function price(ItemRequest $request, array $fields = []): void
    {
        $this->actingAs($this->admin)->post(route('admin.item-requests.quote', $request), array_merge([
            'seller_id' => $this->shop->id, 'category_id' => $this->category->id, 'title' => 'Trail running shoes (waterproof)',
            'unit_price' => 1200, 'payment' => 'deposit', 'deposit_percent' => 30, 'ships_in_days' => 21, 'valid_days' => 7,
            'quote_message' => 'Comes from the UK.',
        ], $fields))->assertSessionHas('success');
    }

    public function test_a_customer_asks_for_an_item_and_admins_see_it(): void
    {
        $this->get(route('item-requests.create'))->assertRedirect(route('login'));
        $customer = User::factory()->create();

        $this->actingAs($customer)->get(route('item-requests.create', ['q' => 'trail shoes']))->assertOk()
            ->assertSee('Can&#039;t find it? We&#039;ll bring it for you', false)->assertSee('value="trail shoes"', false)
            ->assertSee(route('policies.prohibited'), false);
        $this->post(route('item-requests.store'), ['title' => '', 'quantity' => 0, 'link' => 'not a link'])->assertSessionHasErrors(['title', 'quantity', 'link']);

        $request = $this->ask($customer, ['photo' => UploadedFile::fake()->image('shoe.jpg', 400, 400)]);
        $this->assertSame([ItemRequest::NEW, $customer->id, 2, 'size 42, black'], [$request->status, $request->user_id, $request->quantity, $request->options]);
        Storage::disk('public')->assertExists($request->photo_path);
        $this->get(route('item-requests.show', $request))->assertOk()->assertSee('Waiting for our price')->assertSee('Trail running shoes');
        $this->get(route('item-requests.index'))->assertOk()->assertSee('data-item-request-row="'.$request->id.'"', false);

        // Only the customer sees it; admins (not support) answer it
        $this->actingAs(User::factory()->create())->get(route('item-requests.show', $request))->assertNotFound();
        $this->actingAs($this->staffMember('support'))->get(route('admin.item-requests'))->assertForbidden();
        $this->actingAs($this->admin)->get(route('admin.item-requests'))->assertOk()->assertSee('data-admin-item-request="'.$request->id.'"', false);
        $this->get(route('admin.item-requests.show', $request))->assertOk()->assertSee('Send a price')->assertSee('iruali Direct');
        $this->assertSame(1, collect(\App\Support\AdminInbox::all())->firstWhere('key', 'item_requests')['count'] ?? null);

        // At most a few waiting at once
        foreach (range(2, ItemRequest::MAX_OPEN) as $n) {
            $this->ask($customer, ['title' => 'Item number '.$n]);
        }
        $this->actingAs($customer)->post(route('item-requests.store'), ['title' => 'One too many', 'quantity' => 1])->assertSessionHas('error');
        $this->assertSame(ItemRequest::MAX_OPEN, ItemRequest::count());
    }

    public function test_the_price_is_a_private_pre_order_the_customer_accepts_and_orders(): void
    {
        $customer = User::factory()->create();
        $request = $this->ask($customer);
        $this->price($request);

        $request->refresh();
        $product = $request->product;
        $this->assertSame([ItemRequest::QUOTED, 2400.0, 720.0, '2026-11-01'], [$request->status, $request->total(), $request->payNow(), $request->valid_until->toDateString()]);
        $this->assertFalse((bool) $product->is_active);
        $this->assertSame([$request->id, $this->shop->id, 1200.0, 0, 21, 2, 'percent', 30.0], [
            $product->item_request_id, $product->seller_id, (float) $product->price, $product->stock_quantity,
            $product->preorder_lead_days, $product->preorder_limit, $product->preorder_deposit_type, (float) $product->preorder_deposit_value,
        ]);
        $this->assertSame('Trail running shoes (waterproof)', $product->getTranslation('name', 'en'));
        $this->assertSame('https://www.example-shop.com/products/running-shoes', $product->source_url);
        Notification::assertSentTo($customer, ItemRequestQuoted::class);
        $mail = (new ItemRequestQuoted($request))->toMail($customer);
        $this->assertStringContainsString('We can bring it', $mail->subject);

        // Never listed: not in search, the review queue or the shop's own list; the page is the customer's
        $this->get('/search?q=Trail')->assertOk()->assertDontSee('Trail running shoes (waterproof)')->assertSee('data-request-an-item', false);
        $this->assertSame(0, Product::pending()->count());
        $this->actingAs($this->shop)->get(route('seller.products.index'))->assertOk()->assertDontSee('Trail running shoes');
        $this->get(route('seller.products.edit', $product))->assertNotFound();
        $this->actingAs(User::factory()->create())->get(route('products.show', $product))->assertNotFound();
        $this->actingAs($customer)->get(route('products.show', $product))->assertRedirect(route('item-requests.show', $request));
        $this->actingAs($this->admin)->post(route('admin.products.approve', $product->id))->assertNotFound();
        $this->assertFalse((bool) $product->fresh()->is_active);

        // Someone else can't accept it
        $this->actingAs(User::factory()->create())->post(route('item-requests.accept', $request))->assertNotFound();

        // The customer accepts: in the cart, quantity fixed
        $this->actingAs($customer)->get(route('item-requests.show', $request))->assertOk()
            ->assertSee('MVR 2,400.00')->assertSee('Comes from the UK.')->assertSee('data-item-request-accept', false);
        $this->post(route('item-requests.accept', $request))->assertRedirect(route('cart'));
        $line = CartItem::where('product_id', $product->id)->sole();
        $this->assertSame([2, 1200.0], [$line->quantity, (float) $line->price]);
        $this->assertSame(ItemRequest::ACCEPTED, $request->fresh()->status);
        $this->get(route('cart'))->assertOk()->assertSee('data-item-request-line="'.$request->id.'"', false)->assertSee('fixed by our price');

        // Ordered like any booking-fee pre-order
        $result = app(OrderService::class)->createOrderFromCart($customer, $this->shippingData() + ['agree_booking_fee' => true], $line->cart);
        $this->assertTrue($result['success'], $result['message']);
        $order = $result['order']->fresh();
        $this->assertEqualsWithDelta(720, (float) $order->deposit_amount, 0.01);
        $this->assertTrue((bool) $order->items()->sole()->is_preorder);
        $request->refresh();
        $this->assertSame([ItemRequest::ORDERED, $order->id], [$request->status, $request->order_id]);
        $this->get(route('item-requests.show', $request))->assertSee('data-item-request-order', false)->assertDontSee('data-item-request-cancel', false);
    }

    public function test_declining_cancelling_and_expiry(): void
    {
        $customer = User::factory()->create();

        $declined = $this->ask($customer, ['title' => 'Fireworks']);
        $this->actingAs($this->admin)->post(route('admin.item-requests.decline', $declined), ['reason' => ''])->assertSessionHasErrors('reason');
        $this->post(route('admin.item-requests.decline', $declined), ['reason' => 'Fireworks cannot be shipped to the Maldives.'])->assertSessionHas('success');
        $this->assertSame(ItemRequest::DECLINED, $declined->fresh()->status);
        Notification::assertSentTo($customer, ItemRequestDeclined::class);
        $this->actingAs($customer)->get(route('item-requests.show', $declined))->assertSee('data-item-request-declined', false)->assertSee('Fireworks cannot be shipped');

        // Cancelling an accepted price takes it out of the cart
        $cancelled = $this->ask($customer);
        $this->price($cancelled);
        $this->actingAs($customer)->post(route('item-requests.accept', $cancelled));
        $this->assertSame(1, CartItem::where('product_id', $cancelled->fresh()->product_id)->count());
        $this->post(route('item-requests.cancel', $cancelled))->assertRedirect();
        $this->assertSame(ItemRequest::CANCELLED, $cancelled->fresh()->status);
        $this->assertSame(0, CartItem::where('product_id', $cancelled->fresh()->product_id)->count());
        $this->post(route('item-requests.cancel', $cancelled))->assertSessionHas('error');

        // A price past its last day can't be accepted
        $expiring = $this->ask($customer, ['title' => 'Camping stove']);
        $this->price($expiring, ['valid_days' => 2, 'payment' => 'full']);
        $this->assertNull($expiring->fresh()->deposit_percent);
        $this->assertNull($expiring->fresh()->product->preorder_deposit_type);
        $this->travel(3)->days();
        $this->assertTrue($expiring->fresh()->isExpired());
        $this->actingAs($customer)->get(route('item-requests.show', $expiring))->assertSee('Price expired')->assertDontSee('data-item-request-accept', false);
        $this->post(route('item-requests.accept', $expiring))->assertSessionHas('error', 'This price has expired. Ask us again and we will send a new one.');

        // The admin can send a new price; only a shop allowed pre-orders can fill it
        $notAllowed = $this->stockedProduct($this->seller('No Imports'))->seller;
        $notAllowed->forceFill(['preorders_allowed' => false])->save();
        $this->actingAs($this->admin)->post(route('admin.item-requests.quote', $expiring), [
            'seller_id' => $notAllowed->id, 'category_id' => $this->category->id, 'unit_price' => 500, 'payment' => 'full', 'ships_in_days' => 14, 'valid_days' => 7,
        ])->assertSessionHas('error', 'Choose a shop that is allowed to take pre-orders.');
        $this->price($expiring, ['unit_price' => 900]);
        $this->assertTrue($expiring->fresh()->canAccept());
    }

    public function test_a_link_shared_from_another_app_fills_the_form(): void
    {
        // The installed app is a share target: Android opens the form with what was shared
        $manifest = json_decode(file_get_contents(public_path('site.webmanifest')), true);
        $this->assertSame(['action' => '/request-an-item', 'method' => 'GET', 'params' => ['title' => 'title', 'text' => 'text', 'url' => 'url']], $manifest['share_target']);
        $this->assertSame(parse_url(route('item-requests.create'), PHP_URL_PATH), $manifest['share_target']['action']);
        $this->assertContains('/request-an-item', array_column($manifest['shortcuts'], 'url'));

        // Not signed in: sign in first, then back to the form with the shared link
        $shared = route('item-requests.create', ['title' => 'Trail Shoes | Example Shop', 'url' => 'https://www.example-shop.com/p/123']);
        $this->get($shared)->assertRedirect(route('login'));
        $this->assertSame($shared, session('url.intended'));

        $customer = User::factory()->create();
        $this->actingAs($customer)->get($shared)->assertOk()
            ->assertSee('value="https://www.example-shop.com/p/123"', false)->assertSee('value="Trail Shoes | Example Shop"', false);

        // Many apps put the link inside the text
        $this->get(route('item-requests.create', ['text' => 'Check out this jacket! https://m.example.com/item/9?ref=app']))->assertOk()
            ->assertSee('value="https://m.example.com/item/9?ref=app"', false)->assertSee('value="Check out this jacket!"', false);

        // Anything that is not a web link is left out
        $this->get(route('item-requests.create', ['url' => 'javascript:alert(1)', 'title' => 'Lamp']))->assertOk()
            ->assertDontSee('javascript:alert', false)->assertSee('value="Lamp"', false);
    }

    public function test_the_storefront_offers_it(): void
    {
        $this->get('/')->assertOk()->assertSee('data-footer-request-item', false)->assertSee(route('item-requests.create'), false);
        $customer = User::factory()->create();
        $this->actingAs($customer)->get(route('account'))->assertOk()->assertSee(route('item-requests.index'), false);
        $this->assertTrue(app(ItemRequestService::class)->canView($this->ask($customer), $customer));
    }
}
