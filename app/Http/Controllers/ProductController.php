<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use App\Traits\SecureFileUpload;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;

class ProductController extends Controller
{
    use SecureFileUpload;

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
        $categories = Category::active()->get();
        $islands = \App\Models\Island::where('is_active', true)->get();
        return view('products.create', compact('categories', 'islands'));
    }

    /**
     * Store a newly created product
     */
    public function store(StoreProductRequest $request)
    {
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
            $file = $request->file('main_image');
            $filePath = $this->storeFileSecurely($file, 'products');
            
            if (!$filePath) {
                return back()->withErrors(['main_image' => 'Invalid file type or size. Only JPEG, PNG, JPG, and GIF up to 2MB are allowed.']);
            }
            
            $data['main_image'] = $filePath;
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
                    $file = $variant['image'];
                    $filePath = $this->storeFileSecurely($file, 'variants');
                    
                    if ($filePath) {
                        $variantData['image'] = $filePath;
                    }
                    // Skip this variant if invalid file type/size
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
        $this->authorize('update', $product);

        $categories = Category::active()->get();
        $islands = \App\Models\Island::where('is_active', true)->get();
        $product->load('islands');
        return view('products.edit', compact('product', 'categories', 'islands'));
    }

    /**
     * Update the specified product
     */
    public function update(UpdateProductRequest $request, Product $product)
    {
        $this->authorize('update', $product);

        $data = $request->all();
        
        // Generate slug from English name
        $data['slug'] = Str::slug($data['name']['en']);

        // Handle image upload
        if ($request->hasFile('main_image')) {
            // Delete old image
            $this->deleteFile($product->main_image);
            
            $file = $request->file('main_image');
            $filePath = $this->storeFileSecurely($file, 'products');
            
            if (!$filePath) {
                return back()->withErrors(['main_image' => 'Invalid file type or size. Only JPEG, PNG, JPG, and GIF up to 2MB are allowed.']);
            }
            
            $data['main_image'] = $filePath;
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
                    $file = $variant['image'];
                    $filePath = $this->storeFileSecurely($file, 'variants');
                    
                    if ($filePath) {
                        $variantData['image'] = $filePath;
                    }
                    // Skip this variant if invalid file type/size
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
        $this->authorize('delete', $product);

        // Delete image
        $this->deleteFile($product->main_image);

        $product->delete();

        return redirect()->route('products.index')
            ->with('success', __('products.deleted_successfully'));
    }

    /**
     * Approve product (admin only)
     */
    public function approve(Product $product)
    {
        $this->authorize('approve', $product);

        $product->update(['is_active' => true]);

        return redirect()->route('products.index')
            ->with('success', __('products.approved_successfully'));
    }

    /**
     * Reject product (admin only)
     */
    public function reject(Product $product)
    {
        $this->authorize('reject', $product);

        $product->update(['is_active' => false]);

        return redirect()->route('products.index')
            ->with('success', __('products.rejected_successfully'));
    }
}
