<?php

namespace App\Services;

use App\Models\Category;
use App\Models\GiftCard;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Notifications\GiftCardIssued;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Gift cards are bought like an order (one virtual "Gift card" line, paid by card through BML),
 * issued when that order is paid, and redeemed into a wallet.
 */
class GiftCardService
{
    /**
     * Create the (unpaid) order and the pending card. The caller sends the buyer to BML.
     *
     * @return array{order: Order, card: GiftCard}
     */
    public function purchase(User $buyer, float $amount, string $recipientEmail, ?string $recipientName, ?string $message): array
    {
        $amount = round($amount, 2);
        if ($amount < GiftCard::MIN_CUSTOM || $amount > GiftCard::MAX_CUSTOM) {
            throw new RuntimeException(__('Gift cards are from MVR :min to MVR :max.', ['min' => GiftCard::MIN_CUSTOM, 'max' => GiftCard::MAX_CUSTOM]));
        }

        return DB::transaction(function () use ($buyer, $amount, $recipientEmail, $recipientName, $message) {
            $order = Order::create([
                'user_id' => $buyer->id,
                'order_number' => $this->orderNumber(),
                'status' => 'pending',
                'total_amount' => $amount,
                'shipping_amount' => 0,
                'delivery_zone' => null,
                'payment_method' => 'bml',
                'loyalty_points_earned' => 0,
                'points_redeemed' => 0,
                'points_redeemed_discount' => 0,
                'voucher_discount' => 0,
                'shipping_address' => __('Gift card (sent by email)'),
                'shipping_city' => '-',
                'shipping_state' => '-',
                'shipping_zip' => null,
                'shipping_country' => 'Maldives',
                'shipping_phone' => $buyer->phone,
            ]);
            $order->items()->create(['product_id' => $this->product()->id, 'quantity' => 1, 'price' => $amount]);

            $card = GiftCard::create([
                'amount' => $amount,
                'balance' => $amount,
                'purchaser_id' => $buyer->id,
                'order_id' => $order->id,
                'recipient_email' => mb_strtolower(trim($recipientEmail)),
                'recipient_name' => $recipientName ? trim($recipientName) : null,
                'message' => $message ? trim($message) : null,
                'status' => 'pending',
            ]);

            return ['order' => $order, 'card' => $card];
        });
    }

    /**
     * The order was paid: give the card its code, start the clock and email the recipient.
     * Safe to call again (a card already issued is left alone).
     */
    public function issueForOrder(Order $order): void
    {
        foreach (GiftCard::where('order_id', $order->id)->where('status', 'pending')->get() as $card) {
            $card->update([
                'code' => GiftCard::freshCode(),
                'status' => 'active',
                'expires_at' => now()->addMonths(GiftCard::VALID_MONTHS),
                'delivered_at' => now(),
            ]);

            try {
                Notification::route('mail', [$card->recipient_email => $card->recipient_name ?: $card->recipient_email])
                    ->notify((new GiftCardIssued($card->fresh()))->locale($order->user?->preferredLocale() ?? app()->getLocale()));
            } catch (Throwable $e) {
                report($e);
            }
        }
    }

    /**
     * The unpaid order behind a card was cancelled: the card never goes out.
     */
    public function orderCancelled(Order $order): void
    {
        GiftCard::where('order_id', $order->id)->where('status', 'pending')->update(['status' => 'cancelled']);
    }

    /**
     * Put the card's balance into the customer's wallet.
     */
    public function redeem(User $user, string $input, WalletService $wallet): GiftCard
    {
        $code = GiftCard::normaliseCode($input);

        $card = GiftCard::where('code', $code)->first();
        if (! $card) {
            throw new RuntimeException(__('We do not know that gift card code.'));
        }
        if ($card->status === 'active' && $card->expires_at && $card->expires_at->isPast()) {
            $card->update(['status' => 'expired']);
        }
        $this->assertRedeemable($card);

        return DB::transaction(function () use ($user, $card, $wallet) {
            $locked = GiftCard::whereKey($card->id)->lockForUpdate()->first();
            $this->assertRedeemable($locked); // two redemptions racing: only the first wins

            $wallet->credit($user, (float) $locked->balance, 'gift_card', ['gift_card_id' => $locked->id, 'reference' => $locked->code, 'note' => __('Gift card :code', ['code' => $locked->code])]);
            $locked->update(['balance' => 0, 'status' => 'redeemed', 'redeemed_by' => $user->id, 'redeemed_at' => now()]);

            return $locked;
        });
    }

    protected function assertRedeemable(GiftCard $card): void
    {
        if ($card->isRedeemable()) {
            return;
        }

        throw new RuntimeException(match ($card->status) {
            'redeemed' => __('This gift card has already been redeemed.'),
            'expired' => __('This gift card has expired.'),
            'cancelled' => __('This gift card was cancelled.'),
            default => __('This gift card cannot be redeemed.'),
        });
    }

    /**
     * Admin: a card that has not been redeemed is cancelled.
     */
    public function cancel(GiftCard $card): bool
    {
        if (! in_array($card->status, ['active', 'pending'], true)) {
            return false;
        }
        $card->update(['status' => 'cancelled']);

        return true;
    }

    /**
     * Active cards past their date are marked expired (scheduled daily).
     */
    public function expireOld(): int
    {
        return GiftCard::where('status', 'active')->whereNotNull('expires_at')->where('expires_at', '<', now())->update(['status' => 'expired']);
    }

    /**
     * The hidden product every gift-card order line points at.
     */
    public function product(): Product
    {
        $product = Product::withTrashed()->where('slug', 'gift-card')->first();
        if ($product) {
            return $product;
        }

        $category = Category::firstOrCreate(['slug' => 'gift-cards'], ['name' => ['en' => 'Gift cards', 'dv' => 'ގިފްޓް ކާޑު'], 'status' => 'inactive']);
        $owner = User::whereHas('roles', fn ($q) => $q->where('name', 'admin'))->orderBy('id')->first() ?? User::orderBy('id')->firstOrFail();

        return Product::create([
            'name' => ['en' => 'Gift card', 'dv' => 'ގިފްޓް ކާޑު'],
            'description' => ['en' => 'An iruali gift card, delivered by email.', 'dv' => 'އީމެއިލުން ފޮނުވޭ iruali ގިފްޓް ކާޑެއް.'],
            'slug' => 'gift-card',
            'sku' => 'GIFT-CARD',
            'category_id' => $category->id,
            'seller_id' => $owner->id,
            'price' => 0,
            'stock_quantity' => 0,
            'is_active' => false,
            'is_digital' => true,
            'requires_shipping' => false,
        ]);
    }

    protected function orderNumber(): string
    {
        do {
            $number = 'ORD-'.strtoupper(Str::random(10));
        } while (Order::where('order_number', $number)->exists());

        return $number;
    }
}
