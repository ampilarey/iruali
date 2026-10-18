<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Support\HomeLayout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Admin → Home page: move and hide blocks, add product rows and text, preview, publish.
 */
class HomePageBuilderTest extends TestCase
{
    use RefreshDatabase;

    protected function admin(): User
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin'])->id);

        return $admin;
    }

    public function test_changes_stay_in_the_draft_until_published(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->get(route('admin.home-page'))->assertOk()->assertSee('data-home-block="hero"', false);

        $this->post(route('admin.home-page.store'), ['type' => 'text', 'title_en' => 'Eid opening hours', 'text_en' => 'Orders placed on Eid go out on Sunday.', 'button_en' => 'Read more', 'link' => 'https://iruali.mv/help'])->assertSessionHas('success');
        $this->post(route('admin.home-page.move', 'sell_cta'), ['action' => 'toggle']);
        $this->assertTrue(HomeLayout::hasDraft());

        // The live page is unchanged; the preview shows the draft
        $this->post(route('logout'));
        $this->get(route('home'))->assertOk()->assertDontSee('Eid opening hours');
        $this->actingAs($admin)->get(route('home', ['preview' => 1]))->assertSee('Eid opening hours')->assertSee('data-home-preview', false);

        $this->post(route('admin.home-page.publish'), ['action' => 'publish'])->assertSessionHas('success');
        $this->assertFalse(HomeLayout::hasDraft());
        $this->post(route('logout'));
        $this->get(route('home'))->assertSee('Eid opening hours')->assertSee('Orders placed on Eid go out on Sunday.');
    }

    public function test_blocks_move_and_custom_ones_are_removed(): void
    {
        $this->actingAs($this->admin());
        $this->post(route('admin.home-page.move', 'deals'), ['action' => 'up']);
        $types = array_column(HomeLayout::draft(), 'type');
        $this->assertLessThan(array_search('departments', $types), array_search('deals', $types));

        $this->post(route('admin.home-page.move', 'hero'), ['action' => 'remove']); // built-in blocks are only hidden
        $this->assertContains('hero', array_column(HomeLayout::draft(), 'type'));

        $this->post(route('admin.home-page.store'), ['type' => 'text', 'title_en' => 'Hello']);
        $id = collect(HomeLayout::draft())->firstWhere('type', 'text')['id'];
        $this->post(route('admin.home-page.move', $id), ['action' => 'remove']);
        $this->assertNull(collect(HomeLayout::draft())->firstWhere('type', 'text'));

        $this->post(route('admin.home-page.store'), ['type' => 'text', 'link' => 'javascript:alert(1)'])->assertSessionHasErrors('link');
    }

    public function test_a_product_row_from_a_category(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->create(['category_id' => $category->id, 'is_active' => true, 'stock_quantity' => 5, 'name' => ['en' => 'Coconut oil 500ml']]);

        $this->actingAs($this->admin())->post(route('admin.home-page.store'), ['type' => 'product_row', 'title_en' => 'Kitchen picks', 'source' => 'category', 'source_value' => $category->id]);
        $this->post(route('admin.home-page.publish'), ['action' => 'publish']);
        $this->get(route('home'))->assertSee('Kitchen picks')->assertSee('Coconut oil 500ml');

        $this->actingAs(User::factory()->create())->get(route('admin.home-page'))->assertForbidden();
    }
}
