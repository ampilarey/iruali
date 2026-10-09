<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Http\Requests\Seller\ProductRequest;
use App\Models\Category;
use App\Models\Product;
use App\Services\VariantService;
use App\Traits\SecureFileUpload;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    use SecureFileUpload;

    public function index(Request $request)
    {
        $products = Auth::user()->products()
            ->with(['category', 'variants'])
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->q.'%';
                $q->where(fn ($w) => $w->where('name', 'like', $term)->orWhere('sku', 'like', $term));
            })
            ->when($request->status === 'active', fn ($q) => $q->where('is_active', true))
            ->when($request->status === 'pending', fn ($q) => $q->where('is_active', false))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('seller.products.index', compact('products'));
    }

    public function create()
    {
        return view('seller.products.form', [
            'product' => new Product(['stock_quantity' => 0, 'reorder_point' => 5, 'has_variants' => false]),
            'categories' => $this->categories(),
            'variants' => collect(),
            'brandOptions' => \App\Models\Brand::orderBy('name')->pluck('name'),
        ]);
    }

    public function store(ProductRequest $request, VariantService $variants)
    {
        $product = new Product($request->productAttributes());
        $product->seller_id = Auth::id();
        $product->is_active = false; // new listings wait for admin approval
        if ($product->has_variants) {
            $product->stock_quantity = 0; // set from the variants below
        }
        $product->forceFill($request->videoAttributes()); // the optional video link, as provider + id
        $product->save();

        $this->storeImage($request, $product);
        $variants->sync($product, $request->variantRows());
        $this->saveMultiBuy($request, $product);
        app(\App\Services\OnboardingService::class)->refresh(Auth::user()->fresh());

        return redirect()->route('seller.products.index')
            ->with('success', 'Product submitted. It will be visible in the shop once an admin approves it.');
    }

    public function edit(Product $product)
    {
        $this->authorizeOwner($product);

        return view('seller.products.form', [
            'product' => $product,
            'categories' => $this->categories(),
            'variants' => $product->variants()->ordered()->get(),
            'brandOptions' => \App\Models\Brand::orderBy('name')->pluck('name'),
        ]);
    }

    public function update(ProductRequest $request, Product $product, VariantService $variants)
    {
        $this->authorizeOwner($product);

        $product->forceFill($request->videoAttributes()); // the optional video link, as provider + id
        $product->update($request->productAttributes());

        $this->storeImage($request, $product);
        $variants->sync($product, $request->variantRows());
        $this->saveMultiBuy($request, $product);

        return redirect()->route('seller.products.index')
            ->with('success', 'Product updated successfully.');
    }

    public function destroy(Product $product)
    {
        $this->authorizeOwner($product);

        $product->delete();

        return redirect()->route('seller.products.index')
            ->with('success', 'Product deleted.');
    }

    protected function authorizeOwner(Product $product): void
    {
        abort_unless((int) $product->seller_id === (int) Auth::id(), 403);
    }

    protected function categories()
    {
        return Category::where('status', 'active')->orderBy('name')->get();
    }

    protected function storeImage(Request $request, Product $product): void
    {
        if (! $request->hasFile('main_image')) {
            return;
        }

        $path = $this->storeFileSecurely($request->file('main_image'), 'products');
        if (! $path) {
            return;
        }

        if ($product->main_image) {
            $this->deleteFile($product->main_image);
        }

        $product->forceFill(['main_image' => $path])->save();

        $product->images()->where('is_main', true)->delete();
        $product->images()->create([
            'url' => Storage::url($path),
            'alt_text' => $product->getTranslation('name', 'en', false),
            'is_main' => true,
            'sort_order' => 0,
        ]);
    }

    /**
     * The form's "Multi-buy offer" fieldset (forms without it leave the offer alone).
     */
    protected function saveMultiBuy(ProductRequest $request, Product $product): void
    {
        if ($request->has('multibuy')) {
            app(\App\Services\MultiBuyService::class)->saveFromForm($product, (array) ($request->validated()['multibuy'] ?? []));
        }
    }
}
