<?php

/*
|--------------------------------------------------------------------------
| Shop staff: who may open which Seller Centre pages
|--------------------------------------------------------------------------
|
| A shop owner can let people into the Seller Centre for their shop with their own sign-in
| (Seller Centre → Staff). Each staff member has one role; the route names each role may open are
| listed below, like config/staff.php does for the admin area. An entry ending in "*" matches every
| route name starting with it ("seller.orders.*" covers show, status, tracking...); any other entry
| must match exactly. A page that is on no list is closed to staff, so new Seller Centre pages stay
| owner-only until they are added here.
|
| Enforced by the role:seller middleware (App\Http\Middleware\ShopAccess) on every seller route, and
| used by the Seller Centre nav to hide links the person cannot open. The owner always sees everything.
|
*/

return [

    'access' => [

        // Everything in the Seller Centre except the owner-only pages below
        'manager' => [
            'seller.dashboard',
            'seller.products.*',
            'seller.stock', 'seller.stock.*',
            'seller.preorders', 'seller.preorders.*',
            'seller.campaigns', 'seller.campaigns.*',
            'seller.discounts', 'seller.discounts.*',
            'seller.orders', 'seller.orders.*',
            'seller.returns',
            'seller.questions',
            'seller.reviews', 'seller.reviews.*',
            'seller.analytics',
            'seller.performance',
            'seller.profile', 'seller.profile.*', // the shop's details; the owner's own name, phone and bank details stay hidden
            'seller.settings.notifications', 'seller.settings.notifications.*',
            'seller.settings.delivery', 'seller.settings.delivery.*',
            'seller.help', 'seller.help.*',
        ],

        // Packing and handing over orders: the order pages (view, move the part along, delivery
        // details, packing slip, ready for pickup and the pickup code), the stock page and
        // product questions
        'packer' => [
            'seller.orders',
            'seller.orders.show',
            'seller.orders.status',
            'seller.orders.tracking',
            'seller.orders.packing-slip',
            'seller.orders.pickup.ready',
            'seller.orders.pickup.collected',
            'seller.stock', 'seller.stock.update',
            'seller.preorders', 'seller.preorders.arrived', // booking in stock that came; moving a promised date is for managers
            'seller.questions',
            'seller.help', 'seller.help.*',
        ],
    ],

    // Never open to staff, whatever the lists above say: bank details, earnings and payouts, tax
    // registration, business verification documents, staff management and holiday mode (it closes
    // the shop to customers).
    'owner_only' => [
        'seller.earnings', 'seller.earnings.*',
        'seller.payouts', 'seller.payouts.*',
        'seller.settings.bank', 'seller.settings.bank.*',
        'seller.settings.tax', 'seller.settings.tax.*',
        'seller.settings.verification', 'seller.settings.verification.*',
        'seller.settings.holiday', 'seller.settings.holiday.*',
        'seller.staff', 'seller.staff.*',
    ],

    // An invitation link works for this many days
    'invitation_days' => 7,

    // Staff members plus open invitations a shop can have at once
    'max_members' => 20,

    // Invitations a shop can send per hour (sending one again counts too)
    'invitations_per_hour' => 10,

];
