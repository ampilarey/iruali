<?php

namespace App\Support;

use App\Models\PayoutBatch;

/**
 * The bulk-transfer file Bank of Maldives Internet Banking accepts, built from a payout batch.
 *
 * Layout (documented in docs/MARKETPLACE.md → "Bank file"):
 *   - UTF-8 text, no byte-order mark, CRLF ("\r\n") line endings, a line ending after the last row too
 *   - first line is the header: Beneficiary Account Number,Beneficiary Name,Amount,Remarks
 *   - one row per shop in the batch, in the batch's order (by shop name)
 *   - Beneficiary Account Number: the shop's account number, digits only, no spaces
 *   - Beneficiary Name: the account name exactly as the shop entered it
 *   - Amount: MVR with two decimals, a dot as the decimal mark, no thousands separator (1250.00)
 *   - Remarks: the payout reference, "<batch reference>/<payout id>" (e.g. PB-2026-0001/17), which is also
 *     what the shop sees on its earnings page
 *   - line breaks inside a value are replaced by spaces; a field is wrapped in double quotes only when it
 *     contains a comma or a quote, and a quote inside such a field is doubled
 *
 * Keep every detail of the layout here so a change for the bank is made in one place.
 */
final class BankFileFormat
{
    public const COLUMNS = ['Beneficiary Account Number', 'Beneficiary Name', 'Amount', 'Remarks'];

    public const LINE_ENDING = "\r\n";

    /**
     * The file for a batch. Each line of the batch becomes one transfer.
     */
    public static function forBatch(PayoutBatch $batch): string
    {
        $batch->loadMissing('payouts.seller.bankAccount');

        $rows = $batch->payouts
            ->sortBy(fn ($payout) => mb_strtolower($payout->seller?->shopName() ?? ''))
            ->map(fn ($payout) => [
                'account_number' => (string) $payout->seller?->bankAccount?->account_number,
                'account_name' => (string) $payout->seller?->bankAccount?->account_name,
                'amount' => (float) $payout->amount,
                'remark' => self::remark($batch, $payout->id),
            ]);

        return self::render($rows->all());
    }

    /**
     * @param  iterable<array{account_number: string, account_name: string, amount: float, remark: string}>  $rows
     */
    public static function render(iterable $rows): string
    {
        $lines = [self::line(self::COLUMNS)];
        foreach ($rows as $row) {
            $lines[] = self::line([
                preg_replace('/\s+/', '', $row['account_number']),
                $row['account_name'],
                number_format((float) $row['amount'], 2, '.', ''),
                $row['remark'],
            ]);
        }

        return implode(self::LINE_ENDING, $lines).self::LINE_ENDING;
    }

    /**
     * What the shop sees on its statement and what the admin can match the transfer against.
     */
    public static function remark(PayoutBatch $batch, int $payoutId): string
    {
        return $batch->reference.'/'.$payoutId;
    }

    public static function filename(PayoutBatch $batch): string
    {
        return 'bml-bulk-'.$batch->reference.'.csv';
    }

    protected static function line(array $fields): string
    {
        return implode(',', array_map([self::class, 'field'], $fields));
    }

    protected static function field(string $value): string
    {
        $value = str_replace(["\r", "\n"], ' ', $value);

        return preg_match('/[",]/', $value) ? '"'.str_replace('"', '""', $value).'"' : $value;
    }
}
