<?php

namespace App\Services;

use App\Models\BankStatementLine;
use App\Models\Order;
use App\Models\PaymentTransaction;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Reads a BML statement exported as CSV (the columns are found by their headings: date,
 * description or narrative, reference, credit / amount) and matches each line of money in to an
 * order: by a card transaction's id, by an order number in the description, or by the exact
 * amount of one unpaid bank-transfer order.
 */
class SettlementService
{
    /**
     * @return array{added: int, skipped: int, matched: int}
     */
    public function import(string $path): array
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new \RuntimeException('Could not read the file.');
        }
        $columns = null;
        $batch = now()->format('Ymd-His');
        $result = ['added' => 0, 'skipped' => 0, 'matched' => 0];

        while (($row = fgetcsv($handle)) !== false) {
            if ($columns === null) {
                $columns = $this->columns($row); // until the heading row is found

                continue;
            }
            $line = $this->line($row, $columns);
            if (! $line) {
                continue;
            }
            $fingerprint = hash('sha256', implode('|', [$line['posted_on'], $line['amount'], $line['reference'], $line['description']]));
            if (BankStatementLine::where('fingerprint', $fingerprint)->exists()) {
                $result['skipped']++;

                continue;
            }
            $model = BankStatementLine::create($line + ['fingerprint' => $fingerprint, 'batch' => $batch]);
            $result['added']++;
            if ($this->match($model)) {
                $result['matched']++;
            }
        }
        fclose($handle);
        if ($columns === null) {
            throw new \RuntimeException('No heading row with a date and an amount or credit column was found.');
        }

        return $result;
    }

    public function match(BankStatementLine $line): bool
    {
        $text = $line->description.' '.$line->reference;
        $order = null;
        $kind = null;

        // A card payment: BML's transaction id or our reference for it in the line
        $transaction = PaymentTransaction::where('state', 'CONFIRMED')->get(['order_id', 'transaction_id', 'local_id'])
            ->first(fn ($t) => ($t->transaction_id && str_contains($text, (string) $t->transaction_id)) || ($t->local_id && str_contains($text, (string) $t->local_id)));
        if ($transaction) {
            [$order, $kind] = [Order::find($transaction->order_id), 'card'];
        } elseif (preg_match('/ORD-[A-Z0-9]{6,12}/i', $text, $m)) {
            [$order, $kind] = [Order::where('order_number', strtoupper($m[0]))->first(), 'transfer'];
        } else {
            // One unpaid bank-transfer order for exactly this amount
            $candidates = Order::where('payment_method', ManualPaymentService::TRANSFER)->where('payment_status', '!=', 'paid')
                ->where('status', '!=', 'cancelled')->where('total_amount', $line->amount)->limit(2)->get();
            [$order, $kind] = $candidates->count() === 1 ? [$candidates->first(), 'amount'] : [null, null];
        }

        if (! $order) {
            return false;
        }
        $line->forceFill(['order_id' => $order->id, 'match_kind' => $kind, 'status' => 'matched'])->save();

        return true;
    }

    /**
     * Column positions from the heading row, or null when this row is not it.
     *
     * @param  list<string|null>  $row
     * @return array<string, int>|null
     */
    protected function columns(array $row): ?array
    {
        $find = function (array $names) use ($row): ?int {
            foreach ($row as $i => $cell) {
                $cell = strtolower(trim((string) $cell));
                foreach ($names as $name) {
                    if ($cell === $name || str_contains($cell, $name)) {
                        return $i;
                    }
                }
            }

            return null;
        };
        $columns = array_filter([
            'date' => $find(['value date', 'posting date', 'transaction date', 'date']),
            'description' => $find(['description', 'narrative', 'particulars', 'details', 'remarks']),
            'reference' => $find(['reference', 'ref']),
            'credit' => $find(['credit', 'deposit', 'money in']),
            'amount' => $find(['amount']),
        ], fn ($i) => $i !== null);

        return isset($columns['date']) && (isset($columns['credit']) || isset($columns['amount'])) ? $columns : null;
    }

    /**
     * One line of money in, or null (a debit, a blank or a total row).
     *
     * @param  list<string|null>  $row
     * @param  array<string, int>  $columns
     * @return array{posted_on: string, description: string, reference: ?string, amount: float}|null
     */
    protected function line(array $row, array $columns): ?array
    {
        $money = fn ($value) => (float) str_replace([',', ' ', 'MVR'], '', (string) $value);
        $amount = isset($columns['credit']) ? $money($row[$columns['credit']] ?? '') : $money($row[$columns['amount']] ?? '');
        $date = trim((string) ($row[$columns['date']] ?? ''));
        if ($amount <= 0 || $date === '') {
            return null;
        }
        try {
            $postedOn = $this->date($date);
        } catch (Throwable) {
            return null;
        }

        return [
            'posted_on' => $postedOn,
            'description' => mb_substr(trim((string) ($row[$columns['description'] ?? -1] ?? '')), 0, 255),
            'reference' => isset($columns['reference']) ? (mb_substr(trim((string) ($row[$columns['reference']] ?? '')), 0, 120) ?: null) : null,
            'amount' => round($amount, 2),
        ];
    }

    protected function date(string $value): string
    {
        foreach (['d/m/Y', 'd-m-Y', 'Y-m-d', 'd M Y', 'd-M-Y', 'd/m/y', 'j M Y'] as $format) {
            try {
                $parsed = Carbon::createFromFormat('!'.$format, $value);
            } catch (Throwable) {
                continue;
            }
            if ($parsed && $parsed->format($format) === $value) {
                return $parsed->toDateString();
            }
        }

        return Carbon::parse($value)->toDateString();
    }
}
