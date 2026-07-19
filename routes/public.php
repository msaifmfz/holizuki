<?php

use App\Domain\Reading\Support\Seo;
use App\Http\Public\Controllers\ArchiveController;
use App\Http\Public\Controllers\AuthorPageController;
use App\Http\Public\Controllers\CategoryPageController;
use App\Http\Public\Controllers\ContactController;
use App\Http\Public\Controllers\FeedController;
use App\Http\Public\Controllers\HomeController;
use App\Http\Public\Controllers\PostViewController;
use App\Http\Public\Controllers\PostViewEventController;
use App\Http\Public\Controllers\ReaderAccountController;
use App\Http\Public\Controllers\RobotsTxtController;
use App\Http\Public\Controllers\SearchController;
use App\Http\Public\Controllers\SitemapController;
use App\Http\Public\Controllers\TagPageController;
use App\Http\Public\Controllers\TopicController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

Route::get('/', [HomeController::class, 'index'])->name('home');

Route::get('posts/{slug}', [PostViewController::class, 'show'])->name('public.posts.show');
Route::post('posts/{post:slug}/views', PostViewEventController::class)
    ->middleware('throttle:post-views')
    ->name('public.posts.views.store');
Route::get('topics', TopicController::class)->name('public.topics');
Route::get('archive/{year?}/{month?}', ArchiveController::class)
    ->where(['year' => '[0-9]{4}', 'month' => '(?:0[1-9]|1[0-2])'])
    ->name('public.archive');
Route::get('categories/{category:slug}', [CategoryPageController::class, 'show'])->name('public.categories.show');
Route::get('tags/{tag:slug}', [TagPageController::class, 'show'])->name('public.tags.show');
Route::get('authors/{user:author_slug}', [AuthorPageController::class, 'show'])->name('public.authors.show');
Route::get('search', SearchController::class)->name('public.search');

Route::get('about', fn (): Response => Inertia::render('public/about', [
    'seo' => Seo::make(title: 'About — '.Seo::siteName(), canonical: route('public.about')),
]))->name('public.about');

Route::get('privacy', fn (): Response => Inertia::render('public/privacy', [
    'seo' => Seo::make(title: 'Privacy Policy — '.Seo::siteName(), canonical: route('public.privacy')),
]))->name('public.privacy');

Route::get('terms', fn (): Response => Inertia::render('public/terms', [
    'seo' => Seo::make(title: 'Terms of Use — '.Seo::siteName(), canonical: route('public.terms')),
]))->name('public.terms');

Route::middleware('auth')->group(function (): void {
    Route::get('account', [ReaderAccountController::class, 'edit'])->name('reader.account.edit');
    Route::put('account/password', [ReaderAccountController::class, 'updatePassword'])
        ->middleware('throttle:6,1')
        ->name('reader.account.password.update');
    Route::delete('account', [ReaderAccountController::class, 'destroy'])->name('reader.account.destroy');
});

Route::get('contact', [ContactController::class, 'create'])->name('public.contact.create');
Route::post('contact', [ContactController::class, 'store'])
    ->middleware('throttle:contact')
    ->name('public.contact.store');

Route::get('sitemap.xml', SitemapController::class)->name('public.sitemap');
Route::get('feed', FeedController::class)->name('public.feed');
Route::get('robots.txt', RobotsTxtController::class)->name('public.robots');
