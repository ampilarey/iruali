<?php

namespace App\Support;

use Illuminate\Support\Facades\Route;

/**
 * The admin area's menu, grouped (layouts/admin: the sidebar on a computer, the menu drawer on a
 * phone). Each staff member sees only the pages their role may open (StaffAccess, config/staff.php).
 * An item is the current one on its own page and on the pages under it ("admin.orders" covers
 * admin.orders.show), plus any extra route patterns it lists.
 */
class AdminNav
{
    /**
     * [group label (null: no heading) => list of [route, label, icon, extra active patterns]].
     *
     * @return array<string, list<array{0: string, 1: string, 2: string, 3?: list<string>}>>
     */
    public static function groups(): array
    {
        return [
            '' => [
                ['admin.dashboard', 'Dashboard', 'home'],
                ['admin.inbox', 'Inbox', 'bell'],
            ],
            'Sales' => [
                ['admin.orders', 'Orders', 'box'],
                ['admin.returns', 'Returns', 'return'],
                ['admin.disputes', 'Disputes', 'shield'],
                ['admin.complaints', 'Complaints', 'help'],
                ['admin.preorders', 'Pre-orders', 'clock'],
                ['admin.quotes', 'Bulk quotes', 'list'],
                ['admin.messages', 'Messages', 'chat'],
            ],
            'Money' => [
                ['admin.payouts', 'Shop payouts', 'bank'],
                ['admin.payout-batches.index', 'Payout batches', 'bank'],
                ['admin.tax', 'Tax (GST)', 'badge'],
                ['admin.finance', 'Finance', 'columns'],
                ['admin.settlements', 'Settlements', 'bank'],
                ['admin.exports', 'Exports', 'list'],
                ['admin.gift-cards', 'Gift cards', 'gift'],
            ],
            'Shops & products' => [
                ['admin.sellers', 'Sellers', 'store'],
                ['admin.seller-applications', 'Shop applications', 'store'],
                ['admin.verifications', 'Verifications', 'check'],
                ['admin.products', 'Products', 'grid'],
                ['admin.product-changes', 'Product changes', 'repeat'],
                ['admin.brands', 'Brands', 'tag'],
                ['admin.reviews', 'Moderation', 'thumb'],
                ['admin.questions', 'Questions', 'help'],
            ],
            'Customers & marketing' => [
                ['admin.users', 'Users', 'user', ['admin.customers.*']],
                ['admin.customer-report', 'Customer report', 'columns'],
                ['admin.campaigns.index', 'Campaigns', 'squares'],
                ['admin.vouchers.index', 'Vouchers', 'tag'],
                ['admin.newsletter', 'Newsletter', 'mail', ['admin.newsletter-issues.*']],
                ['admin.sms.campaigns', 'SMS campaigns', 'phone'],
                ['admin.social', 'Social posts', 'share'],
                ['admin.home-page', 'Home page', 'home'],
                ['admin.rewards', 'Rewards', 'heart'],
            ],
            'Settings' => [
                ['admin.settings', 'Settings', 'filter'],
                ['admin.delivery', 'Delivery', 'truck'],
                ['admin.islands', 'Islands', 'map-pin'],
                ['admin.legal', 'Legal pages', 'list'],
                ['admin.sms', 'SMS', 'phone'],
                ['admin.alerts', 'Team alerts', 'bell'],
                ['admin.webhooks', 'Webhooks', 'repeat'],
                ['admin.sample-data', 'Sample data', 'squares'],
            ],
            'System' => [
                ['admin.analytics', 'Analytics', 'columns'],
                ['admin.errors', 'Errors', 'shield'],
                ['admin.audit', 'Audit log', 'list'],
            ],
        ];
    }

    /** The phone's bottom bar: the pages used most (the menu holds the rest). */
    public const BOTTOM_BAR = ['admin.dashboard', 'admin.inbox', 'admin.orders', 'admin.products'];

    /**
     * The groups with only the items this staff member may open (empty groups dropped), each item
     * as ['route', 'label', 'icon', 'url', 'active'].
     *
     * @return array<string, list<array{route: string, label: string, icon: string, url: string, active: bool}>>
     */
    public static function visible(): array
    {
        $groups = [];
        foreach (self::groups() as $heading => $items) {
            foreach ($items as $item) {
                [$route, $label, $icon] = $item;
                if (! Route::has($route) || ! StaffAccess::can($route)) {
                    continue;
                }
                $groups[$heading][] = ['route' => $route, 'label' => $label, 'icon' => $icon, 'url' => route($route), 'active' => self::isActive($route, $item[3] ?? [])];
            }
        }

        return $groups;
    }

    /**
     * @param  list<string>  $extra
     */
    public static function isActive(string $route, array $extra = []): bool
    {
        $base = str_ends_with($route, '.index') ? substr($route, 0, -6) : $route;

        return request()->routeIs($route, $base.'.*', ...$extra);
    }

    /** The current page's menu label, for the phone's top bar. */
    public static function currentLabel(): ?string
    {
        foreach (self::visible() as $items) {
            foreach ($items as $item) {
                if ($item['active']) {
                    return $item['label'];
                }
            }
        }

        return null;
    }
}
