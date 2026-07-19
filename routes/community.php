<?php

declare(strict_types=1);

use App\Http\Admin\Controllers\CommunityCommentController;
use App\Http\Admin\Controllers\CommunitySubscriberController;
use App\Http\Public\Controllers\CommentController;
use App\Http\Public\Controllers\NewsletterConfirmationController;
use App\Http\Public\Controllers\NewsletterSubscriptionController;
use App\Http\Public\Controllers\NewsletterUnsubscribeController;
use Illuminate\Support\Facades\Route;

Route::post('newsletter/subscribe', [NewsletterSubscriptionController::class, 'store'])
    ->middleware('throttle:newsletter')
    ->name('newsletter.subscribe');
Route::get('newsletter/confirm/{token}', [NewsletterConfirmationController::class, 'show'])
    ->where('token', '[A-Za-z0-9]{64}')
    ->name('newsletter.confirm.show');
Route::post('newsletter/confirm/{token}', [NewsletterConfirmationController::class, 'store'])
    ->where('token', '[A-Za-z0-9]{64}')
    ->name('newsletter.confirm.store');
Route::get('newsletter/confirmed', [NewsletterConfirmationController::class, 'confirmed'])
    ->name('newsletter.confirmed');
Route::get('newsletter/unsubscribe/{token}', [NewsletterUnsubscribeController::class, 'show'])
    ->where('token', '[A-Za-z0-9]{64}')
    ->name('newsletter.unsubscribe.show');
Route::post('newsletter/unsubscribe/{token}', [NewsletterUnsubscribeController::class, 'store'])
    ->where('token', '[A-Za-z0-9]{64}')
    ->name('newsletter.unsubscribe.store');
Route::get('newsletter/unsubscribed', [NewsletterUnsubscribeController::class, 'unsubscribed'])
    ->name('newsletter.unsubscribed');

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::post('posts/{post:slug}/comments', [CommentController::class, 'store'])
        ->middleware('throttle:comments')
        ->name('public.comments.store');
    Route::patch('comments/{comment}', [CommentController::class, 'update'])
        ->name('public.comments.update');
    Route::delete('comments/{comment}', [CommentController::class, 'destroy'])
        ->name('public.comments.destroy');
});

Route::middleware(['auth', 'verified', 'access-author-portal'])->group(function (): void {
    Route::get('community/comments', [CommunityCommentController::class, 'index'])
        ->name('community.comments.index');
    Route::patch('community/comments/{comment}', [CommunityCommentController::class, 'update'])
        ->name('community.comments.update');
    Route::get('community/subscribers', [CommunitySubscriberController::class, 'index'])
        ->name('community.subscribers.index');
    Route::post('community/subscribers/{subscriber}/resend', [CommunitySubscriberController::class, 'resend'])
        ->name('community.subscribers.resend');
    Route::delete('community/subscribers/{subscriber}', [CommunitySubscriberController::class, 'unsubscribe'])
        ->name('community.subscribers.unsubscribe');
    Route::get('community/subscribers/export', [CommunitySubscriberController::class, 'export'])
        ->name('community.subscribers.export');
});
