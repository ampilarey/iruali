<?php

/*
|--------------------------------------------------------------------------
| Staff roles and what each may open in /admin
|--------------------------------------------------------------------------
|
| Route names each role may open. "admin" sees everything. An entry ending in "*" matches
| every route name starting with it (e.g. "admin.orders.*" covers show, status, refund);
| any other entry must match the route name exactly (e.g. "admin.users" is the list only,
| not "admin.users.role"). Enforced by App\Http\Middleware\StaffAccess on the admin group
| and used by the admin nav to hide links the person cannot open.
|
*/

return [

    // Roles that count as staff (User::isStaff()); they must have two-step sign-in turned on.
    'roles' => ['admin', 'support', 'finance'],

    // Staff without 2FA are sent to the setup page before they can open /admin. Turn off locally.
    'require_two_factor' => (bool) env('STAFF_REQUIRE_2FA', true),

    'access' => [
        'admin' => ['admin.*'],

        // Customer support: orders, returns, moderation, users (read-only), inbox
        'support' => [
            'admin.dashboard',
            'admin.inbox',
            'admin.orders', 'admin.orders.*',
            'admin.returns', 'admin.returns.*',
            'admin.reviews', 'admin.reviews.*',
            'admin.questions', 'admin.questions.*',
            'admin.newsletter', 'admin.newsletter.*',
            'admin.users',
            'admin.disputes', 'admin.disputes.*',
            'admin.messages', 'admin.messages.*',
            'admin.sms', 'admin.sms.*',
            'admin.preorders', // late pre-orders (inbox row)
        ],

        // Finance: payouts, refunds, analytics, errors (read), audit log
        'finance' => [
            'admin.dashboard',
            'admin.inbox',
            'admin.payouts', 'admin.payouts.*',
            'admin.sellers.commission',
            'admin.orders', 'admin.orders.show', 'admin.orders.refunded', 'admin.orders.bml-sync',
            'admin.returns', 'admin.returns.show', 'admin.returns.refunded',
            'admin.analytics',
            'admin.errors', 'admin.errors.show',
            'admin.audit',
            'admin.disputes', 'admin.disputes.show',
            'admin.rewards', 'admin.gift-cards', 'admin.gift-cards.*',
            'admin.tax', 'admin.tax.*', 'admin.orders.invoice', // GST settings, monthly GST report, shops' invoices
        ],
    ],

];
