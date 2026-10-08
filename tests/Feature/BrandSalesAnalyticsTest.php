<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Admin → Analytics → Top brands: units, revenue and share per brand over the last 30 days,
 * counted from the same order lines as the page's top products and sellers.
 */
class BrandSalesAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected User $shop;

    protected Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->shop = User::factory()->create(['is_seller' => true, 'seller_approved' => true, 'business_name' => 'Reef Traders']);
        $this->category = Category::factory()->create(['status' => 'active']);
    }

    protected function staff(string $role): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::firstOrCreate(['name' => $role], ['display_name' => ucfirst($role)])->id);

        return $user;
    }

    protected function product(?string $brand, float $price): Product
    {
        return Product::factory()->create([
            'category_id' => $this->category->id, 'seller_id' => $this->shop->id, 'is_active' => true,
            'stock_quantity' => 50, 'price' => $price, 'compare_price' => null, 'brand' => $brand,
        ]);
    }

    /** An order with the given lines: [product, quantity] at each product's price. */
    protected function order(array $attributes, array $lines): Order
    {
        $order = Order::factory()->create($attributes + ['total_amount' => 0]);
        foreach ($lines as [$product, $quantity]) {
            $order->items()->create(['product_id' => $product->id, 'quantity' => $quantity, 'price' => $product->price]);
        }

        return $order;
    }

    public function test_top_brands_count_the_last_30_days_like_top_products_and_sellers(): void
    {
        $handLine = $this->product('Reefline', 100);
        $dryBag = $this->product('Reefline', 50);
        $snorkel = $this->product('Bluewave', 250);
        $rope = $this->product(null, 20);

        // Paid and on its way: counted
        $this->order(['status' => 'delivered', 'payment_status' => 'paid', 'created_at' => now()->subDays(2)], [[$handLine, 2], [$snorkel, 1]]);
        $this->order(['status' => 'processing', 'payment_status' => 'paid', 'created_at' => now()->subDays(5)], [[$dryBag, 1], [$rope, 2]]);
        // Unpaid and cancelled (as unpaid card orders are after 24 hours): not counted
        $this->order(['status' => 'cancelled', 'payment_status' => 'unpaid', 'created_at' => now()->subDay()], [[$handLine, 10], [$snorkel, 10]]);
        // Unpaid but not cancelled yet: counted, exactly as the top products and sellers count it
        $this->order(['status' => 'pending', 'payment_status' => 'unpaid', 'created_at' => now()->subHour()], [[$dryBag, 1]]);
        // Older than 30 days, or deleted: not counted
        $this->order(['status' => 'delivered', 'payment_status' => 'paid', 'created_at' => now()->subDays(31)], [[$handLine, 7]]);
        $this->order(['status' => 'delivered', 'payment_status' => 'paid', 'created_at' => now()->subDays(3)], [[$snorkel, 3]])->delete();
        // A product the shop has since deleted still counts for its brand
        $dryBag->delete();

        $response = $this->actingAs($this->staff('admin'))->get('/admin/analytics')->assertOk();

        $sales = $response->viewData('brandSales');
        $rows = $sales['rows']->map(fn (array $row) => [$row['brand']->name, $row['units'], $row['revenue'], round($row['share'], 1)])->all();
        $this->assertSame([['Reefline', 4, 300.0, 54.5], ['Bluewave', 1, 250.0, 45.5]], $rows);
        $this->assertSame(['units' => 2, 'revenue' => 40.0], $sales['unbranded']);
        $this->assertSame(550.0, $sales['brandRevenue']);

        $reefline = $handLine->brandModel;
        $response->assertSeeInOrder(['Top brands', 'Reefline', 'MVR 300.00', '54.5%', 'Bluewave', 'MVR 250.00', '45.5%', 'Unbranded products', 'MVR 40.00'])
            ->assertSee('href="'.route('admin.brands.edit', $reefline).'"', false)
            ->assertSee('href="'.route('brands.show', $reefline).'"', false);
    }

    public function test_no_sales_show_an_empty_state_and_unbranded_sales_alone_keep_their_row(): void
    {
        $this->actingAs($this->staff('admin'))->get('/admin/analytics')->assertOk()
            ->assertSee('No brand sales in the last 30 days.')
            ->assertDontSee('Unbranded products');

        $this->order(['status' => 'delivered', 'payment_status' => 'paid'], [[$this->product(null, 20), 3]]);
        $this->get('/admin/analytics')->assertOk()
            ->assertSeeInOrder(['No brand sales in the last 30 days.', 'Unbranded products', 'MVR 60.00']);
    }

    public function test_brands_with_nothing_on_sale_get_no_storefront_link_and_finance_gets_no_admin_link(): void
    {
        $gone = $this->product('Old Label', 80);
        $this->order(['status' => 'delivered', 'payment_status' => 'paid'], [[$gone, 1]]);
        $gone->update(['is_active' => false]);
        $brand = $gone->brandModel;

        $this->actingAs($this->staff('admin'))->get('/admin/analytics')->assertOk()
            ->assertSee('href="'.route('admin.brands.edit', $brand).'"', false)
            ->assertDontSee('href="'.route('brands.show', $brand).'"', false);

        // Finance staff may open analytics but not the brand pages in admin
        $this->actingAs($this->staff('finance'))->get('/admin/analytics')->assertOk()
            ->assertSee('Old Label')
            ->assertDontSee('href="'.route('admin.brands.edit', $brand).'"', false);
    }
}
