<?php

namespace App\Support;

use App\Models\Order;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * The order receipt as a PDF file (download from the receipt page, attached to "email me this
 * receipt"). Made in English: the PDF engine cannot lay out Thaana; the web receipt stays bilingual.
 */
class ReceiptPdf
{
    public static function make(Order $order): string
    {
        $order->loadMissing(['user', 'items.product.seller', 'paymentTransactions']);
        $locale = app()->getLocale();
        app()->setLocale('en');
        try {
            return Pdf::loadView('orders.receipt', ['order' => $order, 'pdf' => true])->setPaper('a4')->output();
        } finally {
            app()->setLocale($locale);
        }
    }

    public static function filename(Order $order): string
    {
        return 'iruali-receipt-'.$order->order_number.'.pdf';
    }
}
