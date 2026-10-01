<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductImage;
use App\Support\ImageVariants;
use App\Traits\SecureFileUpload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImagesAndSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_uploads_get_webp_copies_and_cards_use_the_small_one(): void
    {
        Storage::fake('public');
        ImageVariants::forget();
        $uploader = new class
        {
            use SecureFileUpload;

            public function put(UploadedFile $file): ?string
            {
                return $this->storeFileSecurely($file, 'products');
            }

            public function drop(string $path): bool
            {
                return $this->deleteFile($path);
            }
        };

        $path = $uploader->put(UploadedFile::fake()->image('photo.jpg', 1600, 1200));
        $this->assertNotNull($path);
        Storage::disk('public')->assertExists(ImageVariants::variantPath($path, 400));
        Storage::disk('public')->assertExists(ImageVariants::variantPath($path, 1200));
        $this->assertLessThan(Storage::disk('public')->size($path), Storage::disk('public')->size(ImageVariants::variantPath($path, 400)));

        $product = Product::factory()->create(['is_active' => true]);
        $image = ProductImage::create(['product_id' => $product->id, 'url' => Storage::disk('public')->url($path), 'is_main' => true, 'sort_order' => 0]);
        $this->assertStringEndsWith('-400.webp', $image->variant(400));
        $this->assertStringEndsWith('-1200.webp', $image->variant(1200));
        $this->assertSame($image->url, $image->variant(800), 'unknown widths fall back to the original');

        // An image with no variants (older upload) still shows the original
        $old = ProductImage::create(['product_id' => $product->id, 'url' => Storage::disk('public')->url('products/old.jpg'), 'is_main' => false, 'sort_order' => 1]);
        $this->assertSame($old->url, $old->variant(400));

        $uploader->drop($path);
        Storage::disk('public')->assertMissing(ImageVariants::variantPath($path, 400));
    }

    public function test_search_matches_either_language_brand_and_sku_through_search_text(): void
    {
        $product = Product::factory()->create(['name' => ['en' => 'Solar Lantern', 'dv' => 'ސޯލަރ ބައްތި'], 'brand' => 'SunCo', 'sku' => 'SUN-LT-01', 'is_active' => true]);
        $this->assertStringContainsString('solar lantern', $product->fresh()->search_text);
        $this->assertStringContainsString('ސޯލަރ', $product->fresh()->search_text);

        foreach (['lantern', 'LANTERN', 'ސޯލަރ', 'sunco', 'sun-lt'] as $q) {
            $this->get('/search?q='.urlencode($q))->assertOk()->assertSee('Solar Lantern');
        }
        $this->get('/search?q=lantern')->assertSee('Solar Lantern');
        $this->getJson('/search/suggest?q=lant')->assertOk()->assertJsonFragment(['name' => 'Solar Lantern']);

        $product->update(['name' => ['en' => 'Reef Torch', 'dv' => 'ފަރު ބައްތި']]);
        $this->assertStringContainsString('reef torch', $product->fresh()->search_text);
        $this->assertStringNotContainsString('solar', $product->fresh()->search_text);
    }
}
