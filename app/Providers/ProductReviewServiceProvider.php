<?php

namespace App\Providers;

use App\Models\Product;
use App\Models\ProductChangeRequest;
use App\Services\ProductReviewService;
use App\Support\AdminInbox;
use Illuminate\Support\ServiceProvider;

/**
 * Product edits waiting for review (App\Services\ProductReviewService): the Admin → Inbox row, and
 * a deleted product's waiting edit is withdrawn (its uploaded files deleted). Routes are in
 * routes/web/product-review.php.
 */
class ProductReviewServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        AdminInbox::register('product_changes', fn () => [
            'label' => 'Product changes waiting for review',
            'count' => ProductChangeRequest::pending()->whereHas('product')->count(),
            'route' => 'admin.product-changes',
            'severity' => 'info',
        ]);

        Product::deleting(function (Product $product) {
            $product->changeRequests()->pending()->get()->each(fn (ProductChangeRequest $change) => app(ProductReviewService::class)->withdraw($change));
        });
    }
}
