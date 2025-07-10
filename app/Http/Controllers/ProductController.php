<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    /**
     * Display a listing of products
     */
    public function index()
    {
        $products = Product::with(['category', 'seller'])
            ->when(auth()->user() && auth()->user()->isSeller(), function($query) {
                return $query->where('seller_id', auth()->id());
            })
            ->latest()
            ->paginate(12);

        $categories = Category::active()->root()->get();
        return view('products.index', compact('products', 'categories'));
    }

    /**
     * Show the form for creating a new product
     */
    public function create()
    {
        $categories = Category::where('is_active', true)->get();
        $islands = \App\Models\Island::where('is_active', true)->get();
        return view('products.create', compact('categories', 'islands'));
    }

    /**
     * Store a newly created product
     */
    public function store(Request $request)
    {
        $request->validate([
            'name.en' => 'required|string|max:255',
            'name.dv' => 'required|string|max:255',
            'description.en' => 'nullable|string',
            'description.dv' => 'nullable|string',
            'sku' => 'required|string|unique:products,sku',
            'category_id' => 'required|exists:categories,id',
            'price' => 'required|numeric|min:0',
            'compare_price' => 'nullable|numeric|min:0',
            'stock_quantity' => 'nullable|integer|min:0',
            'reorder_point' => 'nullable|integer|min:0',
            'brand' => 'nullable|string|max:255',
            'model' => 'nullable|string|max:255',
            'main_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'is_featured' => 'boolean',
            'requires_shipping' => 'boolean',
            'is_digital' => 'boolean',
        ]);

        $data = $request->all();
        
        // Generate slug from English name
        $data['slug'] = Str::slug($data['name']['en']);
        
        // Set seller ID if user is a seller
        if (auth()->user() && auth()->user()->isSeller()) {
            $data['seller_id'] = auth()->id();
        } else {
            $data['seller_id'] = 1; // Default admin user
        }

        // Handle image upload
        if ($request->hasFile('main_image')) {
            $data['main_image'] = $request->file('main_image')->store('products', 'public');
        }

        // Set default values
        $data['is_active'] = auth()->user() && auth()->user()->isAdmin() ? true : false; // Requires admin approval
        $data['stock_quantity'] = $data['stock_quantity'] ?? 0;
        $data['reorder_point'] = $data['reorder_point'] ?? 10;

        $product = Product::create($data);

        // Handle per-island inventory
        if ($request->has('island_stock')) {
            $syncData = [];
            foreach ($request->island_stock as $islandId => $row) {
                $syncData[$islandId] = [
                    'stock_quantity' => $row['stock_quantity'] ?? 0,
                    'reorder_point' => $row['reorder_point'] ?? null,
                    'is_active' => isset($row['is_active']) ? true : false,
                ];
            }
            $product->islands()->sync($syncData);
        }

        // Handle variants
        if ($request->has('variants')) {
            foreach ($request->variants as $variant) {
                $variantData = [
                    'product_id' => $product->id,
                    'name' => [
                        'en' => $variant['name']['en'] ?? '',
                        'dv' => $variant['name']['dv'] ?? '',
                    ],
                    'type' => $variant['type'] ?? '',
                    'sku' => $variant['sku'] ?? '',
                    'price_adjustment' => $variant['price_adjustment'] ?? 0,
                    'stock_quantity' => $variant['stock_quantity'] ?? 0,
                    'is_active' => isset($variant['is_active']) ? true : false,
                ];
                // Handle image upload for variant
                if (isset($variant['image']) && is_file($variant['image'])) {
                    $variantData['image'] = $variant['image']->store('variants', 'public');
                }
                $product->variants()->create($variantData);
            }
        }

        return redirect()->route('products.index')
            ->with('success', __('products.created_successfully'));
    }

    /**
     * Display the specified product
     */
    public function show(Product $product)
    {
        $product->load(['category', 'seller', 'reviews.user']);
        
        // Get related products
        $relatedProducts = Product::where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->where('is_active', true)
            ->limit(4)
            ->get();

        return view('products.show', compact('product', 'relatedProducts'));
    }

    /**
     * Show the form for editing the specified product
     */
    public function edit(Product $product)
    {
        // Check if user can edit this product
        if (auth()->user() && !auth()->user()->isAdmin() && $product->seller_id !== auth()->id()) {
            abort(403);
        }

        $categories = Category::where('is_active', true)->get();
        $islands = \App\Models\Island::where('is_active', true)->get();
        $product->load('islands');
        return view('products.edit', compact('product', 'categories', 'islands'));
    }

    /**
     * Update the specified product
     */
    public function update(Request $request, Product $product)
    {
        // Check if user can update this product
        if (auth()->user() && !auth()->user()->isAdmin() && $product->seller_id !== auth()->id()) {
            abort(403);
        }

        $request->validate([
            'name.en' => 'required|string|max:255',
            'name.dv' => 'required|string|max:255',
            'description.en' => 'nullable|string',
            'description.dv' => 'nullable|string',
            'sku' => 'required|string|unique:products,sku,' . $product->id,
            'category_id' => 'required|exists:categories,id',
            'price' => 'required|numeric|min:0',
            'compare_price' => 'nullable|numeric|min:0',
            'stock_quantity' => 'nullable|integer|min:0',
            'reorder_point' => 'nullable|integer|min:0',
            'brand' => 'nullable|string|max:255',
            'model' => 'nullable|string|max:255',
            'main_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'is_featured' => 'boolean',
            'requires_shipping' => 'boolean',
            'is_digital' => 'boolean',
        ]);

        $data = $request->all();
        
        // Generate slug from English name
        $data['slug'] = Str::slug($data['name']['en']);

        // Handle image upload
        if ($request->hasFile('main_image')) {
            // Delete old image
            if ($product->main_image) {
                Storage::disk('public')->delete($product->main_image);
            }
            $data['main_image'] = $request->file('main_image')->store('products', 'public');
        }

        $product->update($data);

        // Handle per-island inventory
        if ($request->has('island_stock')) {
            $syncData = [];
            foreach ($request->island_stock as $islandId => $row) {
                $syncData[$islandId] = [
                    'stock_quantity' => $row['stock_quantity'] ?? 0,
                    'reorder_point' => $row['reorder_point'] ?? null,
                    'is_active' => isset($row['is_active']) ? true : false,
                ];
            }
            $product->islands()->sync($syncData);
        }

        // Handle variants
        $existingIds = $product->variants()->pluck('id')->toArray();
        $submittedIds = [];
        if ($request->has('variants')) {
            foreach ($request->variants as $variant) {
                $variantData = [
                    'name' => [
                        'en' => $variant['name']['en'] ?? '',
                        'dv' => $variant['name']['dv'] ?? '',
                    ],
                    'type' => $variant['type'] ?? '',
                    'sku' => $variant['sku'] ?? '',
                    'price_adjustment' => $variant['price_adjustment'] ?? 0,
                    'stock_quantity' => $variant['stock_quantity'] ?? 0,
                    'is_active' => isset($variant['is_active']) ? true : false,
                ];
                // Handle image upload for variant
                if (isset($variant['image']) && is_file($variant['image'])) {
                    $variantData['image'] = $variant['image']->store('variants', 'public');
                }
                if (isset($variant['id']) && in_array($variant['id'], $existingIds)) {
                    // Update existing variant
                    $product->variants()->where('id', $variant['id'])->update($variantData);
                    $submittedIds[] = $variant['id'];
                } else {
                    // Create new variant
                    $newVariant = $product->variants()->create($variantData);
                    $submittedIds[] = $newVariant->id;
                }
            }
        }
        // Delete removed variants
        $toDelete = array_diff($existingIds, $submittedIds);
        if (!empty($toDelete)) {
            $product->variants()->whereIn('id', $toDelete)->delete();
        }

        return redirect()->route('products.index')
            ->with('success', __('products.updated_successfully'));
    }

    /**
     * Remove the specified product
     */
    public function destroy(Product $product)
    {
        // Check if user can delete this product
        if (auth()->user() && !auth()->user()->isAdmin() && $product->seller_id !== auth()->id()) {
            abort(403);
        }

        // Delete image
        if ($product->main_image) {
            Storage::disk('public')->delete($product->main_image);
        }

        $product->delete();

        return redirect()->route('products.index')
            ->with('success', __('products.deleted_successfully'));
    }

    /**
     * Approve product (admin only)
     */
    public function approve(Product $product)
    {
        if (!auth()->user() || !auth()->user()->isAdmin()) {
            abort(403);
        }

        $product->update(['is_active' => true]);

        return back()->with('success', __('products.approved_successfully'));
    }

    /**
     * Reject product (admin only)
     */
    public function reject(Product $product)
    {
        if (!auth()->user() || !auth()->user()->isAdmin()) {
            abort(403);
        }

        $product->update(['is_active' => false]);

        return back()->with('success', __('products.rejected_successfully'));
    }
}
