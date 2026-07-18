<?php

use App\Http\Controllers\Public\AuthorPageController;
use App\Http\Controllers\Public\CategoryPageController;
use App\Http\Controllers\Public\ContactController;
use App\Http\Controllers\Public\FeedController;
use App\Http\Controllers\Public\HomeController;
use App\Http\Controllers\Public\PostViewController;
use App\Http\Controllers\Public\SearchController;
use App\Http\Controllers\Public\SitemapController;
use App\Http\Controllers\Public\TagPageController;
use App\Support\Seo;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

Route::get('/', [HomeController::class, 'index'])->name('home');

Route::get('posts/{slug}', [PostViewController::class, 'show'])->name('public.posts.show');
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

Route::get('contact', [ContactController::class, 'create'])->name('public.contact.create');
Route::post('contact', [ContactController::class, 'store'])
    ->middleware('throttle:contact')
    ->name('public.contact.store');

Route::get('sitemap.xml', SitemapController::class)->name('public.sitemap');
Route::get('feed', FeedController::class)->name('public.feed');
