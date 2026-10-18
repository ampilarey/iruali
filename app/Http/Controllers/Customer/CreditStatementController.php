<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\CreditService;
use Illuminate\Http\Request;

/**
 * My Account → Pay later: the business's credit limit, what is owed, open invoices with their due
 * dates, and the ones paid recently (a statement to print).
 */
class CreditStatementController extends Controller
{
    public function show(Request $request, CreditService $credit)
    {
        $user = $request->user();
        $account = $credit->account($user);
        abort_unless($account !== null, 404);

        return view('account.credit', [
            'account' => $account,
            'open' => $credit->openInvoices($user)->orderBy('credit_due_on')->get(),
            'paid' => Order::where('user_id', $user->id)->where('payment_method', CreditService::METHOD)->where('payment_status', 'paid')->latest('paid_at')->take(20)->get(),
            'outstanding' => $credit->outstanding($user),
            'available' => $credit->available($user),
        ]);
    }
}
