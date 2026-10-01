<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\SellerOnboarded;
use Throwable;

/**
 * The shop onboarding checklist. Items are worked out live from the shop's profile, bank account and
 * products; the moment every item is done the shop is marked onboarded and iruali is told. Until then
 * the shop's products cannot be activated, so nothing of theirs shows on the storefront.
 */
class OnboardingService
{
    public const ABOUT_MIN_LENGTH = 40;

    /**
     * @return array<int, array{key: string, label: string, hint: string, done: bool, url: string}>
     */
    public function items(User $seller): array
    {
        $seller->loadMissing('bankAccount');

        return [
            ['key' => 'logo', 'label' => __('Shop logo'), 'hint' => __('A square image customers see next to your shop name.'), 'done' => filled($seller->shop_logo), 'url' => route('seller.profile').'#branding'],
            ['key' => 'banner', 'label' => __('Shop banner'), 'hint' => __('A wide image across the top of your shop page.'), 'done' => filled($seller->shop_banner), 'url' => route('seller.profile').'#branding'],
            ['key' => 'about', 'label' => __('About your shop'), 'hint' => __('At least :min characters about what you sell and where you are.', ['min' => self::ABOUT_MIN_LENGTH]), 'done' => mb_strlen(trim((string) $seller->business_description)) >= self::ABOUT_MIN_LENGTH, 'url' => route('seller.profile')],
            ['key' => 'phone', 'label' => __('Phone number'), 'hint' => __('So iruali and customers can reach you about orders.'), 'done' => filled($seller->phone), 'url' => route('seller.profile')],
            ['key' => 'delivery', 'label' => __('Delivery options'), 'hint' => __('How you send orders and whether you ship to other islands.'), 'done' => filled($seller->delivery_notes), 'url' => route('seller.profile').'#delivery'],
            ['key' => 'bank', 'label' => __('Bank account'), 'hint' => __('Where iruali pays your earnings.'), 'done' => $seller->bankAccount !== null, 'url' => route('seller.settings.bank')],
            ['key' => 'product', 'label' => __('First product'), 'hint' => __('Add at least one product to your shop.'), 'done' => $seller->products()->exists(), 'url' => route('seller.products.create')],
        ];
    }

    public function isComplete(User $seller): bool
    {
        return collect($this->items($seller))->every(fn ($item) => $item['done']);
    }

    /**
     * Products of a shop still on its checklist cannot be activated.
     */
    public function blocksActivation(?User $seller): bool
    {
        return $seller !== null && $seller->is_seller && $seller->onboarding_completed_at === null;
    }

    /**
     * Mark the shop onboarded the first time every item is done, and tell iruali's admins.
     */
    public function refresh(User $seller): bool
    {
        if ($seller->onboarding_completed_at !== null || ! $this->isComplete($seller)) {
            return false;
        }

        $seller->forceFill(['onboarding_completed_at' => now()])->save();
        $this->notifyAdmins($seller);

        return true;
    }

    protected function notifyAdmins(User $seller): void
    {
        $admins = User::whereHas('roles', fn ($q) => $q->where('name', 'admin'))->whereNotNull('email')->get();

        foreach ($admins as $admin) {
            try {
                $admin->notify(new SellerOnboarded($seller));
            } catch (Throwable $e) {
                report($e);
            }
        }
    }
}
