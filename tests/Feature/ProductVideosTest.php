<?php

namespace Tests\Feature;

use App\Http\Middleware\SecurityHeaders;
use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Support\ProductVideo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Product videos: the link in the seller's product form (YouTube, Shorts, TikTok, Instagram,
 * Facebook) stored as provider + id, the click-to-load player on the product page, and the
 * Content-Security-Policy that allows exactly those players.
 */
class ProductVideosTest extends TestCase
{
    use RefreshDatabase;

    public const CSP = "frame-src 'self' https://www.youtube-nocookie.com https://www.tiktok.com https://www.instagram.com https://www.facebook.com";

    public static function links(): array
    {
        return [
            'YouTube' => ['https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'youtube', 'dQw4w9WgXcQ'],
            'YouTube with a start time' => ['https://youtube.com/watch?t=42s&v=dQw4w9WgXcQ', 'youtube', 'dQw4w9WgXcQ'],
            'YouTube mobile' => ['https://m.youtube.com/watch?v=dQw4w9WgXcQ&feature=share', 'youtube', 'dQw4w9WgXcQ'],
            'YouTube short link' => ['https://youtu.be/dQw4w9WgXcQ?si=Abc123', 'youtube', 'dQw4w9WgXcQ'],
            'YouTube without https' => ['youtube.com/embed/dQw4w9WgXcQ', 'youtube', 'dQw4w9WgXcQ'],
            'YouTube live' => ['https://www.youtube.com/live/dQw4w9WgXcQ', 'youtube', 'dQw4w9WgXcQ'],
            'YouTube Shorts' => ['https://www.youtube.com/shorts/aqz-KE-bpKQ', 'youtube_short', 'aqz-KE-bpKQ'],
            'TikTok' => ['https://www.tiktok.com/@scout2015/video/6718335390845095173?is_from_webapp=1', 'tiktok', '6718335390845095173'],
            'TikTok embed' => ['https://www.tiktok.com/embed/v2/6718335390845095173', 'tiktok', '6718335390845095173'],
            'Instagram post' => ['https://www.instagram.com/p/CxYz123AbC_/', 'instagram', 'CxYz123AbC_'],
            'Instagram reel' => ['https://www.instagram.com/reel/C1a2b3c4D5e/?igsh=MTc4', 'instagram_reel', 'C1a2b3c4D5e'],
            'Instagram reels' => ['https://instagram.com/reels/C1a2b3c4D5e', 'instagram_reel', 'C1a2b3c4D5e'],
            'Instagram reel under a user name' => ['https://www.instagram.com/reefshop/reel/C1a2b3c4D5e/', 'instagram_reel', 'C1a2b3c4D5e'],
            'Facebook page video' => ['https://www.facebook.com/iruali/videos/1234567890123456/', 'facebook', '1234567890123456'],
            'Facebook video with a title' => ['https://www.facebook.com/iruali/videos/beach-day/1234567890123456/', 'facebook', '1234567890123456'],
            'Facebook watch' => ['https://www.facebook.com/watch/?v=1234567890123456', 'facebook', '1234567890123456'],
            'Facebook mobile' => ['https://m.facebook.com/watch?v=1234567890123456', 'facebook', '1234567890123456'],
            'Facebook reel' => ['https://www.facebook.com/reel/1234567890123456', 'facebook_reel', '1234567890123456'],
        ];
    }

    #[DataProvider('links')]
    public function test_links_are_read_as_provider_and_id(string $url, string $provider, string $id): void
    {
        $video = ProductVideo::parse($url);

        $this->assertNotNull($video, $url);
        $this->assertSame([$provider, $id], [$video->provider, $video->id]);
        // What the form shows again reads back the same
        $again = ProductVideo::parse($video->watchUrl());
        $this->assertSame([$provider, $id], [$again?->provider, $again?->id]);
    }

    public function test_other_links_are_refused(): void
    {
        foreach ([
            'https://vimeo.com/123456789',
            'https://www.youtube.com/watch?v=tooshort',
            'https://www.youtube.com.evil.example/watch?v=dQw4w9WgXcQ',
            'https://evil.example/?next=https://youtube.com/watch?v=dQw4w9WgXcQ',
            'javascript:alert(1)//youtube.com/watch?v=dQw4w9WgXcQ',
            'ftp://youtube.com/watch?v=dQw4w9WgXcQ',
            'https://user:secret@youtube.com/watch?v=dQw4w9WgXcQ',
            'https://youtube.com:8443/watch?v=dQw4w9WgXcQ',
            'https://www.youtube.com/@iruali',
            'https://www.tiktok.com/@reefshop',
            'https://www.tiktok.com/@reefshop/photo/6718335390845095173',
            'https://www.instagram.com/reefshop/',
            'https://www.facebook.com/iruali',
            'https://www.facebook.com/watch/?v=abc',
            'not a link',
            '',
        ] as $url) {
            $this->assertNull(ProductVideo::parse($url), $url);
        }

        foreach (['https://vm.tiktok.com/ZMabc123/', 'https://vt.tiktok.com/ZSabc123/', 'https://fb.watch/abc123XYZ/', 'https://www.facebook.com/share/v/1AbCdEfGh/', 'https://www.facebook.com/share/r/1AbCdEfGh/'] as $short) {
            $this->assertNull(ProductVideo::parse($short), $short);
            $this->assertTrue(ProductVideo::isShortLink($short), $short);
        }
        $this->assertFalse(ProductVideo::isShortLink('https://vimeo.com/123456789'));
    }

    public function test_the_player_is_16_9_for_youtube_and_9_16_for_tiktok_shorts_and_reels(): void
    {
        $this->assertSame('landscape', ProductVideo::make('youtube', 'dQw4w9WgXcQ')->aspect());
        $this->assertSame('landscape', ProductVideo::make('facebook', '1234567890123456')->aspect());
        foreach ([['youtube_short', 'aqz-KE-bpKQ'], ['tiktok', '6718335390845095173'], ['instagram_reel', 'C1a2b3c4D5e'], ['facebook_reel', '1234567890123456']] as [$provider, $id]) {
            $this->assertTrue(ProductVideo::make($provider, $id)->isPortrait(), $provider);
        }
        $this->assertSame('post', ProductVideo::make('instagram', 'CxYz123AbC_')->aspect());

        $this->assertSame('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?autoplay=1&rel=0&playsinline=1', ProductVideo::make('youtube', 'dQw4w9WgXcQ')->embedUrl());
        $this->assertSame('https://www.tiktok.com/player/v1/6718335390845095173?autoplay=1&rel=0', ProductVideo::make('tiktok', '6718335390845095173')->embedUrl());
        $this->assertSame('https://www.instagram.com/reel/C1a2b3c4D5e/embed/', ProductVideo::make('instagram_reel', 'C1a2b3c4D5e')->embedUrl());
        $this->assertSame('https://www.facebook.com/plugins/video.php?href=https%3A%2F%2Fwww.facebook.com%2Freel%2F1234567890123456&show_text=false&autoplay=true', ProductVideo::make('facebook_reel', '1234567890123456')->embedUrl());

        // A stored value this class does not know shows nothing
        $this->assertNull(ProductVideo::make('vimeo', '123456'));
        $this->assertNull(ProductVideo::make('youtube', '<script>'));
        $this->assertNull(ProductVideo::make(null, null));
    }

    protected function seller(): User
    {
        $user = User::factory()->create(['is_seller' => true, 'seller_approved' => true]);
        $user->roles()->attach(Role::firstOrCreate(['name' => 'seller'], ['display_name' => 'Seller'])->id);

        return $user;
    }

    protected function form(Category $category, array $extra = []): array
    {
        return array_merge(['name_en' => 'Reef sandals', 'sku' => 'SANDAL-1', 'category_id' => $category->id, 'price' => 250, 'stock_quantity' => 4], $extra);
    }

    public function test_sellers_add_change_and_remove_a_video_in_the_product_form(): void
    {
        $seller = $this->seller();
        $category = Category::factory()->create();

        $this->actingAs($seller)->get(route('seller.products.create'))->assertOk()
            ->assertSee('name="video_url"', false)
            ->assertSeeInOrder(['name="main_image"', 'name="video_url"', 'data-variant-editor'], false);

        $this->post(route('seller.products.store'), $this->form($category, ['video_url' => 'https://youtu.be/dQw4w9WgXcQ']))
            ->assertSessionHasNoErrors()->assertRedirect(route('seller.products.index'));
        $product = Product::where('sku', 'SANDAL-1')->firstOrFail();
        $this->assertSame(['youtube', 'dQw4w9WgXcQ'], [$product->video_provider, $product->video_id]);

        $this->get(route('seller.products.edit', $product))->assertOk()
            ->assertSee('value="https://www.youtube.com/watch?v=dQw4w9WgXcQ"', false)
            ->assertSee('Now showing a YouTube video.');

        // Another site, or a short share link, is refused with a hint; the video stays
        $this->from(route('seller.products.edit', $product))->put(route('seller.products.update', $product), $this->form($category, ['video_url' => 'https://vimeo.com/123456789']))
            ->assertSessionHasErrors(['video_url' => 'Use a link to a video on YouTube, TikTok, Instagram or Facebook.']);
        $this->put(route('seller.products.update', $product), $this->form($category, ['video_url' => 'https://vm.tiktok.com/ZMabc123/']))
            ->assertSessionHasErrors('video_url');
        $this->assertStringContainsString('short share link', session('errors')->first('video_url'));
        $this->assertSame('youtube', $product->fresh()->video_provider);

        $this->put(route('seller.products.update', $product), $this->form($category, ['video_url' => 'https://www.tiktok.com/@reefshop/video/6718335390845095173']))->assertSessionHasNoErrors();
        $this->assertSame(['tiktok', '6718335390845095173'], [$product->fresh()->video_provider, $product->fresh()->video_id]);

        // A form without the field leaves it; an emptied field removes it
        $this->put(route('seller.products.update', $product), $this->form($category))->assertSessionHasNoErrors();
        $this->assertSame('tiktok', $product->fresh()->video_provider);
        $this->put(route('seller.products.update', $product), $this->form($category, ['video_url' => '']))->assertSessionHasNoErrors();
        $this->assertSame([null, null], [$product->fresh()->video_provider, $product->fresh()->video_id]);
    }

    public function test_the_product_page_loads_the_player_only_after_play_is_pressed(): void
    {
        $product = Product::factory()->create(['is_active' => true, 'stock_quantity' => 3, 'video_provider' => 'youtube', 'video_id' => 'dQw4w9WgXcQ', 'name' => ['en' => 'Reef sandals', 'dv' => 'ރީފް ސެންޑަލް']]);

        $html = $this->get(route('products.show', $product))->assertOk()
            ->assertSee('data-product-video="youtube"', false)
            ->assertSee('data-video-play', false)
            ->assertSee('data-src="https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?autoplay=1&amp;rel=0&amp;playsinline=1"', false)
            ->assertSee('aspect-video', false)
            ->assertSee('Play the video')
            ->assertSee('Nothing loads from YouTube until you press play.')
            ->assertSee("document.createElement('iframe')", false)
            ->getContent();

        // Nothing from the provider is requested before the click: no frame, image or script from it
        $this->assertStringNotContainsString('<iframe', $html);
        $this->assertDoesNotMatchRegularExpression('/\s(?:src|srcset|href)="https?:\/\/[^"]*(?:youtube|ytimg|googlevideo)/i', preg_replace('/<noscript>.*?<\/noscript>/s', '', $html));

        // Portrait players for TikTok, and the page works right to left
        $product->forceFill(['video_provider' => 'tiktok', 'video_id' => '6718335390845095173'])->save();
        $this->get(route('products.show', $product))->assertOk()->assertSee('aspect-[9/16]', false)->assertSee('data-src="https://www.tiktok.com/player/v1/6718335390845095173', false);
        $this->get('/dv/products/'.$product->slug)->assertOk()
            ->assertSee('dir="rtl"', false)
            ->assertSee('ވީޑިއޯ ކުޅުއްވާ')
            ->assertSee('data-video-play', false);

        // No video: no player
        $plain = Product::factory()->create(['is_active' => true]);
        $this->get(route('products.show', $plain))->assertOk()->assertDontSee('data-product-video', false)->assertDontSee('data-video-play', false);
    }

    public function test_the_csp_allows_exactly_the_video_providers_to_be_framed(): void
    {
        $this->assertSame(self::CSP, SecurityHeaders::contentSecurityPolicy());
        $this->get('/')->assertOk()->assertHeader('Content-Security-Policy', self::CSP);

        $product = Product::factory()->create(['is_active' => true, 'video_provider' => 'instagram_reel', 'video_id' => 'C1a2b3c4D5e']);
        $this->get(route('products.show', $product))->assertHeader('Content-Security-Policy', self::CSP);

        // Every player the page can create is on that list
        foreach (array_keys(ProductVideo::PROVIDERS) as $provider) {
            $video = ProductVideo::make($provider, str_starts_with($provider, 'youtube') ? 'dQw4w9WgXcQ' : (str_starts_with($provider, 'instagram') ? 'C1a2b3c4D5e' : '1234567890123456'));
            $origin = 'https://'.parse_url($video->embedUrl(), PHP_URL_HOST);
            $this->assertContains($origin, ProductVideo::FRAME_SOURCES, $provider);
        }
    }
}
