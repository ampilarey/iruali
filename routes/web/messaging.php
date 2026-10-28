<?php

use App\Http\Controllers\Admin\MessageInboxController;
use App\Http\Controllers\Customer\MessagesController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\Seller\MessagesController as SellerMessagesController;
use Illuminate\Support\Facades\Route;

/*
| The messages centre (App\Services\MessagingService): order threads between the customer, the shop and
| iruali support; item questions; iruali support for customers and shops; item request threads. The team
| sees all of them under Admin → Messages (admin, and support through config/staff.php). Nothing is deleted.
*/

Route::middleware('auth')->group(function () {
    Route::post('/orders/{order}/parts/{part}/messages', [MessageController::class, 'storeAsCustomer'])->name('orders.messages.store')->middleware('throttle:20,1,orders.messages.store');
    Route::get('/messages/{message}/attachment', [MessageController::class, 'attachment'])->name('messages.attachment')->whereNumber('message');

    // My messages, "Message iruali", "Ask about this item", item request threads, reporting
    Route::get('/messages', [MessagesController::class, 'index'])->name('messages.index');
    Route::get('/messages/support', [MessagesController::class, 'support'])->name('messages.support');
    Route::post('/messages/support', [MessagesController::class, 'storeSupport'])->name('messages.support.store')->middleware('throttle:20,1,messages.support.store');
    Route::get('/messages/c/{conversation}', [MessagesController::class, 'show'])->name('messages.show')->whereNumber('conversation');
    Route::post('/messages/c/{conversation}', [MessagesController::class, 'reply'])->name('messages.reply')->whereNumber('conversation')->middleware('throttle:20,1,messages.reply');
    Route::post('/products/{product}/ask', [MessagesController::class, 'ask'])->name('messages.product.store')->middleware('throttle:10,10,messages.product.store');
    Route::post('/account/item-requests/{itemRequest}/messages', [MessagesController::class, 'itemRequest'])->name('item-requests.messages.store')->whereNumber('itemRequest')->middleware('throttle:20,1,item-requests.messages.store');
    Route::post('/messages/{message}/report', [MessagesController::class, 'report'])->name('messages.report')->whereNumber('message')->middleware('throttle:10,60,messages.report');
});

Route::middleware(['auth', 'role:seller'])->prefix('seller')->name('seller.')->group(function () {
    Route::post('/orders/{order}/parts/{part}/messages', [MessageController::class, 'storeAsSeller'])->name('orders.messages.store')->middleware('throttle:20,1,seller.orders.messages.store');
    Route::get('/messages', [SellerMessagesController::class, 'index'])->name('messages');
    Route::get('/messages/support', [SellerMessagesController::class, 'support'])->name('messages.support');
    Route::post('/messages/support', [SellerMessagesController::class, 'storeSupport'])->name('messages.support.store')->middleware('throttle:20,1,seller.messages.support.store');
    Route::get('/messages/{conversation}', [SellerMessagesController::class, 'show'])->name('messages.show')->whereNumber('conversation');
    Route::post('/messages/{conversation}', [SellerMessagesController::class, 'reply'])->name('messages.reply')->whereNumber('conversation')->middleware('throttle:20,1,seller.messages.reply');
});

Route::middleware(['auth', 'staff', 'staff.2fa'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/messages', [MessageInboxController::class, 'index'])->name('messages');
    Route::get('/messages/{conversation}', [MessageInboxController::class, 'show'])->name('messages.show')->whereNumber('conversation');
    Route::post('/messages/{conversation}/reply', [MessageInboxController::class, 'reply'])->name('messages.reply')->whereNumber('conversation')->middleware('throttle:60,1,admin.messages.reply');
    Route::post('/messages/{conversation}/status', [MessageInboxController::class, 'status'])->name('messages.status')->whereNumber('conversation');
    Route::post('/messages/users/{user}/block', [MessageInboxController::class, 'block'])->name('messages.block');
    Route::post('/orders/{order}/parts/{part}/messages', [MessageController::class, 'storeAsAdmin'])->name('orders.messages.store')->middleware('throttle:60,1,admin.orders.messages.store');
    Route::post('/conversations/{conversation}/status', [MessageInboxController::class, 'status'])->name('conversations.status');
});
