<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Product;
use App\Models\Category;
use App\Models\Order;
use App\Models\Cart;
use App\Http\Resources\ProductResource;
use App\Http\Resources\OrderResource;
use App\Http\Resources\UserResource;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\CartResource;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ApiResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_resource_transforms_data_correctly()
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'seller_id' => $user->id,
        ]);

        $resource = new ProductResource($product);
        $data = $resource->toArray(request());

        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('name', $data);
        $this->assertArrayHasKey('price', $data);
        $this->assertEquals($product->id, $data['id']);
        $this->assertEquals($product->name, $data['name']);
    }

    public function test_order_resource_transforms_data_correctly()
    {
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
        ]);

        $resource = new OrderResource($order);
        $data = $resource->toArray(request());

        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('order_number', $data);
        $this->assertArrayHasKey('status', $data);
        $this->assertArrayHasKey('total_amount', $data);
        $this->assertEquals($order->id, $data['id']);
        $this->assertEquals($order->order_number, $data['order_number']);
    }

    public function test_user_resource_transforms_data_correctly()
    {
        $user = User::factory()->create();

        $resource = new UserResource($user);
        $data = $resource->toArray(request());

        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('name', $data);
        $this->assertArrayHasKey('email', $data);
        $this->assertEquals($user->id, $data['id']);
        $this->assertEquals($user->name, $data['name']);
    }

    public function test_category_resource_transforms_data_correctly()
    {
        $category = Category::factory()->create();

        $resource = new CategoryResource($category);
        $data = $resource->toArray(request());

        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('name', $data);
        $this->assertArrayHasKey('slug', $data);
        $this->assertEquals($category->id, $data['id']);
        $this->assertEquals($category->name, $data['name']);
    }

    public function test_cart_resource_transforms_data_correctly()
    {
        $user = User::factory()->create();
        $cart = Cart::factory()->create([
            'user_id' => $user->id,
        ]);

        $resource = new CartResource($cart);
        $data = $resource->toArray(request());

        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('user_id', $data);
        $this->assertEquals($cart->id, $data['id']);
        $this->assertEquals($cart->user_id, $data['user_id']);
    }

    public function test_product_resource_collection_works()
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();
        $products = Product::factory()->count(3)->create([
            'category_id' => $category->id,
            'seller_id' => $user->id,
        ]);

        $collection = ProductResource::collection($products);
        $data = $collection->toArray(request());

        $this->assertCount(3, $data);
        $this->assertArrayHasKey('id', $data[0]);
        $this->assertArrayHasKey('name', $data[0]);
    }

    public function test_order_resource_collection_works()
    {
        $user = User::factory()->create();
        $orders = Order::factory()->count(2)->create([
            'user_id' => $user->id,
        ]);

        $collection = OrderResource::collection($orders);
        $data = $collection->toArray(request());

        $this->assertCount(2, $data);
        $this->assertArrayHasKey('id', $data[0]);
        $this->assertArrayHasKey('order_number', $data[0]);
    }

    public function test_wishlist_item_resource_transforms_data_correctly()
    {
        $user = \App\Models\User::factory()->create();
        $category = \App\Models\Category::factory()->create();
        $product = \App\Models\Product::factory()->create([
            'category_id' => $category->id,
            'seller_id' => $user->id,
        ]);
        $wishlist = \App\Models\Wishlist::factory()->create([
            'user_id' => $user->id,
            'product_id' => $product->id,
        ]);
        $resource = new \App\Http\Resources\WishlistItemResource($wishlist);
        $data = $resource->toArray(request());
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('product', $data);
        $this->assertEquals($wishlist->id, $data['id']);
        $this->assertEquals($product->id, $data['product']['id']);
    }

    public function test_auth_user_resource_transforms_data_correctly()
    {
        $user = User::factory()->create();

        $resource = new \App\Http\Resources\AuthUserResource($user);
        $data = $resource->toArray(request());

        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('name', $data);
        $this->assertArrayHasKey('email', $data);
        $this->assertArrayHasKey('phone', $data);
        $this->assertArrayHasKey('loyalty_points', $data);
        $this->assertArrayHasKey('is_seller', $data);
        $this->assertArrayHasKey('seller_approved', $data);
        $this->assertEquals($user->id, $data['id']);
        $this->assertEquals($user->name, $data['name']);
        $this->assertEquals($user->email, $data['email']);
    }
} 