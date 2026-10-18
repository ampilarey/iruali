<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Str;

/**
 * The home page's blocks and their order (Admin → Home page): the built-in sections, which can be
 * moved or hidden, plus product rows (a category, a brand or picked products) and text blocks
 * added by staff. Changes are made to a draft, previewed, then published.
 */
class HomeLayout
{
    /** Built-in sections: type => label */
    public const BUILT_IN = [
        'hero' => 'Hero and side promos',
        'campaign_strips' => 'Campaign strips',
        'departments' => 'Shop by department',
        'deals' => "Today's deals",
        'featured' => 'Featured products',
        'local_shops' => 'Shops on the shopper\'s island',
        'shops' => 'Shops on iruali',
        'brands' => 'Shop by brand',
        'new_arrivals' => 'New arrivals',
        'preorders' => 'Pre-order now',
        'coming_soon' => 'Coming soon',
        'all_products' => 'View all products button',
        'sell_cta' => 'Sell on iruali',
    ];

    /** Blocks staff add: type => label */
    public const CUSTOM = ['product_row' => 'Product row', 'text' => 'Text with a button'];

    /**
     * @return list<array<string, mixed>>
     */
    public static function defaults(): array
    {
        return array_map(fn ($type) => ['id' => $type, 'type' => $type, 'visible' => true], array_keys(self::BUILT_IN));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function published(): array
    {
        return self::decode(Setting::get('home_layout_published')) ?? self::defaults();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function draft(): array
    {
        return self::decode(Setting::get('home_layout_draft')) ?? self::published();
    }

    public static function hasDraft(): bool
    {
        return self::decode(Setting::get('home_layout_draft')) !== null;
    }

    /**
     * @param  list<array<string, mixed>>  $blocks
     */
    public static function saveDraft(array $blocks): void
    {
        Setting::set(['home_layout_draft' => json_encode(array_values($blocks))]);
    }

    public static function publish(): void
    {
        Setting::set(['home_layout_published' => json_encode(self::draft()), 'home_layout_draft' => '']);
    }

    public static function discard(): void
    {
        Setting::set(['home_layout_draft' => '']);
    }

    /**
     * The visible blocks to show, in order.
     *
     * @param  list<array<string, mixed>>  $blocks
     * @return list<array<string, mixed>>
     */
    public static function visible(array $blocks): array
    {
        return array_values(array_filter($blocks, fn ($b) => ($b['visible'] ?? true)
            && (isset(self::BUILT_IN[$b['type'] ?? '']) || isset(self::CUSTOM[$b['type'] ?? '']))));
    }

    public static function newId(): string
    {
        return 'b'.Str::lower(Str::random(8));
    }

    /**
     * The block's title in the current language.
     *
     * @param  array<string, mixed>  $block
     */
    public static function title(array $block): string
    {
        $dv = app()->getLocale() === 'dv' ? trim((string) ($block['title_dv'] ?? '')) : '';

        return $dv !== '' ? $dv : (string) ($block['title_en'] ?? '');
    }

    /**
     * @param  array<string, mixed>  $block
     */
    public static function text(array $block): string
    {
        $dv = app()->getLocale() === 'dv' ? trim((string) ($block['text_dv'] ?? '')) : '';

        return $dv !== '' ? $dv : (string) ($block['text_en'] ?? '');
    }

    /**
     * @return list<array<string, mixed>>|null
     */
    protected static function decode(mixed $value): ?array
    {
        $blocks = is_string($value) && $value !== '' ? json_decode($value, true) : null;

        return is_array($blocks) && $blocks !== [] ? array_values($blocks) : null;
    }
}
