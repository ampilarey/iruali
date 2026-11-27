<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductQuestion;
use App\Services\NotificationService;
use App\Support\CurrentShop;
use Illuminate\Http\Request;

class QuestionController extends Controller
{
    public function store(Request $request, Product $product)
    {
        abort_unless($product->is_active, 404);

        $data = $request->validate(['question' => 'required|string|min:10|max:500']);

        // Public on the product page: no phone numbers, emails or links (customers are served on iruali)
        $product->questions()->create(['question' => \App\Services\MessagingService::mask($data['question']), 'user_id' => $request->user()->id]);

        NotificationService::success(__('Question sent. The shop will answer it here.'));

        return redirect(route('products.show', $product).'#questions');
    }

    /**
     * The product's seller (its owner, or its staff whose role covers questions) or an admin answers
     * a question. answered_by is the person; answered_as_shop says the shop answered, not iruali.
     */
    public function answer(Request $request, ProductQuestion $question)
    {
        $user = $request->user();
        $shopId = (int) $question->product?->seller_id;
        $forShop = $shopId !== 0 && ($shopId === (int) $user->id || CurrentShop::worksFor($user, $shopId, 'seller.questions'));
        abort_unless($user->hasRole('admin') || $forShop, 403);

        $data = $request->validate(['answer' => 'required|string|min:2|max:2000']);

        // A shop's public answer can't take the customer off iruali with a number or a link (Seller Terms)
        $answer = $forShop ? \App\Services\MessagingService::mask($data['answer']) : $data['answer'];
        $question->forceFill(['answer' => $answer, 'answered_by' => $user->id, 'answered_at' => now(), 'answered_as_shop' => $forShop])->save();

        return back()->with('success', __('Answer published.'));
    }
}
