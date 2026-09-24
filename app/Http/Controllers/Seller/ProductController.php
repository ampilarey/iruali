<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Traits\SecureFileUpload;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    use SecureFileUpload;

    public function index(Request $request)
    {
        $products = Auth::user()->products()
            ->with('category')
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
            'product' => new Product(['stock_quantity' => 0, 'reorder_point' => 5]),
            'categories' => $this->categories(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $product = new Product($this->attributes($data));
        $product->seller_id = Auth::id();
        $product->is_active = false; // new listings wait for admin approval
        $product->save();

        $this->storeImage($request, $product);

        return redirect()->route('seller.products.index')
            ->with('success', 'Product submitted. It will be visible in the shop once an admin approves it.');
    }

    public function edit(Product $product)
    {
        $this->authorizeOwner($product);

        return view('seller.products.form', [
            'product' => $product,
            'categories' => $this->categories(),
        ]);
    }

    public function update(Request $request, Product $product)
    {
        $this->authorizeOwner($product);

        $data = $this->validated($request, $product);
        $product->update($this->attributes($data));

        $this->storeImage($request, $product);

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

    protected function validated(Request $request, ?Product $product = null): array
    {
        return $request->validate([
            'name_en' => 'required|string|max:255',
            'name_dv' => 'nullable|string|max:255',
            'description_en' => 'nullable|string|max:5000',
            'description_dv' => 'nullable|string|max:5000',
            'sku' => ['required', 'string', 'max:100', Rule::unique('products', 'sku')->ignore($product?->id)],
            'category_id' => 'required|exists:categories,id',
            'price' => 'required|numeric|min:0|max:999999.99',
            'compare_price' => 'nullable|numeric|min:0|max:999999.99|gt:price',
            'stock_quantity' => 'required|integer|min:0|max:999999',
            'reorder_point' => 'nullable|integer|min:0|max:999999',
            'brand' => 'nullable|string|max:255',
            'weight' => 'nullable|numeric|min:0|max:999.99',
            'main_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);
    }

    protected function attributes(array $data): array
    {
        return [
            'name' => array_filter(['en' => $data['name_en'], 'dv' => $data['name_dv'] ?? null]),
            'description' => array_filter(['en' => $data['description_en'] ?? null, 'dv' => $data['description_dv'] ?? null]),
            'sku' => $data['sku'],
            'category_id' => $data['category_id'],
            'price' => $data['price'],
            'compare_price' => $data['compare_price'] ?? null,
            'stock_quantity' => $data['stock_quantity'],
            'reorder_point' => $data['reorder_point'] ?? 5,
            'brand' => $data['brand'] ?? null,
            'weight' => $data['weight'] ?? null,
        ];
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
}
