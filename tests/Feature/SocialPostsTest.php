<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\Setting;
use App\Models\SocialPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Social posts with an approval step (Facebook, Instagram, Telegram) and product share tracking.
 */
class SocialPostsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.facebook.page_id' => '123', 'services.facebook.page_token' => 'tok', 'services.facebook.instagram_account_id' => '456', 'services.telegram.bot_token' => 'bot']);
        Setting::set(['telegram_channel_id' => '@iruali_deals']);
    }

    protected function admin(): User
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin'])->id);

        return $admin;
    }

    public function test_a_post_is_approved_then_posted_to_each_channel(): void
    {
        Http::fake([
            'graph.facebook.com/*/photos' => Http::response(['id' => 'fb1']),
            'graph.facebook.com/*/media' => Http::response(['id' => 'container1']),
            'graph.facebook.com/*/media_publish' => Http::response(['id' => 'ig1']),
            'api.telegram.org/*' => Http::response(['ok' => true]),
        ]);
        $this->actingAs($this->admin())->post(route('admin.social.store'), ['message' => 'Eid deals are live', 'link' => 'https://iruali.mv/deals', 'image_url' => 'https://iruali.mv/img/eid.jpg', 'channels' => ['facebook', 'instagram', 'telegram']])->assertSessionHas('success');
        $post = SocialPost::sole();
        $this->assertSame('pending', $post->status);

        $this->artisan('social:publish');
        Http::assertNothingSent(); // not approved yet

        $this->post(route('admin.social.approve', $post))->assertSessionHas('success');
        $this->artisan('social:publish')->assertSuccessful();
        $post->refresh();
        $this->assertSame('posted', $post->status);
        $this->assertTrue($post->results['instagram']['ok']);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'api.telegram.org') && $r['chat_id'] === '@iruali_deals');
    }

    public function test_a_failed_channel_is_shown_and_instagram_needs_a_picture(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['message' => 'Token expired']], 400)]);
        $post = SocialPost::create(['message' => 'Hi', 'channels' => ['facebook', 'instagram'], 'status' => 'approved', 'scheduled_at' => now()]);
        $this->artisan('social:publish');
        $post->refresh();
        $this->assertSame('failed', $post->status);
        $this->assertSame('Token expired', $post->results['facebook']['error']);
        $this->assertSame('Instagram needs a picture', $post->results['instagram']['error']);
    }

    public function test_approving_a_product_drafts_a_post_when_switched_on(): void
    {
        Setting::set(['social_auto_drafts' => 1]);
        $product = Product::factory()->create(['is_active' => false, 'category_id' => Category::factory()->create()->id, 'seller_id' => User::factory()->create(['is_seller' => false])->id]);
        $this->actingAs($this->admin())->post(route('admin.products.approve', $product->id))->assertSessionHas('success');
        $draft = SocialPost::where('product_id', $product->id)->first();
        $this->assertNotNull($draft);
        $this->assertSame('pending', $draft->status);
        $this->assertStringContainsString(route('products.show', $product), (string) $draft->link);
    }

    public function test_shares_are_counted_and_listed(): void
    {
        $product = Product::factory()->create(['is_active' => true, 'category_id' => Category::factory()->create()->id]);
        $this->get(route('share.go', [$product, 'whatsapp']))->assertRedirect();
        $this->get(route('share.go', [$product, 'telegram']))->assertRedirectContains('t.me/share');
        $this->post(route('share.copied', $product))->assertNoContent();
        $this->assertSame(3, DB::table('share_events')->count());

        $this->actingAs($this->admin())->get(route('admin.social'))->assertOk()->assertSee('3 shares');
    }
}
