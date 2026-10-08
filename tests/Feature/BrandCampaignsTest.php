<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Campaign;
use App\Models\CampaignProduct;
use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Brand campaigns: a campaign for one brand takes only that brand's products, shows the brand on
 * its page and shows on the brand's page while it runs. Campaigns without a brand are unchanged.
 */
class BrandCampaignsTest extends TestCase
{
    use RefreshDatabase;

    protected User $seller;

    protected User $admin;

    protected Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();

        $this->seller = User::factory()->create(['is_seller' => true, 'seller_approved' => true, 'business_name' => 'Reef Goods']);
        $this->seller->roles()->attach(Role::firstOrCreate(['name' => 'seller'], ['display_name' => 'Seller'])->id);
        $this->admin = User::factory()->create();
        $this->admin->roles()->attach(Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin'])->id);
        $this->category = Category::factory()->create(['status' => 'active']);
    }

    protected function product(string $name, ?string $brand, array $attributes = []): Product
    {
        return Product::factory()->create(array_merge([
            'name' => ['en' => $name],
            'brand' => $brand,
            'category_id' => $this->category->id,
            'seller_id' => $this->seller->id,
            'is_active' => true,
            'stock_quantity' => 10,
            'price' => 200,
            'compare_price' => null,
        ], $attributes));
    }

    protected function join(Campaign $campaign, Product $product, bool $approved = true): CampaignProduct
    {
        return CampaignProduct::create([
            'campaign_id' => $campaign->id, 'product_id' => $product->id, 'seller_id' => $product->seller_id,
            'discount_percent' => null, 'approved_at' => $approved ? now() : null,
        ]);
    }

    /** The admin form's fields for a campaign, with changes. */
    protected function form(array $changes = []): array
    {
        return array_merge([
            'name' => 'Reefline week', 'type' => 'sale', 'placement' => 'home_strip', 'theme_colour' => '#0E7C86',
            'starts_at' => now()->subDay()->format('Y-m-d H:i'), 'ends_at' => now()->addWeek()->format('Y-m-d H:i'),
            'headline_en' => 'Reefline week', 'is_active' => 1, 'discount_percent' => 10,
        ], $changes);
    }

    public function test_an_admin_makes_a_campaign_for_one_brand(): void
    {
        $reefline = $this->product('Hand line kit', 'Reefline')->brandModel;
        $this->product('Snorkel set', 'Bluewave');

        $this->actingAs($this->admin)->get(route('admin.campaigns.create'))->assertOk()
            ->assertSee('Only for brand')
            ->assertSee('<option value="'.$reefline->id.'" >Reefline</option>', false);

        $this->post(route('admin.campaigns.store'), $this->form(['brand_id' => 999999]))->assertSessionHasErrors(['brand_id' => 'Choose a brand from the list.']);
        $this->post(route('admin.campaigns.store'), $this->form(['brand_id' => $reefline->id]))->assertSessionHasNoErrors()->assertRedirect();

        $campaign = Campaign::where('name', 'Reefline week')->firstOrFail();
        $this->assertSame($reefline->id, $campaign->brand_id);
        $this->assertTrue($campaign->brand->is($reefline));

        $this->get(route('admin.campaigns.index'))->assertOk()->assertSee('Only Reefline');
        $this->get(route('admin.campaigns.edit', $campaign))->assertOk()->assertSee('<option value="'.$reefline->id.'" selected>Reefline</option>', false);

        // Back to a campaign every product can join
        $this->put(route('admin.campaigns.update', $campaign), $this->form(['brand_id' => '']))->assertSessionHasNoErrors();
        $this->assertNull($campaign->fresh()->brand_id);
    }

    public function test_shops_can_only_put_the_brands_own_products_into_a_brand_campaign(): void
    {
        $line = $this->product('Hand line kit', 'Reefline');
        $snorkel = $this->product('Snorkel set', 'Bluewave');
        $rope = $this->product('Plain rope', null);
        $campaign = Campaign::factory()->create(['name' => 'Reefline week', 'brand_id' => $line->brand_id, 'discount_percent' => 10]);

        $this->actingAs($this->seller)->get(route('seller.campaigns'))->assertOk()->assertSee('Only for Reefline products');
        $this->get(route('seller.campaigns.show', $campaign))->assertOk()
            ->assertSee('Only Reefline products can join this campaign.')
            ->assertSee('Hand line kit')->assertDontSee('Snorkel set')->assertDontSee('Plain rope');

        foreach ([[$snorkel->id], [$rope->id], [$line->id, $snorkel->id]] as $chosen) {
            $this->post(route('seller.campaigns.store', $campaign), ['products' => $chosen, 'discount_percent' => 15])
                ->assertSessionHasErrors(['products' => 'Only Reefline products can join this campaign.']);
        }
        $this->assertSame(0, CampaignProduct::count(), 'nothing goes in when any chosen product is another brand');

        $this->post(route('seller.campaigns.store', $campaign), ['products' => [$line->id], 'discount_percent' => 15])
            ->assertSessionHasNoErrors()->assertRedirect(route('seller.campaigns.show', $campaign));
        $row = CampaignProduct::where('campaign_id', $campaign->id)->where('product_id', $line->id)->firstOrFail();

        $this->actingAs($this->admin)->post(route('admin.campaigns.approve', [$campaign, $row]))->assertSessionHasNoErrors();
        $this->assertEquals(170, $line->fresh()->campaignPrice());

        // A shop with nothing of the brand is told so
        $other = User::factory()->create(['is_seller' => true, 'seller_approved' => true]);
        $other->roles()->attach(Role::where('name', 'seller')->first()->id);
        $this->product('Other kettle', 'Kettleco', ['seller_id' => $other->id]);
        $this->actingAs($other)->get(route('seller.campaigns.show', $campaign))->assertOk()
            ->assertSee('You have no active Reefline products to add yet.')->assertDontSee('Other kettle');
    }

    public function test_the_campaign_page_shows_the_brand_and_the_brand_page_shows_the_running_campaign(): void
    {
        $line = $this->product('Hand line kit', 'Reefline');
        $this->product('Snorkel set', 'Bluewave');
        $reefline = $line->brandModel;
        $campaign = Campaign::factory()->create([
            'name' => 'Reefline week', 'headline' => ['en' => 'Reefline week: 20% off'], 'brand_id' => $reefline->id,
            'placement' => 'home_strip', 'cta_url' => '/deals',
        ]);
        $this->join($campaign, $line);

        $this->get(route('campaigns.show', $campaign))->assertOk()
            ->assertSee('data-campaign-brand', false)
            ->assertSee('href="'.route('brands.show', $reefline).'"', false)
            ->assertSee('Only Reefline products are in this campaign.')
            ->assertSee('See everything from Reefline')
            ->assertSee('Hand line kit');

        // The strip on the brand page leads to the campaign page, whatever the banner's own button link
        $this->get('/brands/reefline')->assertOk()
            ->assertSee('data-brand-campaign', false)
            ->assertSee('Reefline week: 20% off')
            ->assertSee('href="'.route('campaigns.show', $campaign).'"', false);
        $this->get('/brands/bluewave')->assertOk()->assertDontSee('data-brand-campaign', false)->assertDontSee('Reefline week: 20% off');
        $this->get('/dv/brands/reefline')->assertOk()->assertSee('data-brand-campaign', false);

        // Only while it runs
        $campaign->update(['starts_at' => now()->addDay(), 'ends_at' => now()->addWeek()]);
        $this->get('/brands/reefline')->assertOk()->assertDontSee('data-brand-campaign', false);
        $this->get(route('campaigns.show', $campaign))->assertOk()->assertSee('data-campaign-brand', false);
    }

    public function test_campaigns_without_a_brand_work_as_before(): void
    {
        $line = $this->product('Hand line kit', 'Reefline');
        $snorkel = $this->product('Snorkel set', 'Bluewave');
        $rope = $this->product('Plain rope', null);
        $campaign = Campaign::factory()->create(['name' => 'Eid sale', 'headline' => ['en' => 'Eid sale'], 'discount_percent' => 10]);

        $this->actingAs($this->admin)->get(route('admin.campaigns.edit', $campaign))->assertOk()
            ->assertSee('<option value="">Any brand: every product can join</option>', false)
            ->assertDontSee('selected>Reefline</option>', false);

        $this->actingAs($this->seller)->get(route('seller.campaigns'))->assertOk()->assertDontSee('Only for');
        $this->get(route('seller.campaigns.show', $campaign))->assertOk()
            ->assertSee('Hand line kit')->assertSee('Snorkel set')->assertSee('Plain rope')
            ->assertDontSee('can join this campaign');

        $this->post(route('seller.campaigns.store', $campaign), ['products' => [$line->id, $snorkel->id, $rope->id], 'discount_percent' => 25])
            ->assertSessionHasNoErrors();
        CampaignProduct::query()->update(['approved_at' => now()]);
        Campaign::forgetDiscounts();

        foreach ([$line, $snorkel, $rope] as $product) {
            $this->assertEquals(150, $product->fresh()->campaignPrice());
        }
        $this->get(route('campaigns.show', $campaign))->assertOk()
            ->assertSee('Hand line kit')->assertSee('Snorkel set')->assertSee('Plain rope')
            ->assertDontSee('data-campaign-brand', false);
        $this->get('/brands/reefline')->assertOk()->assertDontSee('data-brand-campaign', false);
    }

    public function test_a_campaign_cannot_take_a_brand_while_it_holds_other_brands_products(): void
    {
        $line = $this->product('Hand line kit', 'Reefline');
        $snorkel = $this->product('Snorkel set', 'Bluewave');
        $reefline = $line->brandModel;
        $campaign = Campaign::factory()->create(['name' => 'Reefline week', 'discount_percent' => 10]);
        $this->join($campaign, $line);
        $other = $this->join($campaign, $snorkel);

        $this->actingAs($this->admin)->from(route('admin.campaigns.edit', $campaign))
            ->put(route('admin.campaigns.update', $campaign), $this->form(['brand_id' => $reefline->id, 'name' => 'Renamed']))
            ->assertRedirect(route('admin.campaigns.edit', $campaign))
            ->assertSessionHasErrors(['brand_id' => '1 product in this campaign is not a Reefline product. Remove it below first, or keep the campaign open to every brand.']);
        $this->assertNull($campaign->fresh()->brand_id);
        $this->assertSame('Reefline week', $campaign->fresh()->name, 'nothing is saved');

        // With the other brand's product taken out, the campaign can be Reefline's
        $this->delete(route('admin.campaigns.reject', [$campaign, $other]))->assertRedirect();
        $this->put(route('admin.campaigns.update', $campaign), $this->form(['brand_id' => $reefline->id]))->assertSessionHasNoErrors();
        $this->assertSame($reefline->id, $campaign->fresh()->brand_id);
        $this->assertEquals(180, $line->fresh()->campaignPrice(), 'the Reefline product keeps its campaign price');

        // ...but not another brand's while the Reefline product is in
        $this->put(route('admin.campaigns.update', $campaign), $this->form(['brand_id' => $snorkel->brand_id]))
            ->assertSessionHasErrors(['brand_id' => '1 product in this campaign is not a Bluewave product. Remove it below first, or keep the campaign open to every brand.']);
        $this->assertSame($reefline->id, $campaign->fresh()->brand_id);
    }

    public function test_a_product_that_changes_brand_loses_the_brand_campaign_price(): void
    {
        $line = $this->product('Hand line kit', 'Reefline');
        $bag = $this->product('Dry bag', 'Reefline');
        $campaign = Campaign::factory()->create(['brand_id' => $line->brand_id, 'discount_percent' => 10]);
        $this->join($campaign, $line);
        $pending = $this->join($campaign, $bag, approved: false);
        $this->assertEquals(180, $line->fresh()->campaignPrice());

        // The shop relabels both products after they went in
        $line->update(['brand' => 'Bluewave']);
        $bag->update(['brand' => 'Bluewave']);
        Campaign::forgetDiscounts();

        $this->assertNull($line->fresh()->campaignPrice());
        $this->assertEquals(200, (float) $line->fresh()->final_price);
        $this->get(route('campaigns.show', $campaign))->assertOk()->assertDontSee('Hand line kit');

        $this->actingAs($this->admin)->get(route('admin.campaigns.edit', $campaign))->assertOk()
            ->assertSee('Not a Reefline product, so no campaign price');
        $this->post(route('admin.campaigns.approve', [$campaign, $pending]))
            ->assertSessionHasErrors(['participation' => 'This product is not a Reefline product, so it cannot be approved for this campaign.']);
        $this->assertNull($pending->fresh()->approved_at);

        $this->actingAs($this->seller)->get(route('seller.campaigns.show', $campaign))->assertOk()
            ->assertSee('No longer a Reefline product, so it gets no campaign price.');
    }

    public function test_a_brand_with_a_brand_campaign_cannot_be_deleted(): void
    {
        $product = $this->product('Old stock', 'Reefline');
        $brand = $product->brandModel;
        $product->update(['brand' => null]); // no product uses the brand any more
        $campaign = Campaign::factory()->create(['brand_id' => $brand->id]);

        $this->actingAs($this->admin)->delete(route('admin.brands.destroy', $brand))
            ->assertSessionHas('error', fn ($message) => str_contains($message, 'only for this brand'));
        $this->assertNotNull($brand->fresh(), 'the brand stays');
        $this->assertSame($brand->id, $campaign->fresh()->brand_id, 'the campaign stays a brand campaign');

        // Once the campaign has no brand, the brand can go
        $campaign->update(['brand_id' => null]);
        $this->actingAs($this->admin)->delete(route('admin.brands.destroy', $brand))->assertRedirect(route('admin.brands'));
        $this->assertNull($brand->fresh());
    }

    public function test_merging_a_brand_keeps_its_campaigns_for_the_merged_brand(): void
    {
        $samsung = $this->product('Galaxy charger', 'Samsung')->brandModel;
        $duplicate = $this->product('Smart TV', 'Samsung Electronics')->brandModel;
        $campaign = Campaign::factory()->create(['brand_id' => $duplicate->id]);
        $open = Campaign::factory()->create();

        $this->actingAs($this->admin)->post(route('admin.brands.merge', $duplicate), ['target_id' => $samsung->id])->assertRedirect();

        $this->assertNull(Brand::find($duplicate->id));
        $this->assertSame($samsung->id, $campaign->fresh()->brand_id, 'still a brand campaign, now for the merged brand');
        $this->assertNull($open->fresh()->brand_id);
    }
}
