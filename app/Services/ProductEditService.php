<?php

namespace App\Services;

use App\Http\Requests\Seller\ProductRequest;
use App\Models\Product;
use App\Traits\SecureFileUpload;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Writes a shop's product form to a product: its columns, the video, pre-order and quote fields, the
 * main image, the variants (VariantService::sync) and the multi-buy offer (MultiBuyService).
 * Seller\ProductController's store and update use it, and so does approving an edit that waited
 * for review (ProductReviewService), so all three save a product the same way.
 *
 * The form, as formFromRequest() reads it:
 *   product    fillable columns (Seller\ProductRequest::productAttributes())
 *   extra      video, pre-order and quote columns, forceFilled (only those the form sent)
 *   variants   variant rows for VariantService::sync() (empty when variants are off)
 *   multibuy   the multi-buy fieldset, or null when the form did not have it
 *   main_image an upload, the path of a file already stored, or null to keep the image
 *
 * @phpstan-type Form array{product: array<string, mixed>, extra: array<string, mixed>, variants: list<array<string, mixed>>, multibuy: array<string, mixed>|null, main_image: UploadedFile|string|null, launch?: array<string, mixed>}
 */
class ProductEditService
{
    use SecureFileUpload;

    public function __construct(protected VariantService $variants, protected MultiBuyService $multibuy) {}

    /**
     * @return Form
     */
    public function formFromRequest(ProductRequest $request): array
    {
        $file = $request->file('main_image');

        return [
            'product' => $request->productAttributes(),
            'extra' => $request->videoAttributes() + $request->preorderAttributes() + $request->quoteAttributes(),
            'variants' => array_values($request->variantRows()),
            'multibuy' => $request->has('multibuy') ? (array) ($request->validated()['multibuy'] ?? []) : null,
            'main_image' => $file instanceof UploadedFile ? $file : null,
            'launch' => $request->comingSoonAttributes(), // "coming soon": applied at once, never reviewed
        ];
    }

    /**
     * Save the form to the product (a new one is created). The caller sets seller_id and
     * is_active on a new product first.
     *
     * @param  Form  $form
     */
    public function apply(Product $product, array $form): Product
    {
        $product->fill($form['product']);
        if (! $product->exists && $product->has_variants) {
            $product->stock_quantity = 0; // set from the variants below
        }
        $product->forceFill($form['extra']); // video link, pre-orders (a new date emails waiting customers), quote minimum
        $product->forceFill($form['launch'] ?? []); // "coming soon" and its launch day
        // Only for a shop an admin allowed them: an edit approved after the shop lost them keeps them off
        if ($product->seller_id && $product->preorder_enabled && $product->isDirty('preorder_enabled') && ! $product->seller?->allowsPreorders()) {
            $product->forceFill(['preorder_enabled' => false]);
        }
        if ($product->seller_id && $product->coming_soon && $product->isDirty('coming_soon') && ! $product->seller?->allowsComingSoon()) {
            $product->forceFill(['coming_soon' => false, 'available_on' => null]);
        }
        $product->save();

        $this->storeImage($product, $form['main_image']);
        $this->variants->sync($product, $form['variants']);
        if ($form['multibuy'] !== null) {
            $this->multibuy->saveFromForm($product, $form['multibuy']);
        }

        return $product;
    }

    /**
     * The main image: a new upload, or a file stored earlier (an edit that waited for review).
     * The old file and its image row are replaced.
     */
    public function storeImage(Product $product, UploadedFile|string|null $image): void
    {
        $path = $image instanceof UploadedFile ? $this->storeFileSecurely($image, 'products') : $image;
        if (! $path || $path === $product->main_image) {
            return;
        }

        if ($product->main_image) {
            $this->deleteFile($product->main_image);
        }

        $product->forceFill(['main_image' => $path])->save();

        $product->images()->where('is_main', true)->delete();
        $product->images()->create([
            'url' => Storage::url($path),
            'alt_text' => $product->getTranslation('name', 'en', false),
            'is_main' => true,
            'sort_order' => 0,
        ]);
    }

    /** Store an upload for an edit waiting for review; null when it is not a usable image. */
    public function stageUpload(UploadedFile $file, string $directory): ?string
    {
        return $this->storeFileSecurely($file, $directory);
    }

    /** Delete a file an edit waiting for review had stored (and its WebP copies). */
    public function deleteStaged(string $path): void
    {
        $this->deleteFile($path);
    }
}
