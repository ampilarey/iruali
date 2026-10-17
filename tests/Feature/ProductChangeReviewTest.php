<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductChangeRequest;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\User;
use App\Notifications\ProductChangeReviewed;
use App\Support\AdminInbox;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Concerns\ActsAsShopStaff;
use Tests\TestCase;

/**
 * Changes a shop makes to an approved product wait for an admin (ProductReviewService); shoppers see
 * the approved version meanwhile. Stock applies at once. Trusted shops (an admin's per-shop switch)
 * skip review for new listings and edits.
 */
class ProductChangeReviewTest extends TestCase
{
    use ActsAsShopStaff, RefreshDatabase;

    protected User $shop;

    protected Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        Storage::fake('public');
        Storage::fake('local');
        $this->shop = $this->staffedShop('Reef Goods', ['onboarding_completed_at' => now()]);
        $this->category = Category::factory()->create(['status' => 'active', 'slug' => 'clothing']);
    }

    protected function staff(string $role): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::firstOrCreate(['name' => $role], ['display_name' => ucfirst($role)])->id);

        return $user;
    }

    protected function liveProduct(array $attributes = []): Product
    {
        return Product::factory()->create(array_merge([
            'seller_id' => $this->shop->id,
            'category_id' => $this->category->id,
            'name' => ['en' => 'Reef Tee'],
            'description' => ['en' => 'Soft cotton.'],
            'sku' => 'TEE-1',
            'price' => 150,
            'compare_price' => null,
            'stock_quantity' => 10,
            'reorder_point' => 5,
            'brand' => null,
            'weight' => null,
            'is_active' => true,
            'approved_at' => now()->subWeek(),
        ], $attributes))->fresh();
    }

    /** The product form as it would be sent for this product, with $changes on top. */
    protected function form(Product $product, array $changes = []): array
    {
        return array_merge([
            'name_en' => $product->getTranslation('name', 'en', false),
            'name_dv' => $product->getTranslation('name', 'dv', false),
            'description_en' => $product->getTranslation('description', 'en', false),
            'description_dv' => $product->getTranslation('description', 'dv', false),
            'sku' => $product->sku,
            'category_id' => $product->category_id,
            'price' => $product->price,
            'compare_price' => $product->compare_price,
            'stock_quantity' => $product->stock_quantity,
            'reorder_point' => $product->reorder_point,
            'brand' => $product->brand,
            'weight' => $product->weight,
            'has_variants' => $product->has_variants ? 1 : 0,
        ], $changes);
    }

    protected function variantRow(ProductVariant|array $variant, array $extra = []): array
    {
        if ($variant instanceof ProductVariant) {
            $variant = ['id' => $variant->id, 'attributes' => json_encode($variant->attributes_list), 'sku' => $variant->sku, 'price' => $variant->price, 'stock_quantity' => $variant->stock_quantity, 'is_active' => $variant->is_active ? 1 : 0];
        }

        return array_merge(['low_stock_threshold' => '', 'price' => '', 'is_active' => 1], $variant, $extra);
    }

    public function test_an_edit_to_a_live_product_waits_for_review_and_shoppers_keep_the_old_version(): void
    {
        $product = $this->liveProduct();

        $this->actingAs($this->shop)->put(route('seller.products.update', $product), $this->form($product, ['name_en' => 'Reef Tee Deluxe', 'price' => 199, 'stock_quantity' => 7]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('seller.products.index'))
            ->assertSessionHas('success', 'Saved. Your changes are waiting for review; shoppers see the current version until an admin approves them. Stock changes are live now.');

        $product->refresh();
        $this->assertSame('Reef Tee', $product->getTranslation('name', 'en'));
        $this->assertSame(150.0, (float) $product->price);
        $this->assertSame(7, (int) $product->stock_quantity, 'stock applies at once');
        $this->assertTrue($product->is_active, 'a pending edit never hides the live product');

        $change = ProductChangeRequest::sole();
        $this->assertSame(ProductChangeRequest::PENDING, $change->status);
        $this->assertSame($this->shop->id, $change->seller_id);
        $this->assertSame($this->shop->id, $change->submitted_by);
        $this->assertSame('Reef Tee Deluxe', $change->payload['product']['name']['en']);
        $this->assertSame('199.00', $change->payload['product']['price']);

        $this->get(route('products.show', $product))->assertOk()->assertSee('Reef Tee')->assertDontSee('Reef Tee Deluxe');

        // The Seller Centre shows the waiting edit: badge in the list, notice and prefilled form
        $this->get(route('seller.products.index'))->assertOk()->assertSee('Edit waiting for review');
        $this->get(route('seller.products.edit', $product))->assertOk()
            ->assertSee('data-pending-change', false)
            ->assertSee('value="Reef Tee Deluxe"', false)
            ->assertSee(route('seller.products.changes.withdraw', $product), false);
    }

    public function test_a_stock_only_edit_applies_at_once_without_a_request(): void
    {
        $product = $this->liveProduct(['has_variants' => true, 'stock_quantity' => 0]);
        $m = ProductVariant::factory()->for($product)->attributes(['Size' => 'M'])->stock(3)->create(['sku' => 'TEE-1-M']);
        $l = ProductVariant::factory()->for($product)->attributes(['Size' => 'L'])->stock(4)->priced(160)->create(['sku' => 'TEE-1-L']);
        $product->refresh()->syncStockFromVariants();
        $product->refresh();

        $this->actingAs($this->shop)->put(route('seller.products.update', $product), $this->form($product, [
            'reorder_point' => 2,
            'variants' => [$this->variantRow($m, ['stock_quantity' => 9, 'low_stock_threshold' => 1]), $this->variantRow($l, ['stock_quantity' => 1])],
        ]))->assertSessionHasNoErrors()->assertSessionHas('success', 'Product updated successfully.');

        $this->assertSame(0, ProductChangeRequest::count());
        $this->assertSame(9, $m->fresh()->stock_quantity);
        $this->assertSame(1, $m->fresh()->low_stock_threshold);
        $this->assertSame(1, $l->fresh()->stock_quantity);
        $this->assertSame(10, (int) $product->fresh()->stock_quantity);
        $this->assertSame(2, (int) $product->fresh()->reorder_point);
    }

    public function test_approving_applies_every_field_with_the_new_image_and_variants(): void
    {
        $product = $this->liveProduct(['has_variants' => true, 'stock_quantity' => 0]);
        $m = ProductVariant::factory()->for($product)->attributes(['Size' => 'M'])->stock(3)->create(['sku' => 'TEE-1-M']);
        $l = ProductVariant::factory()->for($product)->attributes(['Size' => 'L'])->stock(4)->create(['sku' => 'TEE-1-L']);
        $other = Category::factory()->create(['status' => 'active']);
        $product->refresh();

        $this->actingAs($this->shop)->put(route('seller.products.update', $product), $this->form($product, [
            'name_en' => 'Island Tee', 'name_dv' => 'ޓީ', 'description_en' => 'Even softer.', 'price' => 175, 'compare_price' => 200,
            'category_id' => $other->id, 'brand' => 'Iruali', 'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'quote_min_quantity' => 20,
            'multibuy' => ['tiers' => [['min_qty' => 2, 'percent' => 5]], 'group' => ''],
            'main_image' => UploadedFile::fake()->image('new.jpg'),
            'variants' => [
                $this->variantRow($m, ['price' => 180, 'stock_quantity' => 5]), // re-priced, stock now
                $this->variantRow(['attributes' => json_encode(['Size' => 'XL']), 'sku' => 'TEE-1-XL', 'stock_quantity' => 2], ['image' => UploadedFile::fake()->image('xl.jpg')]), // added
                // L removed
            ],
        ]))->assertSessionHasNoErrors();

        $change = ProductChangeRequest::sole();
        $staged = $change->stagedFiles();
        $this->assertCount(2, $staged, 'the main image and the new variant image are stored for the edit');
        foreach ($staged as $path) {
            Storage::disk('public')->assertExists($path);
        }
        $this->assertSame(5, $m->fresh()->stock_quantity, 'stock of an existing variant applied at once');
        $xlImage = collect($change->payload['variants'])->firstWhere('sku', 'TEE-1-XL')['image'];
        $this->get(route('seller.products.edit', $product))->assertOk()
            ->assertSee('TEE-1-XL')->assertDontSee('TEE-1-L"', false)
            ->assertSee('name="variants[1][staged_image]" value="'.$xlImage.'"', false)
            ->assertSee('New image, waiting for review.');
        $this->assertNull($m->fresh()->price);
        $this->assertNull($product->fresh()->main_image);

        // The review page shows before → after
        $admin = $this->staff('admin');
        $this->actingAs($admin)->get(route('admin.product-changes'))->assertOk()->assertSee('Island Tee')->assertSee('Reef Goods');
        $this->get(route('admin.product-changes.show', $change))->assertOk()
            ->assertSee('Reef Tee')->assertSee('Island Tee')->assertSee('175.00')->assertSee($other->name)
            ->assertSee('data-image="after"', false)
            ->assertSee('data-variant-status="added"', false)->assertSee('data-variant-status="removed"', false)->assertSee('data-variant-status="changed"', false);

        // Meanwhile the shop sold some: approval keeps the live stock
        $m->update(['stock_quantity' => 4]);

        $this->post(route('admin.product-changes.approve', $change))->assertRedirect(route('admin.product-changes'))->assertSessionHas('success');

        $product->refresh();
        $this->assertSame('Island Tee', $product->getTranslation('name', 'en'));
        $this->assertSame('ޓީ', $product->getTranslation('name', 'dv'));
        $this->assertSame('Even softer.', $product->getTranslation('description', 'en'));
        $this->assertSame(175.0, (float) $product->price);
        $this->assertSame(200.0, (float) $product->compare_price);
        $this->assertSame($other->id, $product->category_id);
        $this->assertSame('Iruali', $product->brand);
        $this->assertSame('youtube', $product->video_provider);
        $this->assertSame(20, (int) $product->quote_min_quantity);
        $this->assertSame([['min_qty' => 2, 'percent' => 5.0]], $product->multibuyOffer->tierList());
        $this->assertContains($product->main_image, $staged);
        $this->assertSame(1, $product->images()->where('is_main', true)->count());
        $this->assertTrue($product->is_active);

        $this->assertSame(180.0, (float) $m->fresh()->price);
        $this->assertSame(4, $m->fresh()->stock_quantity, 'stock that changed since the edit is kept');
        $this->assertNull(ProductVariant::find($l->id), 'the removed variant (never ordered) is deleted');
        $xl = ProductVariant::where('sku', 'TEE-1-XL')->sole();
        $this->assertSame(2, $xl->stock_quantity);
        $this->assertContains($xl->image, $staged);
        $this->assertSame(6, (int) $product->stock_quantity);

        $this->assertSame(ProductChangeRequest::APPROVED, $change->fresh()->status);
        $this->assertSame($admin->id, $change->fresh()->reviewed_by);
        $this->assertTrue(AuditLog::where('action', 'product.change_approved')->where('subject_id', $product->id)->exists());
        Notification::assertSentTo($this->shop, ProductChangeReviewed::class, fn ($n) => $n->change->status === ProductChangeRequest::APPROVED);

        $this->get(route('products.show', $product->fresh()))->assertOk()->assertSee('Island Tee');
    }

    public function test_rejecting_keeps_the_live_version_deletes_the_files_and_tells_the_shop_why(): void
    {
        $product = $this->liveProduct();
        $this->actingAs($this->shop)->put(route('seller.products.update', $product), $this->form($product, ['name_en' => 'Fake Brand Tee', 'main_image' => UploadedFile::fake()->image('x.jpg')]));
        $change = ProductChangeRequest::sole();
        [$path] = $change->stagedFiles();
        Storage::disk('public')->assertExists($path);

        $admin = $this->staff('admin');
        $this->actingAs($admin)->post(route('admin.product-changes.reject', $change), ['reason' => ''])->assertSessionHasErrors('reason');
        $this->post(route('admin.product-changes.reject', $change), ['reason' => 'The new name uses a brand you are not authorised to sell.'])
            ->assertRedirect(route('admin.product-changes'));

        $this->assertSame('Reef Tee', $product->fresh()->getTranslation('name', 'en'));
        $this->assertNull($product->fresh()->main_image);
        Storage::disk('public')->assertMissing($path);
        $this->assertSame(ProductChangeRequest::REJECTED, $change->fresh()->status);
        $this->assertTrue(AuditLog::where('action', 'product.change_rejected')->exists());
        Notification::assertSentTo($this->shop, ProductChangeReviewed::class, function (ProductChangeReviewed $n) {
            $mail = $n->toMail($this->shop);

            return $n->change->status === ProductChangeRequest::REJECTED && str_contains(implode(' ', $mail->introLines), 'not authorised to sell');
        });

        // Approving it afterwards is refused, and the shop sees why on the form
        $this->post(route('admin.product-changes.approve', $change))->assertSessionHas('error');
        $this->actingAs($this->shop)->get(route('seller.products.edit', $product))->assertOk()->assertSee('not authorised to sell');
    }

    public function test_a_second_edit_replaces_the_waiting_one_and_the_shop_can_withdraw_it(): void
    {
        $product = $this->liveProduct();
        $this->actingAs($this->shop)->put(route('seller.products.update', $product), $this->form($product, ['name_en' => 'First try', 'main_image' => UploadedFile::fake()->image('a.jpg')]));
        $first = ProductChangeRequest::sole();
        [$image] = $first->stagedFiles();

        // The second edit builds on the first (the form shows it): its image stays unless replaced
        $this->put(route('seller.products.update', $product), $this->form($product, ['name_en' => 'Second try', 'price' => 160]));
        $this->assertSame(ProductChangeRequest::WITHDRAWN, $first->fresh()->status);
        $second = ProductChangeRequest::pending()->sole();
        $this->assertSame('Second try', $second->payload['product']['name']['en']);
        $this->assertSame([$image], $second->stagedFiles());
        $this->assertSame($image, $second->payload['main_image']);
        Storage::disk('public')->assertExists($image);

        // A new image replaces the waiting one, whose file is deleted
        $this->put(route('seller.products.update', $product), $this->form($product, ['name_en' => 'Third try', 'main_image' => UploadedFile::fake()->image('b.jpg')]));
        $third = ProductChangeRequest::pending()->sole();
        Storage::disk('public')->assertMissing($image);
        $this->assertNotSame($image, $third->payload['main_image']);

        $this->post(route('seller.products.changes.withdraw', $product))->assertRedirect(route('seller.products.edit', $product))->assertSessionHas('success');
        $this->assertSame(0, ProductChangeRequest::pending()->count());
        Storage::disk('public')->assertMissing($third->payload['main_image']);
        $this->assertSame('Reef Tee', $product->fresh()->getTranslation('name', 'en'));

        // Another shop cannot withdraw it
        $other = $this->staffedShop('Other');
        $this->actingAs($other)->post(route('seller.products.changes.withdraw', $product))->assertForbidden();
    }

    public function test_saving_the_untouched_form_creates_no_request(): void
    {
        $product = $this->liveProduct(['name' => ['en' => 'Reef Tee', 'dv' => 'ރީފް ޓީ'], 'brand' => 'Iruali', 'weight' => 1.5, 'has_variants' => true, 'stock_quantity' => 0]);
        $m = ProductVariant::factory()->for($product)->attributes(['Size' => 'M'])->stock(3)->priced(160)->create(['sku' => 'TEE-1-M']);
        app(\App\Services\MultiBuyService::class)->saveFromForm($product, ['tiers' => [['min_qty' => 2, 'percent' => 5]], 'group' => '']);
        $product->refresh();

        // Every field the form sends, as it renders them for this product
        $this->actingAs($this->shop)->put(route('seller.products.update', $product), $this->form($product, [
            'brand' => 'IRUALI', // the brand list matches regardless of case
            'flash_sale_ends_at' => '',
            'delivery_surcharge' => '0.00',
            'video_url' => '',
            'quote_min_quantity' => '',
            'preorder_form' => 1, 'preorder_enabled' => 0, 'preorder_ship_date' => '', 'preorder_limit' => '', 'preorder_note' => '',
            'multibuy' => ['tiers' => [['min_qty' => 2, 'percent' => '5'], ['min_qty' => '', 'percent' => ''], ['min_qty' => '', 'percent' => '']], 'group' => '', 'group_name' => ''],
            'variants' => [$this->variantRow($m, ['price' => '160.00'])],
        ]))->assertSessionHasNoErrors()->assertSessionHas('success', 'Product updated successfully.');

        $this->assertSame(0, ProductChangeRequest::count());
    }

    public function test_saving_the_live_values_again_withdraws_the_waiting_edit(): void
    {
        $product = $this->liveProduct();
        $this->actingAs($this->shop)->put(route('seller.products.update', $product), $this->form($product, ['price' => 99]));
        $this->assertSame(1, ProductChangeRequest::pending()->count());

        $this->put(route('seller.products.update', $product), $this->form($product))->assertSessionHas('success', 'Product updated successfully.');
        $this->assertSame(0, ProductChangeRequest::pending()->count());
    }

    public function test_edits_to_a_product_that_was_never_approved_apply_directly(): void
    {
        $product = $this->liveProduct(['is_active' => false, 'approved_at' => null]);

        $this->actingAs($this->shop)->put(route('seller.products.update', $product), $this->form($product, ['name_en' => 'Renamed', 'price' => 120]))
            ->assertSessionHas('success', 'Product updated successfully.');

        $this->assertSame(0, ProductChangeRequest::count());
        $this->assertSame('Renamed', $product->fresh()->getTranslation('name', 'en'));
        $this->assertSame(120.0, (float) $product->fresh()->price);
        $this->assertFalse($product->fresh()->is_active, 'it still waits for its first approval');
    }

    public function test_shop_staff_edits_wait_for_review_on_behalf_of_the_shop(): void
    {
        $product = $this->liveProduct();
        $staff = $this->staffMember($this->shop);

        $this->actingAs($staff)->put(route('seller.products.update', $product), $this->form($product, ['name_en' => 'By staff']))->assertSessionHasNoErrors();

        $change = ProductChangeRequest::sole();
        $this->assertSame($this->shop->id, $change->seller_id);
        $this->assertSame($staff->id, $change->submitted_by);
    }

    public function test_a_trusted_shop_publishes_new_products_and_edits_at_once(): void
    {
        $this->shop->forceFill(['auto_approve_listings' => true])->save();

        $this->actingAs($this->shop)->post(route('seller.products.store'), [
            'name_en' => 'Sun Cap', 'sku' => 'CAP-1', 'category_id' => $this->category->id, 'price' => 90, 'stock_quantity' => 5,
        ])->assertSessionHasNoErrors()->assertSessionHas('success', 'Product published. It is live in the shop now.');
        $cap = Product::where('sku', 'CAP-1')->sole();
        $this->assertTrue($cap->is_active);
        $this->assertNotNull($cap->approved_at);
        $audit = AuditLog::where('action', 'product.auto_approved')->sole();
        $this->assertSame($cap->id, $audit->subject_id);
        $this->assertSame($this->shop->id, $audit->user_id, 'the approval is recorded as automatic, by the shop');

        $product = $this->liveProduct();
        $this->put(route('seller.products.update', $product), $this->form($product, ['name_en' => 'Trusted rename', 'price' => 80]))
            ->assertSessionHas('success', 'Product updated successfully.');
        $this->assertSame(0, ProductChangeRequest::count());
        $this->assertSame('Trusted rename', $product->fresh()->getTranslation('name', 'en'));
        $this->assertSame(80.0, (float) $product->fresh()->price);
    }

    public function test_a_shop_that_is_not_trusted_still_waits_for_first_approval(): void
    {
        $this->actingAs($this->shop)->post(route('seller.products.store'), [
            'name_en' => 'Sun Cap', 'sku' => 'CAP-1', 'category_id' => $this->category->id, 'price' => 90, 'stock_quantity' => 5,
        ])->assertSessionHas('success', 'Product submitted. It will be visible in the shop once an admin approves it.');

        $cap = Product::where('sku', 'CAP-1')->sole();
        $this->assertFalse($cap->is_active);
        $this->assertNull($cap->approved_at);
    }

    public function test_a_suspended_trusted_shop_gets_no_auto_approval(): void
    {
        $this->shop->forceFill(['auto_approve_listings' => true, 'status' => 'suspended'])->save();
        $this->assertFalse($this->shop->fresh()->autoApprovesListings());

        $product = $this->liveProduct();
        $review = app(\App\Services\ProductReviewService::class);
        $this->assertTrue($review->needsReview($product->fresh()));

        $draft = Product::factory()->create(['seller_id' => $this->shop->id, 'is_active' => false, 'approved_at' => null]);
        $this->assertFalse($review->publishIfTrusted($draft->fresh()));
        $this->assertFalse($draft->fresh()->is_active);
    }

    public function test_only_admins_can_make_a_shop_trusted_and_it_is_audited(): void
    {
        $admin = $this->staff('admin');

        foreach (['catalogue', 'support', 'finance'] as $role) {
            $this->actingAs($this->staff($role))->post(route('admin.sellers.auto-approve', $this->shop), ['enabled' => 1])->assertForbidden();
        }
        $this->assertFalse((bool) $this->shop->fresh()->auto_approve_listings, 'off for every shop by default');

        $this->actingAs($admin)->get(route('admin.sellers.performance', $this->shop))->assertOk()->assertSee('data-auto-approve="off"', false);
        $this->post(route('admin.sellers.auto-approve', $this->shop), ['enabled' => 1])->assertRedirect()->assertSessionHas('success');
        $shop = $this->shop->fresh();
        $this->assertTrue($shop->auto_approve_listings);
        $this->assertSame($admin->id, (int) $shop->auto_approve_listings_by);
        $this->assertNotNull($shop->auto_approve_listings_at);
        $this->assertTrue(AuditLog::where('action', 'seller.auto_approve_on')->where('subject_id', $shop->id)->where('user_id', $admin->id)->exists());
        $this->get(route('admin.sellers.performance', $shop))->assertSee('data-auto-approve="on"', false);
        $this->get(route('admin.sellers'))->assertOk()->assertSee('data-seller-trusted', false);

        $this->post(route('admin.sellers.auto-approve', $shop), ['enabled' => 0])->assertSessionHas('success');
        $this->assertFalse($shop->fresh()->auto_approve_listings);
        $this->assertTrue(AuditLog::where('action', 'seller.auto_approve_off')->exists());

        // A shop cannot switch it on for itself through its profile form
        $this->actingAs($this->shop)->put(route('seller.profile.update'), ['name' => $this->shop->name, 'email' => $this->shop->email, 'auto_approve_listings' => 1]);
        $this->assertFalse($shop->fresh()->auto_approve_listings);
    }

    public function test_catalogue_staff_review_changes_but_support_cannot(): void
    {
        $product = $this->liveProduct();
        $this->actingAs($this->shop)->put(route('seller.products.update', $product), $this->form($product, ['name_en' => 'Checked by catalogue']));
        $change = ProductChangeRequest::sole();

        $support = $this->staff('support');
        $this->actingAs($support)->get(route('admin.product-changes'))->assertForbidden();
        $this->actingAs($support)->post(route('admin.product-changes.approve', $change))->assertForbidden();

        $catalogue = $this->staff('catalogue');
        $this->actingAs($catalogue)->get(route('admin.product-changes'))->assertOk()->assertSee('Checked by catalogue');
        $this->get(route('admin.product-changes.show', $change))->assertOk();
        $this->post(route('admin.product-changes.approve', $change))->assertRedirect(route('admin.product-changes'));
        $this->assertSame('Checked by catalogue', $product->fresh()->getTranslation('name', 'en'));
        $this->assertSame($catalogue->id, $change->fresh()->reviewed_by);
    }

    public function test_approval_fails_gracefully_when_a_variant_in_the_edit_was_deleted(): void
    {
        $product = $this->liveProduct(['has_variants' => true, 'stock_quantity' => 0]);
        $m = ProductVariant::factory()->for($product)->attributes(['Size' => 'M'])->stock(3)->create(['sku' => 'TEE-1-M']);
        $product->refresh();
        $this->actingAs($this->shop)->put(route('seller.products.update', $product), $this->form($product, [
            'name_en' => 'Renamed', 'variants' => [$this->variantRow($m, ['price' => 170])],
        ]))->assertSessionHasNoErrors();
        $change = ProductChangeRequest::sole();

        $m->delete();

        $this->actingAs($this->staff('admin'))->post(route('admin.product-changes.approve', $change))
            ->assertRedirect(route('admin.product-changes.show', $change))
            ->assertSessionHas('error', fn ($message) => str_contains($message, 'has been deleted'));
        $this->assertSame(ProductChangeRequest::PENDING, $change->fresh()->status);
        $this->assertSame('Reef Tee', $product->fresh()->getTranslation('name', 'en'), 'nothing was applied');
    }

    public function test_bulk_price_changes_to_live_products_wait_for_review_but_stock_and_hiding_apply(): void
    {
        $live = $this->liveProduct();
        $draft = $this->liveProduct(['sku' => 'NEW-1', 'is_active' => false, 'approved_at' => null, 'price' => 100]);

        $this->actingAs($this->shop)->post(route('seller.products.bulk'), ['ids' => [$live->id, $draft->id], 'action' => 'price_percent', 'value' => 10])
            ->assertOk()->assertSee('waits for review');
        $this->post(route('seller.products.bulk.apply'), ['ids' => [$live->id, $draft->id], 'action' => 'price_percent', 'value' => 10])
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'wait for review'));

        $this->assertSame(150.0, (float) $live->fresh()->price, 'shoppers keep the approved price');
        $this->assertSame('165.00', ProductChangeRequest::pending()->where('product_id', $live->id)->sole()->payload['product']['price']);
        $this->assertSame(110.0, (float) $draft->fresh()->price, 'a product never approved changes directly');

        // A second bulk change builds on the waiting one
        $this->post(route('seller.products.bulk.apply'), ['ids' => [$live->id], 'action' => 'price_percent', 'value' => 10]);
        $this->assertSame('181.50', ProductChangeRequest::pending()->sole()->payload['product']['price']);

        $this->post(route('seller.products.bulk.apply'), ['ids' => [$live->id], 'action' => 'stock_set', 'value' => 3]);
        $this->assertSame(3, (int) $live->fresh()->stock_quantity);
        $this->post(route('seller.products.bulk.apply'), ['ids' => [$live->id], 'action' => 'deactivate']);
        $this->assertFalse($live->fresh()->is_active);
        $this->post(route('seller.products.bulk.apply'), ['ids' => [$live->id], 'action' => 'activate']);
        $this->assertTrue($live->fresh()->is_active, 'hiding and showing an approved product needs no review');
        $this->assertSame(1, ProductChangeRequest::pending()->count());
    }

    public function test_csv_import_sends_changes_to_live_products_for_review(): void
    {
        $live = $this->liveProduct(['has_variants' => true, 'stock_quantity' => 0, 'sku' => 'HOOD-1', 'name' => ['en' => 'Hoodie']]);
        $m = ProductVariant::factory()->for($live)->attributes(['Size' => 'M'])->stock(4)->create(['sku' => 'HOOD-1-M']);
        $tee = $this->liveProduct(['sku' => 'TEE-9']);

        $columns = \App\Services\ProductCsvService::COLUMNS;
        $line = fn (array $row) => implode(',', array_map(fn ($c) => '"'.($row[$c] ?? '').'"', $columns));
        $csv = implode(',', $columns)."\n"
            .$line(['sku' => 'TEE-9', 'product_name_en' => 'Tee Renamed', 'price' => '170', 'stock' => '25'])."\n"
            .$line(['sku' => 'HOOD-1-M', 'price' => '310', 'stock' => '8'])."\n"
            .$line(['sku' => 'HOOD-1-XL', 'product_sku' => 'HOOD-1', 'price' => '330', 'stock' => '2', 'variant_attributes' => 'Size=XL'])."\n";

        $response = $this->actingAs($this->shop)->post(route('seller.products.import.preview'), ['file' => UploadedFile::fake()->createWithContent('p.csv', $csv)])
            ->assertOk()->assertSee('wait for review');
        preg_match('/name="token" value="([a-zA-Z0-9]{32})"/', $response->getContent(), $match);
        $this->post(route('seller.products.import.apply'), ['token' => $match[1]])->assertSessionHas('import_result');

        // Stock applied, the rest waits
        $this->assertSame(25, (int) $tee->fresh()->stock_quantity);
        $this->assertSame('Reef Tee', $tee->fresh()->getTranslation('name', 'en'));
        $this->assertSame(150.0, (float) $tee->fresh()->price);
        $teeChange = ProductChangeRequest::pending()->where('product_id', $tee->id)->sole();
        $this->assertSame('Tee Renamed', $teeChange->payload['product']['name']['en']);
        $this->assertSame('170.00', $teeChange->payload['product']['price']);

        $this->assertSame(8, $m->fresh()->stock_quantity);
        $this->assertNull($m->fresh()->price);
        $this->assertNull(ProductVariant::where('sku', 'HOOD-1-XL')->first(), 'the new variant waits for review');
        $hoodChange = ProductChangeRequest::pending()->where('product_id', $live->id)->sole();
        $this->assertSame('310.00', collect($hoodChange->payload['variants'])->firstWhere('sku', 'HOOD-1-M')['price']);
        $this->assertSame('330.00', collect($hoodChange->payload['variants'])->firstWhere('sku', 'HOOD-1-XL')['price']);

        $this->actingAs($this->staff('admin'))->post(route('admin.product-changes.approve', $hoodChange))->assertSessionHas('success');
        $this->assertSame(310.0, (float) $m->fresh()->price);
        $this->assertSame(2, ProductVariant::where('sku', 'HOOD-1-XL')->sole()->stock_quantity);
        $this->assertSame(10, (int) $live->fresh()->stock_quantity);
    }

    public function test_the_admin_inbox_counts_changes_waiting(): void
    {
        $a = $this->liveProduct();
        $b = $this->liveProduct(['sku' => 'TEE-2']);
        $this->actingAs($this->shop)->put(route('seller.products.update', $a), $this->form($a, ['name_en' => 'A2']));
        $this->put(route('seller.products.update', $b), $this->form($b, ['name_en' => 'B2']));
        $this->put(route('seller.products.update', $b), $this->form($b, ['name_en' => 'B3'])); // replaces, still one for b

        $row = collect(AdminInbox::items($this->staff('admin')))->firstWhere('key', 'product_changes');
        $this->assertSame('Product changes waiting for review', $row['label']);
        $this->assertSame(2, $row['count']);
        $this->assertSame(route('admin.product-changes'), $row['url']);
        $this->assertNotNull(collect(AdminInbox::items($this->staff('catalogue')))->firstWhere('key', 'product_changes'));
        $this->assertNull(collect(AdminInbox::items($this->staff('finance')))->firstWhere('key', 'product_changes'));

        // A deleted product's waiting edit is withdrawn
        $this->actingAs($this->shop)->delete(route('seller.products.destroy', $a));
        AdminInbox::reset();
        (new \App\Providers\ProductReviewServiceProvider($this->app))->boot();
        $this->assertSame(1, collect(AdminInbox::items($this->staff('admin')))->firstWhere('key', 'product_changes')['count']);
    }

    public function test_a_variant_with_orders_removed_in_an_approved_edit_is_deactivated(): void
    {
        $product = $this->liveProduct(['has_variants' => true, 'stock_quantity' => 0]);
        $m = ProductVariant::factory()->for($product)->attributes(['Size' => 'M'])->stock(3)->create(['sku' => 'TEE-1-M']);
        $l = ProductVariant::factory()->for($product)->attributes(['Size' => 'L'])->stock(2)->create(['sku' => 'TEE-1-L']);
        Order::factory()->create()->items()->create(['product_id' => $product->id, 'product_variant_id' => $l->id, 'quantity' => 1, 'price' => 150]);
        $product->refresh();

        $this->actingAs($this->shop)->put(route('seller.products.update', $product), $this->form($product, ['variants' => [$this->variantRow($m)]]))->assertSessionHasNoErrors();
        $this->actingAs($this->staff('admin'))->post(route('admin.product-changes.approve', ProductChangeRequest::sole()))->assertSessionHas('success');

        $this->assertFalse($l->fresh()->is_active);
        $this->assertSame(3, (int) $product->fresh()->stock_quantity);
    }
}
