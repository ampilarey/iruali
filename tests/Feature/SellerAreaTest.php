<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SellerAreaTest extends TestCase
{
    use RefreshDatabase;

    protected function userWithRole(string $role, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $roleModel = Role::firstOrCreate(['name' => $role], ['display_name' => ucfirst($role)]);
        $user->roles()->attach($roleModel->id);

        return $user;
    }

    public function test_guests_are_redirected_and_customers_are_forbidden(): void
    {
        $this->get('/seller/dashboard')->assertRedirect('/login');

        $this->actingAs($this->userWithRole('customer'))
            ->get('/seller/dashboard')
            ->assertForbidden();
    }

    public function test_seller_pages_render(): void
    {
        $seller = $this->userWithRole('seller');
        $product = Product::factory()->create(['seller_id' => $seller->id]);
        $order = Order::factory()->create(['status' => 'delivered']);
        $order->items()->create(['product_id' => $product->id, 'quantity' => 3, 'price' => 40]);

        $this->actingAs($seller);
        $this->get('/seller/analytics')->assertSee('120.00');

        foreach (['/seller/dashboard', '/seller/products', '/seller/products/create', '/seller/orders', '/seller/analytics', '/seller/profile'] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_seller_can_create_product_pending_approval(): void
    {
        $seller = $this->userWithRole('seller');
        $category = Category::factory()->create();

        $this->actingAs($seller)->post('/seller/products', [
            'name_en' => 'Reef Safe Sunscreen',
            'sku' => 'SUN-001',
            'category_id' => $category->id,
            'price' => 150,
            'stock_quantity' => 20,
        ])->assertRedirect(route('seller.products.index'));

        $product = Product::where('sku', 'SUN-001')->firstOrFail();
        $this->assertSame($seller->id, $product->seller_id);
        $this->assertFalse($product->is_active);
        $this->assertSame('Reef Safe Sunscreen', $product->getTranslation('name', 'en'));
    }

    public function test_seller_can_update_and_delete_own_product(): void
    {
        $seller = $this->userWithRole('seller');
        $product = Product::factory()->create(['seller_id' => $seller->id]);

        $this->actingAs($seller)->put(route('seller.products.update', $product), [
            'name_en' => 'Renamed',
            'sku' => $product->sku,
            'category_id' => $product->category_id,
            'price' => 99,
            'stock_quantity' => 3,
        ])->assertRedirect(route('seller.products.index'));

        $product->refresh();
        $this->assertEquals(99, $product->price);
        $this->assertSame('Renamed', $product->getTranslation('name', 'en'));

        $this->delete(route('seller.products.destroy', $product))->assertRedirect();
        $this->assertSoftDeleted($product);
    }

    public function test_seller_cannot_touch_another_sellers_product(): void
    {
        $seller = $this->userWithRole('seller');
        $other = Product::factory()->create();

        $this->actingAs($seller);
        $this->get(route('seller.products.edit', $other))->assertForbidden();
        $this->delete(route('seller.products.destroy', $other))->assertForbidden();
        $this->assertNotSoftDeleted($other);
    }

    public function test_orders_only_show_the_sellers_own_items(): void
    {
        $seller = $this->userWithRole('seller');
        $mine = Product::factory()->create(['seller_id' => $seller->id, 'price' => 100]);
        $theirs = Product::factory()->create(['price' => 500]);

        $order = Order::factory()->create(['status' => 'pending']);
        $order->items()->create(['product_id' => $mine->id, 'quantity' => 2, 'price' => 100]);
        $order->items()->create(['product_id' => $theirs->id, 'quantity' => 1, 'price' => 500]);

        $unrelated = Order::factory()->create();
        $unrelated->items()->create(['product_id' => $theirs->id, 'quantity' => 1, 'price' => 500]);

        $this->actingAs($seller);

        $this->get('/seller/orders')
            ->assertOk()
            ->assertSee($order->order_number)
            ->assertDontSee($unrelated->order_number);

        $this->get(route('seller.orders.show', $order))
            ->assertOk()
            ->assertSee('200.00')
            ->assertDontSee('500.00');

        $this->get(route('seller.orders.show', $unrelated))->assertNotFound();
    }

    public function test_seller_can_update_profile(): void
    {
        $seller = $this->userWithRole('seller');

        $this->actingAs($seller)->put('/seller/profile', [
            'name' => 'Aisha Ibrahim',
            'business_name' => 'Island Crafts',
            'city' => 'Male',
        ])->assertRedirect(route('seller.profile'));

        $this->assertSame('Aisha Ibrahim', $seller->fresh()->name);
        $this->assertSame('Island Crafts', $seller->fresh()->business_name);
    }

    public function test_admin_approving_seller_marks_them_approved(): void
    {
        $admin = $this->userWithRole('admin');
        $seller = $this->userWithRole('seller', ['seller_approved' => false]);

        $this->actingAs($admin)
            ->post(route('admin.sellers.approve', $seller->id))
            ->assertRedirect();

        $this->assertTrue($seller->fresh()->seller_approved);
    }
}
