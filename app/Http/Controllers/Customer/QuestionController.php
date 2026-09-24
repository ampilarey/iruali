<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductQuestion;
use App\Services\NotificationService;
use Illuminate\Http\Request;

class QuestionController extends Controller
{
    public function store(Request $request, Product $product)
    {
        abort_unless($product->is_active, 404);

        $data = $request->validate(['question' => 'required|string|min:10|max:500']);

        $product->questions()->create($data + ['user_id' => $request->user()->id]);

        NotificationService::success(__('Question sent. The shop will answer it here.'));

        return redirect(route('products.show', $product).'#questions');
    }

    /**
     * The product's seller (or an admin) answers a question.
     */
    public function answer(Request $request, ProductQuestion $question)
    {
        $user = $request->user();
        abort_unless($user->hasRole('admin') || $question->product?->seller_id === $user->id, 403);

        $data = $request->validate(['answer' => 'required|string|min:2|max:2000']);

        $question->update($data + ['answered_by' => $user->id, 'answered_at' => now()]);

        return back()->with('success', __('Answer published.'));
    }
}
