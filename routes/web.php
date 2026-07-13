<?php

use App\Http\Controllers\PostAutosaveController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\PostFeaturedImageController;
use App\Http\Controllers\PostPublishingController;
use App\Http\Controllers\PostRevisionController;
use App\Http\Controllers\PostTrashController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

Route::get('/', fn (): Response => Inertia::render('welcome'))->name('home');

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('dashboard', fn (): Response => Inertia::render('dashboard'))->name('dashboard');

    Route::get('posts/trash', [PostTrashController::class, 'index'])->name('posts.trash.index');
    Route::post('posts/{post}/restore', [PostTrashController::class, 'restore'])->withTrashed()->name('posts.restore');
    Route::delete('posts/{post}/force', [PostTrashController::class, 'forceDestroy'])->withTrashed()->name('posts.force-destroy');

    Route::resource('posts', PostController::class)->only(['index', 'store', 'edit', 'update', 'destroy']);
    Route::patch('posts/{post}/autosave', PostAutosaveController::class)->name('posts.autosave');
    Route::get('posts/{post}/preview', [PostPublishingController::class, 'preview'])->name('posts.preview');
    Route::post('posts/{post}/publish', [PostPublishingController::class, 'publish'])->name('posts.publish');
    Route::post('posts/{post}/schedule', [PostPublishingController::class, 'schedule'])->name('posts.schedule');
    Route::post('posts/{post}/unpublish', [PostPublishingController::class, 'unpublish'])->name('posts.unpublish');
    Route::post('posts/{post}/featured-image', [PostFeaturedImageController::class, 'store'])->name('posts.featured-image.store');
    Route::delete('posts/{post}/featured-image', [PostFeaturedImageController::class, 'destroy'])->name('posts.featured-image.destroy');

    Route::scopeBindings()->group(function (): void {
        Route::get('posts/{post}/revisions', [PostRevisionController::class, 'index'])->name('posts.revisions.index');
        Route::get('posts/{post}/revisions/{revision}', [PostRevisionController::class, 'show'])->name('posts.revisions.show');
        Route::post('posts/{post}/revisions/{revision}/restore', [PostRevisionController::class, 'restore'])->name('posts.revisions.restore');
    });
});

require __DIR__.'/settings.php';
