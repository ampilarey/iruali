<?php

namespace App\Console\Commands;

use App\Models\ProductImage;
use App\Support\ImageVariants;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Build the 400/1200 px WebP copies for product photos uploaded before variants existed.
 */
class MakeImageVariants extends Command
{
    protected $signature = 'images:variants {--force : Rebuild even when a variant already exists}';

    protected $description = 'Create the smaller WebP copies of uploaded product photos';

    public function handle(): int
    {
        $prefix = Storage::disk(ImageVariants::DISK)->url('');
        $made = 0;

        ProductImage::query()->where('url', 'like', $prefix.'%')->orderBy('id')->lazy()->each(function (ProductImage $image) use ($prefix, &$made) {
            $path = substr($image->url, strlen($prefix));
            if (! $this->option('force') && Storage::disk(ImageVariants::DISK)->exists(ImageVariants::variantPath($path, 400))) {
                return;
            }
            ImageVariants::make($path);
            $made++;
        });

        $this->info("Variants made for {$made} image(s).");

        return self::SUCCESS;
    }
}
