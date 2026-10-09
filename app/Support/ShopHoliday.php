<?php

namespace App\Support;

use App\Models\Product;
use App\Models\User;

/**
 * What customers are told about a shop on holiday (User::isOnHoliday()), so the product page, the
 * cart, checkout and the API say the same thing. The back-on date is the first day the shop takes
 * orders again: "on holiday until 15 Oct" means it can be ordered from on 15 Oct.
 */
class ShopHoliday
{
    /** "15 Oct" (Dhivehi month names in Dhivehi), or '' when no back-on date was given. */
    public static function date(User $shop): string
    {
        return $shop->holiday_until?->translatedFormat('j M') ?? '';
    }

    /** "This shop is on holiday until 15 Oct", or "for now" without a back-on date. */
    public static function headline(User $shop): string
    {
        return $shop->holiday_until
            ? __('This shop is on holiday until :date', ['date' => self::date($shop)])
            : __('This shop is on holiday for now');
    }

    /** Why adding to the cart was refused. */
    public static function addToCartMessage(Product $product): string
    {
        $shop = $product->seller;
        $name = $shop ? $shop->shopName() : __('This shop');

        return $shop?->holiday_until
            ? __(':shop is on holiday until :date, so it is not taking orders. Add it to your wishlist to buy it when the shop is back.', ['shop' => $name, 'date' => self::date($shop)])
            : __(':shop is on holiday, so it is not taking orders right now. Add it to your wishlist to buy it when the shop is back.', ['shop' => $name]);
    }

    /** A cart line that can't be ordered while its shop is away (cart, checkout and placing the order). */
    public static function cartMessage(Product $product): string
    {
        $shop = $product->seller;
        $name = $shop ? $shop->shopName() : __('This shop');

        return $shop?->holiday_until
            ? __('":product" can\'t be ordered until :date: :shop is on holiday. It stays in your cart.', ['product' => $product->name, 'shop' => $name, 'date' => self::date($shop)])
            : __('":product" can\'t be ordered right now: :shop is on holiday. It stays in your cart.', ['product' => $product->name, 'shop' => $name]);
    }
}
