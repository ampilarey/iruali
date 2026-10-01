<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Throwable;

/**
 * Smaller WebP copies of uploaded product photos: a 400 px one for cards and a 1200 px one for the
 * product page, stored next to the original as <name>-400.webp and <name>-1200.webp. The original
 * is kept untouched. If a variant is missing (old upload, GD hiccup) the original is used.
 */
class ImageVariants
{
    public const WIDTHS = [400, 1200];

    public const DISK = 'public';

    /** @var array<string, bool> exists() results for this request */
    protected static array $seen = [];

    public static function make(string $path): void
    {
        $disk = Storage::disk(self::DISK);
        if (! $disk->exists($path) || ! preg_match('/\.(jpe?g|png|gif|webp)$/i', $path)) {
            return;
        }

        try {
            $manager = ImageManager::gd();
            $source = $disk->path($path);
            foreach (self::WIDTHS as $width) {
                $disk->put(self::variantPath($path, $width), (string) $manager->read($source)->scaleDown(width: $width)->toWebp(82));
                self::$seen[self::variantPath($path, $width)] = true;
            }
        } catch (Throwable $e) {
            report($e); // the original still works; the page just loads a bigger file
        }
    }

    public static function delete(string $path): void
    {
        foreach (self::WIDTHS as $width) {
            Storage::disk(self::DISK)->delete(self::variantPath($path, $width));
            unset(self::$seen[self::variantPath($path, $width)]);
        }
    }

    public static function variantPath(string $path, int $width): string
    {
        return preg_replace('/\.(jpe?g|png|gif|webp)$/i', "-{$width}.webp", $path);
    }

    /**
     * The URL to use for a stored image at the given width: the variant when it exists, else the original.
     */
    public static function url(string $originalUrl, int $width): string
    {
        $prefix = Storage::disk(self::DISK)->url('');
        if (! str_starts_with($originalUrl, $prefix)) {
            return $originalUrl; // not one of our uploads (seeded placeholder, external URL)
        }

        $path = substr($originalUrl, strlen($prefix));
        $variant = self::variantPath($path, $width);
        if ($variant === $path) {
            return $originalUrl;
        }

        self::$seen[$variant] ??= Storage::disk(self::DISK)->exists($variant);

        return self::$seen[$variant] ? $prefix.$variant : $originalUrl;
    }

    public static function forget(): void
    {
        self::$seen = [];
    }
}
