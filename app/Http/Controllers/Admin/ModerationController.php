<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NewsletterSubscriber;
use App\Models\ProductQuestion;
use App\Models\ProductReview;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Reviews and questions go live straight away; admins hide or remove the ones that shouldn't be there.
 */
class ModerationController extends Controller
{
    public function reviews(Request $request)
    {
        $reviews = ProductReview::query()
            ->with(['product', 'user'])
            ->when($request->query('show') === 'hidden', fn ($q) => $q->where('is_approved', false))
            ->when($request->query('show') === 'low', fn ($q) => $q->where('rating', '<=', 2))
            ->when($request->filled('q'), function ($q) use ($request) {
                $like = '%'.$request->query('q').'%';
                $q->where(fn ($w) => $w->where('comment', 'like', $like)->orWhere('title', 'like', $like)->orWhere('reviewer_name', 'like', $like));
            })
            ->latest()
            ->paginate(25)
            ->withQueryString();

        $counts = [
            'all' => ProductReview::count(),
            'hidden' => ProductReview::where('is_approved', false)->count(),
            'low' => ProductReview::where('rating', '<=', 2)->count(),
        ];

        return view('admin.moderation.reviews', compact('reviews', 'counts'));
    }

    public function toggleReview(ProductReview $review)
    {
        $review->update(['is_approved' => ! $review->is_approved]);

        return back()->with('success', $review->is_approved ? 'Review is visible again.' : 'Review hidden from the shop.');
    }

    public function destroyReview(ProductReview $review)
    {
        $review->delete();

        return back()->with('success', 'Review deleted.');
    }

    public function questions(Request $request)
    {
        $questions = ProductQuestion::query()
            ->with(['product', 'user', 'answerer'])
            ->when($request->query('show') !== 'all', fn ($q) => $q->whereNull('answer'))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        $unanswered = ProductQuestion::whereNull('answer')->count();

        return view('admin.moderation.questions', compact('questions', 'unanswered'));
    }

    public function destroyQuestion(ProductQuestion $question)
    {
        $question->delete();

        return back()->with('success', 'Question deleted.');
    }

    public function newsletter(Request $request)
    {
        if ($request->query('export') === 'csv') {
            return $this->exportNewsletter();
        }

        $subscribers = NewsletterSubscriber::latest()->paginate(50);

        return view('admin.moderation.newsletter', compact('subscribers'));
    }

    public function destroySubscriber(NewsletterSubscriber $subscriber)
    {
        $subscriber->delete();

        return back()->with('success', 'Subscriber removed.');
    }

    protected function exportNewsletter(): StreamedResponse
    {
        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['email', 'language', 'subscribed_at']);
            NewsletterSubscriber::orderBy('id')->chunk(500, function ($rows) use ($out) {
                foreach ($rows as $row) {
                    fputcsv($out, [$row->email, $row->locale, $row->created_at?->toDateTimeString()]);
                }
            });
            fclose($out);
        }, 'iruali-newsletter-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
    }
}
