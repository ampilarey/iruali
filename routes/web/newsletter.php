<?php

use App\Http\Controllers\Admin\NewsletterIssueController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin → Moderation → Newsletter: write, preview, test and send newsletters
|--------------------------------------------------------------------------
| Named admin.newsletter-issues.* (not admin.newsletter.*), so the support role, which may see
| the subscriber list, cannot send. The footer signup, its confirmation link and the unsubscribe
| link are storefront pages in routes/web.php.
*/

Route::middleware(['auth', 'staff', 'staff.2fa'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/newsletter/issues/create', [NewsletterIssueController::class, 'create'])->name('newsletter-issues.create');
    Route::post('/newsletter/issues', [NewsletterIssueController::class, 'store'])->name('newsletter-issues.store');
    Route::get('/newsletter/issues/{issue}', [NewsletterIssueController::class, 'show'])->name('newsletter-issues.show')->whereNumber('issue');
    Route::get('/newsletter/issues/{issue}/edit', [NewsletterIssueController::class, 'edit'])->name('newsletter-issues.edit')->whereNumber('issue');
    Route::put('/newsletter/issues/{issue}', [NewsletterIssueController::class, 'update'])->name('newsletter-issues.update')->whereNumber('issue');
    Route::get('/newsletter/issues/{issue}/preview', [NewsletterIssueController::class, 'preview'])->name('newsletter-issues.preview')->whereNumber('issue');
    Route::post('/newsletter/issues/{issue}/test', [NewsletterIssueController::class, 'test'])->name('newsletter-issues.test')->whereNumber('issue')->middleware('throttle:10,1');
    Route::post('/newsletter/issues/{issue}/send', [NewsletterIssueController::class, 'send'])->name('newsletter-issues.send')->whereNumber('issue');
    Route::post('/newsletter/issues/{issue}/retry', [NewsletterIssueController::class, 'retry'])->name('newsletter-issues.retry')->whereNumber('issue');
    Route::delete('/newsletter/issues/{issue}', [NewsletterIssueController::class, 'destroy'])->name('newsletter-issues.destroy')->whereNumber('issue');
});
