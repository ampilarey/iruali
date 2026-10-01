<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAddressRequest;
use App\Models\Address;
use App\Services\DeliveryService;
use App\Services\NotificationService;
use Illuminate\Http\Request;

/**
 * My Account → Addresses: the delivery addresses a customer keeps for checkout.
 */
class AddressController extends Controller
{
    public function index(Request $request)
    {
        $addresses = $request->user()->addresses()->defaultFirst()->with('islandRecord')->get();

        return view('account.addresses.index', compact('addresses'));
    }

    public function create(DeliveryService $delivery)
    {
        return view('account.addresses.form', ['address' => new Address, 'islandsByAtoll' => $delivery->islandsByAtoll()]);
    }

    public function store(StoreAddressRequest $request)
    {
        $address = new Address($request->addressData());
        $address->user_id = $request->user()->id;
        $address->syncIsland()->save();

        NotificationService::success(__('Address saved.'));

        return redirect()->route('account.addresses');
    }

    public function edit(Address $address, DeliveryService $delivery)
    {
        $this->authorize('update', $address);

        return view('account.addresses.form', ['address' => $address, 'islandsByAtoll' => $delivery->islandsByAtoll()]);
    }

    public function update(StoreAddressRequest $request, Address $address)
    {
        $this->authorize('update', $address);

        $address->fill($request->addressData())->syncIsland()->save();

        NotificationService::success(__('Address saved.'));

        return redirect()->route('account.addresses');
    }

    public function destroy(Address $address)
    {
        $this->authorize('delete', $address);

        $address->delete();

        NotificationService::success(__('Address removed.'));

        return redirect()->route('account.addresses');
    }

    public function makeDefault(Address $address)
    {
        $this->authorize('update', $address);

        $address->makeDefault();

        NotificationService::success(__('Default address updated.'));

        return redirect()->route('account.addresses');
    }
}
