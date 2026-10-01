<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\User;
use App\Services\BulkProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductBulkToolsTest extends TestCase
{
    use RefreshDatabase;

    protected User $shop;

    protected User $otherShop;

    protected Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->shop = $this->seller();
        $this->otherShop = $this->seller();
        $this->category = Category::factory()->create(['slug' => 'clothing', 'status' => 'active']);
    }

    protected function seller(): User
    {
        $user = User::factory()->create(['is_seller' => true, 'seller_approved' => true, 'business_name' => 'Shop '.fake()->unique()->word()]);
        $user->roles()->attach(Role::firstOrCreate(['name' => 'seller'], ['display_name' => 'Seller'])->id);

        return $user;
    }

    protected function catalogue(): array
    {
        $tee = Product::factory()->create(['seller_id' => $this->shop->id, 'category_id' => $this->category->id, 'sku' => 'TEE-1', 'name' => ['en' => 'Reef Tee', 'dv' => 'ރީފް ޓީ'], 'price' => 150, 'stock_quantity' => 20, 'is_active' => true, 'approved_at' => now(), 'brand' => 'Iruali']);
        $hoodie = Product::factory()->create(['seller_id' => $this->shop->id, 'category_id' => $this->category->id, 'sku' => 'HOOD-1', 'name' => ['en' => 'Hoodie'], 'price' => 300, 'has_variants' => true, 'is_active' => true, 'approved_at' => now()]);
        $m = ProductVariant::factory()->for($hoodie)->attributes(['Size' => 'M', 'Colour' => 'Blue'])->stock(4)->create(['sku' => 'HOOD-1-M']);
        $l = ProductVariant::factory()->for($hoodie)->attributes(['Size' => 'L', 'Colour' => 'Blue'])->stock(6)->priced(320)->create(['sku' => 'HOOD-1-L']);

        return [$tee, $hoodie->fresh(), $m, $l];
    }

    protected function csv(array $rows): UploadedFile
    {
        $lines = [implode(',', \App\Services\ProductCsvService::COLUMNS)];
        foreach ($rows as $row) {
            $lines[] = implode(',', array_map(fn ($c) => '"'.str_replace('"', '""', (string) ($row[$c] ?? '')).'"', \App\Services\ProductCsvService::COLUMNS));
        }

        return UploadedFile::fake()->createWithContent('products.csv', "\xEF\xBB\xBF".implode("\n", $lines)."\n");
    }

    public function test_export_has_a_bom_the_columns_and_one_row_per_variant(): void
    {
        [$tee, $hoodie, $m, $l] = $this->catalogue();
        Product::factory()->create(['seller_id' => $this->otherShop->id, 'sku' => 'NOT-MINE']);

        $response = $this->actingAs($this->shop)->get(route('seller.products.export'))->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $body = $response->getContent();

        $this->assertStringStartsWith("\xEF\xBB\xBFsku,product_sku,product_name_en,product_name_dv,category_slug,brand,price,sale_price,stock,variant_attributes,is_active,description_en,description_dv,image_urls", $body);
        $this->assertStringContainsString('"TEE-1","TEE-1","Reef Tee","ރީފް ޓީ","clothing","Iruali","150.00","","20","","1"', $body);
        $this->assertStringContainsString('"HOOD-1-M","HOOD-1","Hoodie","","clothing",', $body);
        $this->assertStringContainsString('"300.00","","4","Size=M;Colour=Blue","1"', $body);
        $this->assertStringContainsString('"HOOD-1-L","HOOD-1"', $body);
        $this->assertStringContainsString('"320.00","","6","Size=L;Colour=Blue","1"', $body);
        $this->assertStringNotContainsString('NOT-MINE', $body);
        $this->assertSame(4, substr_count($body, "\n")); // header + 3 rows

        $this->actingAs($this->shop)->get(route('seller.products.import.sample'))->assertOk()->assertSee('HOOD-001-M');
        $this->actingAs(User::factory()->create())->get(route('seller.products.export'))->assertForbidden();
    }

    public function test_export_round_trips_through_import_with_a_dry_run_then_apply(): void
    {
        [$tee, $hoodie, $m, $l] = $this->catalogue();
        $csv = $this->actingAs($this->shop)->get(route('seller.products.export'))->getContent();

        // Unchanged file: every row is an update with nothing to change
        $file = UploadedFile::fake()->createWithContent('products.csv', $csv);
        $this->post(route('seller.products.import.preview'), ['file' => $file])->assertOk()->assertSee('3 rows update')->assertSee('No changes');

        // Edit the stock of the tee and the price of the large hoodie, and add a new product and a new variant
        $edited = str_replace(['"150.00","","20"', '"320.00","","6"'], ['"160.00","","25"', '"330.00","","9"'], $csv);
        $edited .= '"CAP-1","","Sun Cap","","clothing","Iruali","90","","12","","1","Keeps the sun off.","",""'."\n";
        $edited .= '"HOOD-1-XL","HOOD-1","","","","","340","","2","Size=XL;Colour=Blue","1","","",""'."\n";
        $edited .= '"BAD-1","","","","","","abc","","x","","maybe","","",""'."\n";
        $response = $this->post(route('seller.products.import.preview'), ['file' => UploadedFile::fake()->createWithContent('products.csv', $edited)])
            ->assertOk()
            ->assertSee('2 rows create')->assertSee('3 rows update')->assertSee('1 row has errors')
            ->assertSee('price: 150.00 → 160.00')->assertSee('stock: 20 → 25')
            ->assertSee('price: 320.00 → 330.00')
            ->assertSee('New variant Size=XL;Colour=Blue')
            ->assertSee('price must be a number');
        preg_match('/name="token" value="([a-zA-Z0-9]{32})"/', $response->getContent(), $match);
        $this->assertNotEmpty($match, 'the preview offers an Apply button with the file token');

        // Nothing has changed yet
        $this->assertSame(20, $tee->fresh()->stock_quantity);
        $this->assertDatabaseMissing('products', ['sku' => 'CAP-1']);

        $this->post(route('seller.products.import.apply'), ['token' => $match[1]])->assertRedirect(route('seller.products.import'))->assertSessionHas('import_result', ['create' => 2, 'update' => 3, 'error' => 1]);

        $this->assertSame(25, $tee->fresh()->stock_quantity);
        $this->assertSame(160.0, (float) $tee->fresh()->price);
        $this->assertSame(330.0, (float) $l->fresh()->price);
        $this->assertSame(9, $l->fresh()->stock_quantity);
        $cap = Product::where('sku', 'CAP-1')->firstOrFail();
        $this->assertFalse($cap->is_active, 'imported products wait for approval');
        $this->assertSame($this->shop->id, $cap->seller_id);
        $this->assertSame('Sun Cap', $cap->getTranslation('name', 'en'));
        $this->assertSame(12, $cap->stock_quantity);
        $xl = ProductVariant::where('sku', 'HOOD-1-XL')->firstOrFail();
        $this->assertSame($hoodie->id, $xl->product_id);
        $this->assertSame(['Size' => 'XL', 'Colour' => 'Blue'], $xl->attributes_list);
        $this->assertSame(15, (int) $hoodie->fresh()->stock_quantity);
        $this->assertDatabaseMissing('products', ['sku' => 'BAD-1']);

        // The token is single-use
        $this->post(route('seller.products.import.apply'), ['token' => $match[1]])->assertNotFound();
    }

    public function test_import_never_touches_another_shops_products_and_reports_errors_by_line(): void
    {
        $theirs = Product::factory()->create(['seller_id' => $this->otherShop->id, 'sku' => 'THEIRS-1', 'price' => 50, 'stock_quantity' => 5]);
        $theirVariant = ProductVariant::factory()->create(['sku' => 'THEIRS-V']);
        $theirVariant->product->update(['seller_id' => $this->otherShop->id]);

        $this->actingAs($this->shop)->post(route('seller.products.import.preview'), ['file' => $this->csv([
            ['sku' => 'THEIRS-1', 'product_name_en' => 'Hijack', 'category_slug' => 'clothing', 'price' => '1', 'stock' => '999'],
            ['sku' => 'THEIRS-V', 'price' => '1', 'stock' => '999'],
            ['sku' => 'NEW-1', 'product_name_en' => 'No category', 'price' => '10'],
            ['sku' => 'NEW-2', 'product_name_en' => 'Fine', 'category_slug' => 'clothing', 'price' => '10', 'stock' => '1'],
            ['sku' => 'NEW-2', 'product_name_en' => 'Dup', 'category_slug' => 'clothing', 'price' => '10'],
        ])])->assertOk()
            ->assertSee('belongs to another shop')
            ->assertSee('valid category_slug')
            ->assertSee('SKU repeats line 5')
            ->assertSee('4 rows have errors')->assertSee('1 row creates');

        $this->assertSame(5, $theirs->fresh()->stock_quantity);
        $this->assertSame(50.0, (float) $theirs->fresh()->price);

        $this->post(route('seller.products.import.preview'), ['file' => UploadedFile::fake()->createWithContent('x.csv', "name,price\nfoo,1\n")])
            ->assertSessionHasErrors('file');
    }

    public function test_bulk_edit_changes_prices_and_stock_with_a_confirm_step(): void
    {
        [$tee, $hoodie, $m, $l] = $this->catalogue();
        $tiny = Product::factory()->create(['seller_id' => $this->shop->id, 'sku' => 'TINY', 'price' => 0.01, 'stock_quantity' => 1]);
        $ids = [$tee->id, $hoodie->id, $tiny->id];

        $this->actingAs($this->shop)->post(route('seller.products.bulk'), ['ids' => $ids, 'action' => 'price_percent', 'value' => -10])
            ->assertOk()->assertSee('This will change 3 products')->assertSee('150.00 → 135.00')->assertSee('300.00 → 270.00');
        $this->assertSame(150.0, (float) $tee->fresh()->price, 'the confirm page changes nothing');

        $this->post(route('seller.products.bulk.apply'), ['ids' => $ids, 'action' => 'price_percent', 'value' => -10])
            ->assertRedirect(route('seller.products.index'))->assertSessionHas('success', '3 products updated.');
        $this->assertSame(135.0, (float) $tee->fresh()->price);
        $this->assertSame(270.0, (float) $hoodie->fresh()->price);
        $this->assertSame(288.0, (float) $l->fresh()->price, 'a variant with its own price moves by the same percentage');
        $this->assertNull($m->fresh()->price, 'a variant without its own price keeps following the product');
        $this->assertSame(0.01, (float) $tiny->fresh()->price, 'never below 0.01');
        $this->assertSame(36.75, app(BulkProductService::class)->adjustedPrice(33.33, 10.25));

        $this->post(route('seller.products.bulk.apply'), ['ids' => [$tee->id, $hoodie->id], 'action' => 'stock_set', 'value' => 7]);
        $this->assertSame(7, $tee->fresh()->stock_quantity);
        $this->assertSame(7, $m->fresh()->stock_quantity);
        $this->assertSame(7, $l->fresh()->stock_quantity);
        $this->assertSame(14, (int) $hoodie->fresh()->stock_quantity);

        $this->post(route('seller.products.bulk.apply'), ['ids' => [$tee->id], 'action' => 'price_percent'])->assertSessionHasErrors('value');
        $this->post(route('seller.products.bulk.apply'), ['ids' => [$tee->id], 'action' => 'stock_set', 'value' => 1.5])->assertSessionHasErrors('value');
    }

    public function test_bulk_activation_respects_admin_approval_and_other_shops_products_are_refused(): void
    {
        [$tee] = $this->catalogue();
        $pending = Product::factory()->create(['seller_id' => $this->shop->id, 'is_active' => false, 'approved_at' => null]);
        $theirs = Product::factory()->create(['seller_id' => $this->otherShop->id]);

        $this->actingAs($this->shop)->post(route('seller.products.bulk.apply'), ['ids' => [$tee->id, $pending->id], 'action' => 'deactivate'])->assertRedirect();
        $this->assertFalse($tee->fresh()->is_active);

        $this->post(route('seller.products.bulk'), ['ids' => [$tee->id, $pending->id], 'action' => 'activate'])->assertOk()->assertSee('stays pending until an admin approves it');
        $this->post(route('seller.products.bulk.apply'), ['ids' => [$tee->id, $pending->id], 'action' => 'activate'])->assertRedirect();
        $this->assertTrue($tee->fresh()->is_active, 'a product an admin approved before can be switched back on');
        $this->assertFalse($pending->fresh()->is_active, 'a product never approved stays pending');

        $this->post(route('seller.products.bulk'), ['ids' => [$tee->id, $theirs->id], 'action' => 'deactivate'])->assertForbidden();
        $this->post(route('seller.products.bulk.apply'), ['ids' => [$theirs->id], 'action' => 'deactivate'])->assertForbidden();
        $this->assertTrue($theirs->fresh()->is_active);

        $this->get(route('seller.products.index'))->assertOk()->assertSee('data-bulk-bar', false)->assertSee('Duplicate')->assertSee('Export CSV');
    }

    public function test_duplicate_copies_the_product_with_its_images_and_variants_at_zero_stock(): void
    {
        [$tee, $hoodie, $m, $l] = $this->catalogue();
        $hoodie->images()->create(['url' => '/storage/products/hoodie.jpg', 'alt_text' => 'Hoodie', 'is_main' => true, 'sort_order' => 0]);
        $hoodie->forceFill(['main_image' => 'products/hoodie.jpg'])->save();

        $this->actingAs($this->shop)->post(route('seller.products.duplicate', $hoodie))->assertRedirect();
        $copy = Product::where('sku', 'HOOD-1-COPY')->firstOrFail();

        $this->assertSame('Hoodie (copy)', $copy->getTranslation('name', 'en'));
        $this->assertNotSame($hoodie->slug, $copy->slug);
        $this->assertFalse($copy->is_active);
        $this->assertNull($copy->approved_at);
        $this->assertSame($this->shop->id, $copy->seller_id);
        $this->assertTrue($copy->has_variants);
        $this->assertSame('/storage/products/hoodie.jpg', $copy->images()->firstOrFail()->url);
        $this->assertSame('products/hoodie.jpg', $copy->main_image);
        $this->assertCount(2, $copy->variants);
        $this->assertSame(0, $copy->variants->sum('stock_quantity'));
        $this->assertSame(0, (int) $copy->stock_quantity);
        $this->assertSame(['Size' => 'L', 'Colour' => 'Blue'], $copy->variants->firstWhere('sku', 'HOOD-1-L-COPY')->attributes_list);
        $this->assertSame(320.0, (float) $copy->variants->firstWhere('sku', 'HOOD-1-L-COPY')->price);
        $this->assertSame(6, $l->fresh()->stock_quantity, 'the original is untouched');

        // Duplicating again picks a free SKU; another shop cannot duplicate it
        $this->post(route('seller.products.duplicate', $hoodie))->assertRedirect();
        $this->assertDatabaseHas('products', ['sku' => 'HOOD-1-COPY2']);
        $this->actingAs($this->otherShop)->post(route('seller.products.duplicate', $hoodie))->assertForbidden();
    }
}
