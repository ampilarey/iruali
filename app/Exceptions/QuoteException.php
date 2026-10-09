<?php

namespace App\Exceptions;

use App\Models\QuoteRequest;
use Illuminate\Contracts\Debug\ShouldntReport;
use RuntimeException;

/**
 * A bulk quote step that can't be taken (QuoteService): the message says why, in words the person
 * can act on, so it is shown to them and never reported. Placing an order shows it like the other
 * checkout refusals (OrderService catches RuntimeException).
 */
class QuoteException extends RuntimeException implements ShouldntReport
{
    /**
     * @param  QuoteRequest|null  $existing  the customer's open request that stood in the way, if that was the reason
     */
    public function __construct(string $message, public readonly ?QuoteRequest $existing = null)
    {
        parent::__construct($message);
    }
}
