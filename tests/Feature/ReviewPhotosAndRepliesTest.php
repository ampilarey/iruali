<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\ReviewPhoto;
use App\Models\Role;
use App\Models\User;
use App\Notifications\ReviewReplied;
use App\Support\ImageVariants;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Review photos, the verified-purchase badge, shop replies and the review list filters.
 */
class ReviewPhotosAndRepliesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        ImageVariants::forget();
        Notification::fake();
    }

    protected function userWithRole(string $role, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->roles()->attach(Role::firstOrCreate(['name' => $role], ['display_name' => ucfirst($role)])->id);

        return $user;
    }

    protected function seller(): User
    {
        return $this->userWithRole('seller', ['is_seller' => true, 'seller_approved' => true, 'business_name' => 'Reef Crafts']);
    }

    protected function review(Product $product, User $user, array $attributes = []): ProductReview
    {
        return ProductReview::create(array_merge([
            'product_id' => $product->id, 'user_id' => $user->id, 'reviewer_name' => $user->name, 'reviewer_email' => $user->email,
            'rating' => 4, 'comment' => 'Nice and sturdy, arrived on time', 'is_approved' => true,
        ], $attributes));
    }

    public function test_review_photos_are_stored_with_webp_copies_and_deleted_with_the_review(): void
    {
        $product = Product::factory()->create(['is_active' => true]);
        $buyer = User::factory()->create();

        $this->actingAs($buyer)->post(route('reviews.store', $product), [
            'rating' => 5, 'comment' => 'Lovely weave and quick delivery',
            'photos' => [UploadedFile::fake()->image('a.jpg', 900, 700), UploadedFile::fake()->image('b.png', 500, 500)],
        ])->assertRedirect();

        $review = ProductReview::where('product_id', $product->id)->sole();
        $this->assertCount(2, $review->photos);
        foreach ($review->photos as $photo) {
            $this->assertStringStartsWith('reviews/', $photo->path);
            Storage::disk('public')->assertExists($photo->path);
            Storage::disk('public')->assertExists(ImageVariants::variantPath($photo->path, 400));
            $this->assertStringEndsWith('-400.webp', $photo->variant(400));
        }

        $this->get(route('products.show', $product))->assertOk()->assertSee('data-review-photo', false)->assertSee('With photos (1)');

        // Editing the review can drop a photo and add another; never more than three in total
        $first = $review->photos->first();
        $this->actingAs($buyer)->post(route('reviews.store', $product), [
            'rating' => 4, 'comment' => 'Still lovely a month later',
            'remove_photos' => [$first->id],
            'photos' => [UploadedFile::fake()->image('c.webp')],
        ])->assertRedirect();
        Storage::disk('public')->assertMissing($first->path);
        Storage::disk('public')->assertMissing(ImageVariants::variantPath($first->path, 400));
        $this->assertCount(2, $review->fresh()->photos);

        $this->actingAs($buyer)->from(route('products.show', $product))->post(route('reviews.store', $product), [
            'rating' => 4, 'comment' => 'Still lovely a month later',
            'photos' => [UploadedFile::fake()->image('d.jpg'), UploadedFile::fake()->image('e.jpg')],
        ])->assertSessionHasErrors('photos');
        $this->assertCount(2, $review->fresh()->photos);

        $this->actingAs($buyer)->post(route('reviews.store', $product), [
            'rating' => 4, 'comment' => 'Still lovely a month later',
            'photos' => [UploadedFile::fake()->create('big.jpg', 5000, 'image/jpeg')],
        ])->assertSessionHasErrors('photos.0');
        $this->actingAs($buyer)->post(route('reviews.store', $product), [
            'rating' => 4, 'comment' => 'Still lovely a month later',
            'photos' => [UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf')],
        ])->assertSessionHasErrors('photos.0');

        // Deleting the review (admin moderation) removes the files
        $paths = $review->fresh()->photos->pluck('path');
        $admin = $this->userWithRole('admin');
        $this->actingAs($admin)->delete(route('admin.reviews.destroy', $review))->assertRedirect();
        $this->assertSame(0, ReviewPhoto::count());
        foreach ($paths as $path) {
            Storage::disk('public')->assertMissing($path);
            Storage::disk('public')->assertMissing(ImageVariants::variantPath($path, 1200));
        }
    }

    public function test_verified_purchase_needs_a_delivered_order_with_the_product(): void
    {
        $product = Product::factory()->create(['is_active' => true]);
        $buyer = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $buyer->id, 'status' => 'processing']);
        $order->items()->create(['product_id' => $product->id, 'quantity' => 1, 'price' => 100]);

        $this->actingAs($buyer)->post(route('reviews.store', $product), ['rating' => 5, 'comment' => 'Looks great so far, waiting']);
        $this->assertFalse(ProductReview::sole()->verified_purchase);
        $this->get(route('products.show', $product))->assertDontSee('Verified purchase');

        $order->update(['status' => 'delivered']);
        $this->actingAs($buyer)->post(route('reviews.store', $product), ['rating' => 5, 'comment' => 'Looks great, delivered fast']);
        $this->assertTrue(ProductReview::sole()->verified_purchase);
        $this->get(route('products.show', $product))->assertSee('Verified purchase');

        // Someone whose only order with it was cancelled is not verified
        $other = User::factory()->create();
        Order::factory()->create(['user_id' => $other->id, 'status' => 'cancelled'])->items()->create(['product_id' => $product->id, 'quantity' => 1, 'price' => 100]);
        $this->actingAs($other)->post(route('reviews.store', $product), ['rating' => 2, 'comment' => 'Never got it, cancelled it']);
        $this->assertFalse(ProductReview::where('user_id', $other->id)->sole()->verified_purchase);
    }

    public function test_only_the_products_shop_can_reply_and_the_customer_is_told_once(): void
    {
        $seller = $this->seller();
        $otherSeller = $this->seller();
        $product = Product::factory()->create(['is_active' => true, 'seller_id' => $seller->id]);
        $buyer = User::factory()->create();
        $review = $this->review($product, $buyer);

        $this->post(route('seller.reviews.reply', $review), ['seller_reply' => 'Thanks!'])->assertRedirect('/login');
        $this->actingAs($buyer)->post(route('seller.reviews.reply', $review), ['seller_reply' => 'Thanks!'])->assertForbidden();
        $this->actingAs($otherSeller)->post(route('seller.reviews.reply', $review), ['seller_reply' => 'Thanks!'])->assertForbidden();
        $this->assertNull($review->fresh()->seller_reply);

        $this->actingAs($seller)->get(route('seller.reviews'))->assertOk()->assertSee('Nice and sturdy')->assertSee('Waiting for a reply (1)');

        $this->actingAs($seller)->post(route('seller.reviews.reply', $review), ['seller_reply' => 'Thank you! Glad it arrived safely.'])->assertRedirect();
        $review->refresh();
        $this->assertSame('Thank you! Glad it arrived safely.', $review->seller_reply);
        $this->assertNotNull($review->seller_replied_at);
        $this->assertSame($seller->id, $review->seller_reply_user_id);
        Notification::assertSentTo($buyer, ReviewReplied::class, fn ($n) => $n->review->is($review));

        $this->get(route('products.show', $product))->assertOk()
            ->assertSee('Reply from Reef Crafts')
            ->assertSee('Thank you! Glad it arrived safely.');

        // Editing keeps the original reply date and does not email again
        $this->actingAs($seller)->post(route('seller.reviews.reply', $review), ['seller_reply' => 'Thank you again!'])->assertRedirect();
        $this->assertSame('Thank you again!', $review->fresh()->seller_reply);
        Notification::assertSentToTimes($buyer, ReviewReplied::class, 1);

        $this->actingAs($seller)->get(route('seller.reviews'))->assertOk()->assertSee('Waiting for a reply (0)');
        $this->actingAs($seller)->get(route('seller.reviews', ['show' => 'all']))->assertOk()->assertSee('Thank you again!');

        // Admin moderation can take the reply down; the review stays
        $admin = $this->userWithRole('admin');
        $this->actingAs($buyer)->delete(route('admin.reviews.reply.destroy', $review))->assertForbidden();
        $this->actingAs($admin)->get(route('admin.reviews'))->assertOk()->assertSee('Remove reply');
        $this->actingAs($admin)->delete(route('admin.reviews.reply.destroy', $review))->assertRedirect();
        $this->assertNull($review->fresh()->seller_reply);
        $this->assertNull($review->fresh()->seller_replied_at);
        $this->assertSame('Nice and sturdy, arrived on time', $review->fresh()->comment);
    }

    public function test_review_list_can_be_filtered_to_photos_and_sorted(): void
    {
        $product = Product::factory()->create(['is_active' => true]);
        $low = $this->review($product, User::factory()->create(), ['rating' => 1, 'comment' => 'Broke within a week sadly']);
        $high = $this->review($product, User::factory()->create(), ['rating' => 5, 'comment' => 'Perfect, exactly as pictured']);
        $mid = $this->review($product, User::factory()->create(), ['rating' => 3, 'comment' => 'Decent for the price paid']);
        Storage::disk('public')->put('reviews/x.jpg', 'x');
        $mid->photos()->create(['path' => 'reviews/x.jpg', 'sort_order' => 1]);

        $this->get(route('products.show', $product))->assertOk()
            ->assertSee('Broke within a week')->assertSee('Perfect, exactly')->assertSee('Decent for the price');

        $this->get(route('products.show', ['product' => $product, 'photos' => 1]))->assertOk()
            ->assertSee('Decent for the price')
            ->assertDontSee('Broke within a week')
            ->assertDontSee('Perfect, exactly')
            ->assertSee('3 star'); // the breakdown still covers every review

        $this->get(route('products.show', ['product' => $product, 'sort' => 'lowest']))->assertOk()
            ->assertSeeInOrder(['Broke within a week', 'Decent for the price', 'Perfect, exactly']);
        $this->get(route('products.show', ['product' => $product, 'sort' => 'highest']))->assertOk()
            ->assertSeeInOrder(['Perfect, exactly', 'Decent for the price', 'Broke within a week']);
        $this->get(route('products.show', ['product' => $product, 'sort' => 'newest']))->assertOk()
            ->assertSeeInOrder(['Decent for the price', 'Perfect, exactly', 'Broke within a week']);
    }
}
