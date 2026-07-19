<?php

use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ContactSubmissionController;
use App\Http\Controllers\PostAutosaveController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\PostFeatureController;
use App\Http\Controllers\PostFeaturedImageController;
use App\Http\Controllers\PostInlineImageController;
use App\Http\Controllers\PostOgImageController;
use App\Http\Controllers\PostPublishingController;
use App\Http\Controllers\PostRevisionController;
use App\Http\Controllers\PostTrashController;
use App\Http\Controllers\TagController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('dashboard', fn (): Response => Inertia::render('dashboard'))->name('dashboard');

    Route::get('posts/trash', [PostTrashController::class, 'index'])->name('posts.trash.index');
    Route::post('posts/{post}/restore', [PostTrashController::class, 'restore'])->withTrashed()->name('posts.restore');
    Route::delete('posts/{post}/force', [PostTrashController::class, 'forceDestroy'])->withTrashed()->name('posts.force-destroy');

    Route::resource('posts', PostController::class)->only(['index', 'store', 'edit', 'update', 'destroy']);
    Route::resource('categories', CategoryController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('tags', TagController::class)->only(['index', 'store', 'update', 'destroy']);

    Route::get('inbox', [ContactSubmissionController::class, 'index'])->name('contact-submissions.index');
    Route::patch('inbox/{contactSubmission}/read', [ContactSubmissionController::class, 'markRead'])->name('contact-submissions.read');
    Route::delete('inbox/{contactSubmission}', [ContactSubmissionController::class, 'destroy'])->name('contact-submissions.destroy');
    Route::patch('posts/{post}/autosave', PostAutosaveController::class)->name('posts.autosave');
    Route::get('posts/{post}/preview', [PostPublishingController::class, 'preview'])->name('posts.preview');
    Route::post('posts/{post}/publish', [PostPublishingController::class, 'publish'])->name('posts.publish');
    Route::post('posts/{post}/schedule', [PostPublishingController::class, 'schedule'])->name('posts.schedule');
    Route::post('posts/{post}/unpublish', [PostPublishingController::class, 'unpublish'])->name('posts.unpublish');
    Route::post('posts/{post}/featured-image', [PostFeaturedImageController::class, 'store'])->name('posts.featured-image.store');
    Route::delete('posts/{post}/featured-image', [PostFeaturedImageController::class, 'destroy'])->name('posts.featured-image.destroy');
    Route::post('posts/{post}/og-image', [PostOgImageController::class, 'store'])->name('posts.og-image.store');
    Route::delete('posts/{post}/og-image', [PostOgImageController::class, 'destroy'])->name('posts.og-image.destroy');
    Route::post('posts/{post}/feature', [PostFeatureController::class, 'store'])->name('posts.feature.store');
    Route::delete('posts/{post}/feature', [PostFeatureController::class, 'destroy'])->name('posts.feature.destroy');
    Route::post('posts/{post}/inline-images', [PostInlineImageController::class, 'store'])->name('posts.inline-images.store');

    Route::scopeBindings()->group(function (): void {
        Route::get('posts/{post}/revisions', [PostRevisionController::class, 'index'])->name('posts.revisions.index');
        Route::get('posts/{post}/revisions/{revision}', [PostRevisionController::class, 'show'])->name('posts.revisions.show');
        Route::post('posts/{post}/revisions/{revision}/restore', [PostRevisionController::class, 'restore'])->name('posts.revisions.restore');
    });
});

require __DIR__.'/settings.php';
require __DIR__.'/public.php';
