<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Product;
use App\Models\Category;
use App\Models\ProductImage;
use Illuminate\Support\Str;

class SampleProductsSeeder extends Seeder
{
    public function run(): void
    {
        $categories = Category::all();
        
        foreach ($categories as $category) {
            $this->addProductsToCategory($category);
        }
    }
    
    private function addProductsToCategory(Category $category)
    {
        $products = $this->getProductsForCategory($category->name);
        
        foreach ($products as $productData) {
            $product = Product::create([
                'name' => ['en' => $productData['name']],
                'slug' => Str::slug($productData['name']) . '-' . Str::random(4),
                'description' => ['en' => $productData['description']],
                'price' => $productData['price'],
                'sale_price' => $productData['sale_price'] ?? null,
                'sku' => 'SKU-' . Str::random(8),
                'stock_quantity' => rand(10, 100),
                'category_id' => $category->id,
                'seller_id' => 1, // Assuming user ID 1 exists
                'is_active' => true,
                'is_featured' => rand(0, 1),
                'weight' => rand(0.1, 5.0),
                'dimensions' => json_encode([
                    'length' => rand(10, 50),
                    'width' => rand(10, 50),
                    'height' => rand(10, 50)
                ]),
                'meta_title' => $productData['name'] . ' - iruali',
                'meta_description' => $productData['description'],
            ]);
            
            // Add product image
            if (isset($productData['image'])) {
                ProductImage::create([
                    'product_id' => $product->id,
                    'url' => $productData['image'],
                    'is_main' => true,
                    'alt_text' => $productData['name'],
                    'sort_order' => 1,
                ]);
            }
        }
    }
    
    private function getProductsForCategory(string $categoryName): array
    {
        switch (strtolower($categoryName)) {
            case 'electronics':
                return [
                    [
                        'name' => 'Wireless Bluetooth Headphones',
                        'description' => 'Premium wireless headphones with noise cancellation and 30-hour battery life.',
                        'price' => 89.99,
                        'sale_price' => 69.99,
                        'image' => 'images/products/headphones.svg',
                        'keywords' => 'headphones, wireless, bluetooth, noise cancellation'
                    ],
                    [
                        'name' => 'Smart LED TV 55"',
                        'description' => '4K Ultra HD Smart TV with HDR and built-in streaming apps.',
                        'price' => 599.99,
                        'sale_price' => 499.99,
                        'image' => 'images/products/tv.svg',
                        'keywords' => 'tv, 4k, smart tv, led, hdr'
                    ],
                    [
                        'name' => 'Portable Bluetooth Speaker',
                        'description' => 'Waterproof portable speaker with 360° sound and 20-hour battery.',
                        'price' => 49.99,
                        'image' => 'images/products/speaker.svg',
                        'keywords' => 'speaker, bluetooth, portable, waterproof'
                    ]
                ];
                
            case 'clothing':
                return [
                    [
                        'name' => 'Classic Denim Jacket',
                        'description' => 'Timeless denim jacket perfect for any casual occasion.',
                        'price' => 79.99,
                        'sale_price' => 59.99,
                        'image' => 'images/products/denim-jacket.svg',
                        'keywords' => 'jacket, denim, casual, classic'
                    ],
                    [
                        'name' => 'Premium Cotton T-Shirt',
                        'description' => 'Soft, breathable cotton t-shirt available in multiple colors.',
                        'price' => 24.99,
                        'image' => 'images/products/tshirt.jpg',
                        'keywords' => 'tshirt, cotton, casual, comfortable'
                    ]
                ];
                
            case 'home & garden':
                return [
                    [
                        'name' => 'Modern Coffee Table',
                        'description' => 'Elegant wooden coffee table with storage shelf.',
                        'price' => 199.99,
                        'sale_price' => 159.99,
                        'image' => 'images/products/coffee-table.svg',
                        'keywords' => 'furniture, coffee table, wooden, modern'
                    ],
                    [
                        'name' => 'Indoor Plant Set',
                        'description' => 'Set of 3 low-maintenance indoor plants with decorative pots.',
                        'price' => 39.99,
                        'image' => 'images/products/plants.jpg',
                        'keywords' => 'plants, indoor, decorative, low maintenance'
                    ]
                ];
                
            case 'sports & outdoors':
                return [
                    [
                        'name' => 'Professional Yoga Mat',
                        'description' => 'Non-slip yoga mat with alignment lines and carrying strap.',
                        'price' => 34.99,
                        'image' => 'images/products/yoga-mat.jpg',
                        'keywords' => 'yoga, mat, fitness, exercise'
                    ],
                    [
                        'name' => 'Hiking Backpack 30L',
                        'description' => 'Lightweight hiking backpack with multiple compartments.',
                        'price' => 89.99,
                        'sale_price' => 69.99,
                        'image' => 'images/products/backpack.jpg',
                        'keywords' => 'backpack, hiking, outdoor, lightweight'
                    ]
                ];
                
            case 'books & media':
                return [
                    [
                        'name' => 'Bestselling Novel Collection',
                        'description' => 'Set of 3 bestselling novels in hardcover.',
                        'price' => 49.99,
                        'image' => 'images/products/books.jpg',
                        'keywords' => 'books, novels, hardcover, collection'
                    ],
                    [
                        'name' => 'Wireless Gaming Mouse',
                        'description' => 'High-precision gaming mouse with customizable RGB lighting.',
                        'price' => 79.99,
                        'image' => 'images/products/gaming-mouse.jpg',
                        'keywords' => 'gaming, mouse, wireless, rgb'
                    ]
                ];
                
            case 'health & beauty':
                return [
                    [
                        'name' => 'Organic Face Cream',
                        'description' => 'Natural face cream with anti-aging properties.',
                        'price' => 29.99,
                        'image' => 'images/products/face-cream.jpg',
                        'keywords' => 'beauty, face cream, organic, anti-aging'
                    ],
                    [
                        'name' => 'Electric Toothbrush Set',
                        'description' => 'Sonic electric toothbrush with travel case and replacement heads.',
                        'price' => 59.99,
                        'sale_price' => 44.99,
                        'image' => 'images/products/toothbrush.jpg',
                        'keywords' => 'dental, electric toothbrush, sonic, travel'
                    ]
                ];
                
            case 'smartphones':
                return [
                    [
                        'name' => 'Latest Smartphone Pro',
                        'description' => 'Flagship smartphone with advanced camera system and 5G.',
                        'price' => 999.99,
                        'sale_price' => 899.99,
                        'image' => 'images/products/smartphone.jpg',
                        'keywords' => 'smartphone, 5g, camera, flagship'
                    ],
                    [
                        'name' => 'Wireless Charging Pad',
                        'description' => 'Fast wireless charging pad compatible with all Qi devices.',
                        'price' => 39.99,
                        'image' => 'images/products/charging-pad.jpg',
                        'keywords' => 'charging, wireless, qi, fast charge'
                    ]
                ];
                
            case 'laptops':
                return [
                    [
                        'name' => 'Ultrabook Laptop 14"',
                        'description' => 'Lightweight laptop with Intel i7 processor and 16GB RAM.',
                        'price' => 1299.99,
                        'sale_price' => 1099.99,
                        'image' => 'images/products/laptop.jpg',
                        'keywords' => 'laptop, ultrabook, intel, lightweight'
                    ],
                    [
                        'name' => 'Laptop Stand & Cooling Pad',
                        'description' => 'Adjustable laptop stand with built-in cooling fans.',
                        'price' => 49.99,
                        'image' => 'images/products/laptop-stand.jpg',
                        'keywords' => 'laptop stand, cooling, adjustable, ergonomic'
                    ]
                ];
                
            case 'men\'s clothing':
                return [
                    [
                        'name' => 'Formal Business Suit',
                        'description' => 'Professional business suit perfect for formal occasions.',
                        'price' => 299.99,
                        'sale_price' => 249.99,
                        'image' => 'images/products/business-suit.jpg',
                        'keywords' => 'suit, formal, business, professional'
                    ],
                    [
                        'name' => 'Casual Polo Shirt',
                        'description' => 'Comfortable polo shirt made from breathable fabric.',
                        'price' => 34.99,
                        'image' => 'images/products/polo-shirt.jpg',
                        'keywords' => 'polo, shirt, casual, breathable'
                    ]
                ];
                
            case 'women\'s clothing':
                return [
                    [
                        'name' => 'Elegant Evening Dress',
                        'description' => 'Beautiful evening dress perfect for special occasions.',
                        'price' => 199.99,
                        'sale_price' => 159.99,
                        'image' => 'images/products/evening-dress.jpg',
                        'keywords' => 'dress, evening, elegant, formal'
                    ],
                    [
                        'name' => 'Comfortable Leggings',
                        'description' => 'High-quality leggings perfect for workout or casual wear.',
                        'price' => 29.99,
                        'image' => 'images/products/leggings.jpg',
                        'keywords' => 'leggings, workout, comfortable, casual'
                    ]
                ];
                
            default:
                return [
                    [
                        'name' => 'Sample Product',
                        'description' => 'This is a sample product for demonstration purposes.',
                        'price' => 29.99,
                        'image' => 'images/products/sample.jpg',
                        'keywords' => 'sample, product, demo'
                    ]
                ];
        }
    }
}
