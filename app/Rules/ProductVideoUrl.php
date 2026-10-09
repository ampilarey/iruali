<?php

namespace App\Rules;

use App\Support\ProductVideo;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * The product form's video link: a YouTube (or Shorts), TikTok, Instagram (post or reel) or
 * Facebook video link that App\Support\ProductVideo can read.
 */
class ProductVideoUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ProductVideo::parse($value) !== null) {
            return; // empty values are left to "nullable"
        }

        $fail(ProductVideo::isShortLink($value)
            ? __('This is a short share link. Open the video and copy the full link from the address bar (or "Copy link" on a computer).')
            : __('Use a link to a video on YouTube, TikTok, Instagram or Facebook.'));
    }
}
