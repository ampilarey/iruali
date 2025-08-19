<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Product;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_check_endpoint()
    {
        $response = $this->getJson('/api/health');

        $response->assertStatus(200)
                ->assertJson([
                    'status' => 'healthy',
                    'version' => '1.0.0'
                ]);
    }

    public function test_products_endpoint()
    {
        // Create test data
        $category = Category::factory()->create();
        $seller = User::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'seller_id' => $seller->id,
        ]);

        $response = $this->getJson('/api/v1/products');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'products',
                        'pagination' => [
                            'current_page',
                            'last_page',
                            'per_page',
                            'total',
                            'from',
                            'to',
                        ]
                    ],
                    'message'
                ]);
    }

    public function test_categories_endpoint()
    {
        $category = Category::factory()->create();

        $response = $this->getJson('/api/v1/categories');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data',
                    'message'
                ]);
    }

    public function test_search_endpoint()
    {
        // Create test data
        $category = Category::factory()->create();
        $seller = User::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'seller_id' => $seller->id,
        ]);

        $response = $this->getJson('/api/v1/search?q=test');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'query',
                        'products',
                        'pagination'
                    ],
                    'message'
                ]);
    }

    public function test_authentication_required_for_protected_endpoints()
    {
        $response = $this->getJson('/api/v1/cart');

        $response->assertStatus(401);
    }

    public function test_user_registration()
    {
        $userData = [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        $response = $this->postJson('/api/v1/register', $userData);

        $response->assertStatus(201)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'user' => [
                            'id',
                            'name',
                            'email',
                        ],
                        'token',
                        'token_type'
                    ],
                    'message'
                ]);
    }

    public function test_user_login()
    {
        $user = User::factory()->create([
            'password' => bcrypt('password123'),
        ]);

        $loginData = [
            'email' => $user->email,
            'password' => 'password123',
        ];

        $response = $this->postJson('/api/v1/login', $loginData);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'user',
                        'token',
                        'token_type'
                    ],
                    'message'
                ]);
    }
}
