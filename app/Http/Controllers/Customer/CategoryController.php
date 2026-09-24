<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Services\CatalogService;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = Category::active()->root()
            ->withCount(['products' => fn ($q) => $q->active()])
            ->with(['children' => fn ($q) => $q->active()])
            ->get()
            ->sortBy(fn ($c) => $c->localized_name)
            ->values();

        return view('categories.index', compact('categories'));
    }

    public function show(Request $request, Category $category, CatalogService $catalog)
    {
        abort_unless($category->status === 'active', 404);

        $crumbs = [];
        if ($category->parent) {
            $crumbs[] = ['label' => $category->parent->localized_name, 'url' => route('categories.show', $category->parent)];
        }

        return view('catalog.index', $catalog->listing($request, ['category' => $category]) + [
            'title' => $category->localized_name,
            'subtitle' => $category->localized_description,
            'category' => $category,
            'subcategories' => $category->children()->active()->get(),
            'crumbs' => $crumbs,
        ]);
    }
}
