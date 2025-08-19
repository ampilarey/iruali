<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Product;
use App\Models\Category;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Wishlist;
use App\Models\Order;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;

class NotificationSystemTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $user;
    protected $product;
    protected $category;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test data
        $this->user = User::factory()->create([
            'email_verified_at' => now(),
            'phone_verified_at' => now()
        ]);
        $this->category = Category::factory()->create();
        $this->product = Product::factory()->create([
            'category_id' => $this->category->id,
            'seller_id' => $this->user->id,
            'name' => 'Test Product'
        ]);
        
        // Create test voucher
        \App\Models\Voucher::create([
            'code' => 'TEST123',
            'type' => 'percent',
            'amount' => 10.00,
            'min_order' => 50.00,
            'max_uses' => 100,
            'used_count' => 0,
            'valid_from' => now()->subDay(),
            'valid_until' => now()->addDay(),
            'is_active' => true
        ]);
    }

    /** @test */
    public function it_shows_success_notification_for_registration()
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'phone' => '1234567890',
            'password' => 'StrongPassword123!@#',
            'password_confirmation' => 'StrongPassword123!@#',
            'address' => 'Test Address',
            'city' => 'Test City',
            'state' => 'Test State',
            'country' => 'Test Country',
            'postal_code' => '12345',
            'date_of_birth' => '1990-01-01',
            'gender' => 'male',
            'terms' => 'on'
        ]);

        $response->assertRedirect('/verification/notice');
        $response->assertSessionHas('notification');
        
        $notification = session('notification');
        $this->assertEquals('success', $notification['type']);
        $this->assertEquals('Success', $notification['title']);
        $this->assertStringContainsString('Registration successful', $notification['message']);
    }

    /** @test */
    public function it_shows_success_notification_for_login()
    {
        $response = $this->post('/login', [
            'email' => $this->user->email,
            'password' => 'password'
        ]);

        $response->assertRedirect('/');
        $response->assertSessionHas('notification');
        
        $notification = session('notification');
        $this->assertEquals('success', $notification['type']);
        $this->assertEquals('Success', $notification['title']);
        $this->assertStringContainsString('Login successful', $notification['message']);
    }

    /** @test */
    public function it_shows_success_notification_for_logout()
    {
        $this->actingAs($this->user);

        $response = $this->post('/logout');

        $response->assertRedirect('/');
        $response->assertSessionHas('notification');
        
        $notification = session('notification');
        $this->assertEquals('success', $notification['type']);
        $this->assertEquals('Success', $notification['title']);
        $this->assertStringContainsString('Logout successful', $notification['message']);
    }

    /** @test */
    public function it_shows_success_notification_for_email_verification()
    {
        $this->actingAs($this->user);
        $otp = \App\Models\OTP::createForEmail($this->user->email, 'verification');
        $code = $otp->code;
        $response = $this->post('/auth/verify/email/otp', [
            'email' => $this->user->email,
            'code' => $code
        ]);
        $response->assertRedirect();
        $response->assertSessionHas('notification');
        $notification = session('notification');
        $this->assertEquals('success', $notification['type']);
        $this->assertEquals('Success', $notification['title']);
        $this->assertStringContainsString('Email verified successfully', $notification['message']);
    }

    /** @test */
    public function it_shows_success_notification_for_phone_verification()
    {
        $this->actingAs($this->user);
        $otp = \App\Models\OTP::createForPhone($this->user->phone, 'verification');
        $code = $otp->code;
        $response = $this->post('/auth/verify/phone/otp', [
            'phone' => $this->user->phone,
            'code' => $code
        ]);
        $response->assertRedirect();
        $response->assertSessionHas('notification');
        $notification = session('notification');
        $this->assertEquals('success', $notification['type']);
        $this->assertEquals('Success', $notification['title']);
        $this->assertStringContainsString('Phone verified successfully', $notification['message']);
    }

    /** @test */
    public function it_shows_success_notification_for_adding_to_cart()
    {
        $this->actingAs($this->user);

        $response = $this->post(route('cart.add'), [
            'product_id' => $this->product->id,
            'quantity' => 1
        ]);

        $response->assertRedirect(route('cart'));
        $response->assertSessionHas('notification');
        
        $notification = session('notification');
        $this->assertEquals('success', $notification['type']);
        $this->assertEquals('Success', $notification['title']);
        $this->assertStringContainsString('Test Product added to cart successfully', $notification['message']);
    }

    /** @test */
    public function it_shows_success_notification_for_removing_from_cart()
    {
        $this->actingAs($this->user);
        
        // Create cart and cart item
        $cart = \App\Models\Cart::factory()->create(['user_id' => $this->user->id]);
        $cartItem = \App\Models\CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => $this->product->id,
            'quantity' => 1
        ]);

        $response = $this->delete(route('cart.remove', ['item' => $cartItem->id]));

        $response->assertRedirect(route('cart'));
        $response->assertSessionHas('notification');
        
        $notification = session('notification');
        $this->assertEquals('success', $notification['type']);
        $this->assertEquals('Success', $notification['title']);
        $this->assertStringContainsString('Test Product removed from cart successfully', $notification['message']);
    }

    /** @test */
    public function it_shows_success_notification_for_clearing_cart()
    {
        $this->actingAs($this->user);
        
        // Create cart
        \App\Models\Cart::factory()->create(['user_id' => $this->user->id]);

        $response = $this->post(route('cart.clear'));

        $response->assertRedirect(route('cart'));
        $response->assertSessionHas('notification');
        
        $notification = session('notification');
        $this->assertEquals('success', $notification['type']);
        $this->assertEquals('Success', $notification['title']);
        $this->assertStringContainsString('Cart cleared successfully', $notification['message']);
    }

    /** @test */
    public function it_shows_success_notification_for_adding_to_wishlist()
    {
        $this->actingAs($this->user);

        $response = $this->post(route('wishlist.add', ['product' => $this->product->id]), [
            'product_id' => $this->product->id
        ]);

        $response->assertRedirect(route('wishlist'));
        $response->assertSessionHas('notification');
        
        $notification = session('notification');
        $this->assertEquals('success', $notification['type']);
        $this->assertEquals('Success', $notification['title']);
        $this->assertStringContainsString('Test Product added to wishlist successfully', $notification['message']);
    }

    /** @test */
    public function it_shows_success_notification_for_removing_from_wishlist()
    {
        $this->actingAs($this->user);
        
        // Create wishlist item
        $wishlistItem = \App\Models\Wishlist::factory()->create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id
        ]);

        $response = $this->delete(route('wishlist.remove', ['product' => $wishlistItem->id]));

        $response->assertRedirect(route('wishlist'));
        $response->assertSessionHas('notification');
        
        $notification = session('notification');
        $this->assertEquals('success', $notification['type']);
        $this->assertEquals('Success', $notification['title']);
        $this->assertStringContainsString('Test Product removed from wishlist successfully', $notification['message']);
    }

    /** @test */
    public function it_shows_success_notification_for_clearing_wishlist()
    {
        $this->actingAs($this->user);
        
        // Create wishlist item
        \App\Models\Wishlist::factory()->create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id
        ]);

        $response = $this->delete(route('wishlist.clear'));

        $response->assertRedirect(route('wishlist'));
        $response->assertSessionHas('notification');
        
        $notification = session('notification');
        $this->assertEquals('success', $notification['type']);
        $this->assertEquals('Success', $notification['title']);
        $this->assertStringContainsString('Wishlist cleared successfully', $notification['message']);
    }

    /** @test */
    public function it_shows_success_notification_for_voucher_application()
    {
        $this->actingAs($this->user);
        // Create cart and add product to meet min_order
        $cart = \App\Models\Cart::factory()->create(['user_id' => $this->user->id]);
        \App\Models\CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => $this->product->id,
            'quantity' => 1,
            'price' => 100.00
        ]);
        $response = $this->post('/cart/apply-voucher', [
            'voucher_code' => 'TEST123'
        ]);
        $response->assertRedirect();
        $response->assertSessionHas('notification');
        $notification = session('notification');
        $this->assertEquals('success', $notification['type']);
        $this->assertEquals('Success', $notification['title']);
        $this->assertStringContainsString('Voucher TEST123 applied successfully', $notification['message']);
    }

    /** @test */
    public function it_shows_success_notification_for_voucher_removal()
    {
        $this->actingAs($this->user);

        $response = $this->post('/cart/remove-voucher');

        $response->assertRedirect();
        $response->assertSessionHas('notification');
        
        $notification = session('notification');
        $this->assertEquals('success', $notification['type']);
        $this->assertEquals('Success', $notification['title']);
        $this->assertStringContainsString('Voucher removed successfully', $notification['message']);
    }

    /** @test */
    public function it_shows_error_notification_for_invalid_credentials()
    {
        $response = $this->post('/login', [
            'email' => 'invalid@example.com',
            'password' => 'wrongpassword'
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('email');
    }

    /** @test */
    public function it_shows_error_notification_for_order_creation_failure()
    {
        $this->actingAs($this->user);
        // Create cart and add product so cart is not empty
        $cart = \App\Models\Cart::factory()->create(['user_id' => $this->user->id]);
        \App\Models\CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => $this->product->id,
            'quantity' => 1,
            'price' => 100.00
        ]);
        $response = $this->post('/orders', [
            'shipping_address' => 'Test Address',
            'shipping_city' => 'Test City',
            'shipping_state' => 'Test State',
            'shipping_zip' => '12345',
            'shipping_country' => 'Test Country',
            'payment_method' => 'invalid' // invalid value
            // 'agree_terms' omitted intentionally
        ]);
        $response->assertRedirect();
        $response->assertSessionHasErrors(['payment_method', 'agree_terms']);
    }

    /** @test */
    public function it_shows_info_notification_for_duplicate_wishlist_item()
    {
        $this->actingAs($this->user);
        
        // Create wishlist item first
        Wishlist::factory()->create([
            'user_id' => $this->user->id,
            'product_id' => $this->product->id
        ]);

        // Try to add the same product again
        $response = $this->post(route('wishlist.add', ['product' => $this->product->id]), [
            'product_id' => $this->product->id
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('notification');
        
        $notification = session('notification');
        $this->assertEquals('info', $notification['type']);
        $this->assertEquals('Information', $notification['title']);
    }

    /** @test */
    public function notification_service_methods_work_correctly()
    {
        // Test success notification
        NotificationService::success('Test success message', 'Custom Title');
        $this->assertTrue(session()->has('notification'));
        
        $notification = session('notification');
        $this->assertEquals('success', $notification['type']);
        $this->assertEquals('Custom Title', $notification['title']);
        $this->assertEquals('Test success message', $notification['message']);

        // Test error notification
        NotificationService::error('Test error message');
        $notification = session('notification');
        $this->assertEquals('error', $notification['type']);
        $this->assertEquals('Error', $notification['title']);
        $this->assertEquals('Test error message', $notification['message']);

        // Test warning notification
        NotificationService::warning('Test warning message');
        $notification = session('notification');
        $this->assertEquals('warning', $notification['type']);
        $this->assertEquals('Warning', $notification['title']);
        $this->assertEquals('Test warning message', $notification['message']);

        // Test info notification
        NotificationService::info('Test info message');
        $notification = session('notification');
        $this->assertEquals('info', $notification['type']);
        $this->assertEquals('Information', $notification['title']);
        $this->assertEquals('Test info message', $notification['message']);

        // Test question notification
        NotificationService::question('Test question message');
        $notification = session('notification');
        $this->assertEquals('question', $notification['type']);
        $this->assertEquals('Confirm', $notification['title']);
        $this->assertEquals('Test question message', $notification['message']);
    }

    /** @test */
    public function notification_service_convenience_methods_work()
    {
        // Test created notification
        NotificationService::created('Product');
        $notification = session('notification');
        $this->assertStringContainsString('Product created successfully', $notification['message']);

        // Test updated notification
        NotificationService::updated('Product');
        $notification = session('notification');
        $this->assertStringContainsString('Product updated successfully', $notification['message']);

        // Test deleted notification
        NotificationService::deleted('Product');
        $notification = session('notification');
        $this->assertStringContainsString('Product deleted successfully', $notification['message']);

        // Test added to cart notification
        NotificationService::addedToCart('Test Product');
        $notification = session('notification');
        $this->assertStringContainsString('Test Product added to cart successfully', $notification['message']);

        // Test removed from cart notification
        NotificationService::removedFromCart('Test Product');
        $notification = session('notification');
        $this->assertStringContainsString('Test Product removed from cart successfully', $notification['message']);

        // Test added to wishlist notification
        NotificationService::addedToWishlist('Test Product');
        $notification = session('notification');
        $this->assertStringContainsString('Test Product added to wishlist successfully', $notification['message']);

        // Test removed from wishlist notification
        NotificationService::removedFromWishlist('Test Product');
        $notification = session('notification');
        $this->assertStringContainsString('Test Product removed from wishlist successfully', $notification['message']);

        // Test order placed notification
        NotificationService::orderPlaced();
        $notification = session('notification');
        $this->assertStringContainsString('Order placed successfully', $notification['message']);

        // Test voucher applied notification
        NotificationService::voucherApplied('TEST123');
        $notification = session('notification');
        $this->assertStringContainsString('Voucher TEST123 applied successfully', $notification['message']);

        // Test voucher removed notification
        NotificationService::voucherRemoved();
        $notification = session('notification');
        $this->assertStringContainsString('Voucher removed successfully', $notification['message']);
    }

    /** @test */
    public function multiple_notifications_work_correctly()
    {
        $notifications = [
            [
                'type' => 'success',
                'title' => 'Success',
                'message' => 'First notification'
            ],
            [
                'type' => 'error',
                'title' => 'Error',
                'message' => 'Second notification'
            ]
        ];

        NotificationService::multiple($notifications);
        $this->assertTrue(session()->has('notifications'));
        
        $sessionNotifications = session('notifications');
        $this->assertCount(2, $sessionNotifications);
        $this->assertEquals('success', $sessionNotifications[0]['type']);
        $this->assertEquals('error', $sessionNotifications[1]['type']);
    }
} 