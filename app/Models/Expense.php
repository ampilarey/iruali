<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One of iruali's own costs (hosting, SMS, card fees, marketing...), with an optional receipt.
 */
class Expense extends Model
{
    public const DISK = 'local';

    public const CATEGORIES = [
        'hosting' => 'Hosting and software', 'sms' => 'SMS', 'card_fees' => 'Card and bank fees', 'marketing' => 'Marketing',
        'staff' => 'Staff', 'delivery' => 'Delivery and couriers', 'office' => 'Office', 'professional' => 'Accounting and legal', 'other' => 'Other',
    ];

    protected $fillable = ['spent_on', 'category', 'description', 'supplier', 'amount', 'gst_amount', 'receipt_path', 'recurring', 'repeat_of', 'created_by'];

    protected $casts = ['spent_on' => 'date', 'amount' => 'decimal:2', 'gst_amount' => 'decimal:2', 'recurring' => 'boolean'];

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? $this->category;
    }
}
