<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\DemoDataService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Admin → Settings → Sample data: take the demo shops and sample products off the site before
 * launch (typing REMOVE to confirm), or put them back. Full admins only (routes/web/demo-data.php).
 */
class DemoDataController extends Controller
{
    public function __construct(protected DemoDataService $demo) {}

    public function index(): View
    {
        return view('admin.demo-data.index', ['status' => $this->demo->status()]);
    }

    public function remove(Request $request): RedirectResponse
    {
        $request->validate(
            ['confirm' => ['required', 'string', 'in:REMOVE']],
            ['confirm.required' => __('Type REMOVE in the box to confirm.'), 'confirm.in' => __('Type REMOVE in the box to confirm.')],
        );

        $removed = $this->demo->remove();
        if (array_sum($removed) === 0) {
            return redirect()->route('admin.sample-data')->with('success', __('There was no sample data left to remove.'));
        }

        return redirect()->route('admin.sample-data')->with('success', __('Sample data removed. Products taken off the site: :products. Shops closed: :shops.', [
            'products' => $removed['products'],
            'shops' => $removed['shops'],
        ]));
    }

    public function restore(): RedirectResponse
    {
        $restored = $this->demo->restore();
        if (array_sum($restored) === 0) {
            return redirect()->route('admin.sample-data')->with('success', __('There was nothing to restore.'));
        }

        return redirect()->route('admin.sample-data')->with('success', __('Sample data restored. Products back on the site: :products. Shops reopened: :shops.', [
            'products' => $restored['products'],
            'shops' => $restored['shops'],
        ]));
    }
}
