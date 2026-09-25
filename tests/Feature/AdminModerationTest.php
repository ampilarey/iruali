<?php

namespace Tests\Feature;

use App\Models\NewsletterSubscriber;
use App\Models\Product;
use App\Models\ProductQuestion;
use App\Models\ProductReview;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminModerationTest extends TestCase
{
    use RefreshDatabase;

    protected function admin(): User
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin'])->id);

        return $admin;
    }

    public function test_only_admins_can_moderate(): void
    {
        $review = ProductReview::create(['product_id' => Product::factory()->create()->id, 'reviewer_name' => 'A', 'reviewer_email' => 'a@example.com', 'rating' => 1, 'comment' => 'Spam spam spam', 'is_approved' => true]);

        $this->actingAs(User::factory()->create());
        $this->get(route('admin.reviews'))->assertForbidden();
        $this->post(route('admin.reviews.toggle', $review))->assertForbidden();
        $this->get(route('admin.newsletter'))->assertForbidden();
    }

    public function test_hidden_reviews_leave_the_shop_and_stay_hidden_when_edited(): void
    {
        $product = Product::factory()->create(['is_active' => true]);
        $author = User::factory()->create();
        $this->actingAs($author)->post(route('reviews.store', $product), ['rating' => 1, 'comment' => 'Buy from my other shop instead']);
        $review = ProductReview::sole();

        $this->actingAs($this->admin())->get(route('admin.reviews', ['show' => 'low']))->assertOk()->assertSee('Buy from my other shop instead');
        $this->post(route('admin.reviews.toggle', $review))->assertRedirect();
        $this->assertFalse($review->fresh()->is_approved);

        $this->get(route('products.show', $product))->assertDontSee('Buy from my other shop instead');

        $this->actingAs($author)->post(route('reviews.store', $product), ['rating' => 1, 'comment' => 'Edited but still spam text']);
        $this->assertFalse($review->fresh()->is_approved);

        $this->actingAs($this->admin())->delete(route('admin.reviews.destroy', $review))->assertRedirect();
        $this->assertModelMissing($review);
    }

    public function test_admin_can_answer_and_delete_questions(): void
    {
        $product = Product::factory()->create(['is_active' => true]);
        $question = ProductQuestion::create(['product_id' => $product->id, 'question' => 'Is it waterproof?']);
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.questions'))->assertOk()->assertSee('Is it waterproof?');
        $this->post(route('questions.answer', $question), ['answer' => 'Yes, fully.'])->assertRedirect();
        $this->get(route('products.show', $product))->assertSee('Yes, fully.');

        $this->delete(route('admin.questions.destroy', $question))->assertRedirect();
        $this->assertModelMissing($question);
    }

    public function test_newsletter_list_and_csv_export(): void
    {
        NewsletterSubscriber::create(['email' => 'fan@example.com', 'locale' => 'dv']);

        $this->actingAs($this->admin());
        $this->get(route('admin.newsletter'))->assertOk()->assertSee('fan@example.com')->assertSee('Dhivehi');

        $csv = $this->get(route('admin.newsletter', ['export' => 'csv']));
        $csv->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('fan@example.com,dv', $csv->streamedContent());
    }
}
