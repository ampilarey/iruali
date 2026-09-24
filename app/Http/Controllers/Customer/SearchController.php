<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Services\CatalogService;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function search(Request $request, CatalogService $catalog)
    {
        $q = trim((string) $request->query('q', ''));

        if ($q === '') {
            return redirect()->route('shop', $request->only('category'));
        }

        // The header's department picker sends a department; show that department's page with the search applied.
        return view('catalog.index', $catalog->listing($request) + [
            'title' => __('Results for “:q”', ['q' => $q]),
            'crumbs' => [],
        ]);
    }
}
