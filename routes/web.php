<?php

declare(strict_types=1);

use App\Http\Admin\Controllers\AnalyticsDashboardController;
use App\Http\Admin\Controllers\AnalyticsInsightController;
use App\Http\Admin\Controllers\AnalyticsRealtimeController;
use App\Http\Admin\Controllers\AnalyticsSettingsController;
use App\Http\Admin\Controllers\Assistant\AssistantCancelController;
use App\Http\Admin\Controllers\Assistant\AssistantChangeController;
use App\Http\Admin\Controllers\Assistant\AssistantChatController;
use App\Http\Admin\Controllers\Assistant\AssistantImageController;
use App\Http\Admin\Controllers\Assistant\AssistantMetadataController;
use App\Http\Admin\Controllers\Assistant\AssistantOutlineController;
use App\Http\Admin\Controllers\Assistant\AssistantStateController;
use App\Http\Admin\Controllers\Assistant\AssistantTransformController;
use App\Http\Admin\Controllers\Assistant\AuthoringArtifactController;
use App\Http\Admin\Controllers\Assistant\AuthoringDraftController;
use App\Http\Admin\Controllers\Assistant\AuthoringImageCompositionController;
use App\Http\Admin\Controllers\Assistant\AuthoringImageFlowController;
use App\Http\Admin\Controllers\Assistant\AuthoringImageGuidanceController;
use App\Http\Admin\Controllers\Assistant\AuthoringImagePassageController;
use App\Http\Admin\Controllers\Assistant\AuthoringPlanController;
use App\Http\Admin\Controllers\Assistant\AuthoringResearchController;
use App\Http\Admin\Controllers\Assistant\AuthoringTaskController;
use App\Http\Admin\Controllers\CategoryController;
use App\Http\Admin\Controllers\ContactSubmissionController;
use App\Http\Admin\Controllers\PostAuthoringJourneyController;
use App\Http\Admin\Controllers\PostAutosaveController;
use App\Http\Admin\Controllers\PostController;
use App\Http\Admin\Controllers\PostFeatureController;
use App\Http\Admin\Controllers\PostFeaturedImageController;
use App\Http\Admin\Controllers\PostInlineImageController;
use App\Http\Admin\Controllers\PostMediaController;
use App\Http\Admin\Controllers\PostOgImageController;
use App\Http\Admin\Controllers\PostPublishingController;
use App\Http\Admin\Controllers\PostRevisionController;
use App\Http\Admin\Controllers\PostSourceController;
use App\Http\Admin\Controllers\PostTrashController;
use App\Http\Admin\Controllers\TagController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'access-author-portal'])->group(function (): void {
    Route::get('dashboard', [AnalyticsDashboardController::class, 'index'])->name('dashboard');
    Route::get('dashboard/posts', [AnalyticsDashboardController::class, 'posts'])->name('dashboard.posts.index');
    Route::get('dashboard/posts/{post}', [AnalyticsDashboardController::class, 'post'])->name('dashboard.posts.show');
    Route::get('dashboard/audience', [AnalyticsDashboardController::class, 'audience'])->name('dashboard.audience');
    Route::get('dashboard/achievements', [AnalyticsDashboardController::class, 'achievements'])->name('dashboard.achievements');
    Route::patch('dashboard/insights/{insight}', [AnalyticsInsightController::class, 'update'])->name('dashboard.insights.update');
    Route::get('dashboard/analytics/settings', [AnalyticsSettingsController::class, 'edit'])->name('dashboard.analytics.settings.edit');
    Route::patch('dashboard/analytics/settings', [AnalyticsSettingsController::class, 'update'])->name('dashboard.analytics.settings.update');
    Route::get('dashboard/analytics/realtime', AnalyticsRealtimeController::class)->middleware('throttle:analytics-realtime')->name('dashboard.analytics.realtime');
    Route::post('dashboard/analytics/snapshots', [AnalyticsDashboardController::class, 'requestSnapshot'])->name('dashboard.analytics.snapshots.store');
    Route::get('dashboard/analytics/snapshots/{preparation}', [AnalyticsDashboardController::class, 'snapshotStatus'])->name('dashboard.analytics.snapshots.show');

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
    Route::get('posts/{post}/authoring-journey', [PostAuthoringJourneyController::class, 'show'])->name('posts.authoring-journey.show');
    Route::patch('posts/{post}/authoring-journey', [PostAuthoringJourneyController::class, 'update'])->name('posts.authoring-journey.update');
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
    Route::get('posts/{post}/sources', [PostSourceController::class, 'index'])->name('posts.sources.index');
    Route::post('posts/{post}/sources/notes', [PostSourceController::class, 'storeNote'])->name('posts.sources.notes.store');
    Route::post('posts/{post}/sources/urls', [PostSourceController::class, 'storeUrl'])->name('posts.sources.urls.store');
    Route::post('posts/{post}/sources/documents', [PostSourceController::class, 'storeDocuments'])->name('posts.sources.documents.store');
    Route::get('posts/{post}/sources/{source}/download', [PostSourceController::class, 'download'])->name('posts.sources.download');
    Route::patch('posts/{post}/sources/{source}', [PostSourceController::class, 'update'])->name('posts.sources.update');
    Route::delete('posts/{post}/sources/{source}', [PostSourceController::class, 'destroy'])->name('posts.sources.destroy');

    Route::get('posts/{post}/assistant', AssistantStateController::class)->name('posts.assistant.state');
    Route::post('posts/{post}/assistant/cancel', AssistantCancelController::class)->name('posts.assistant.cancel');
    Route::post('posts/{post}/assistant/changes/{change}/accept', [AssistantChangeController::class, 'accept'])->name('posts.assistant.changes.accept');
    Route::post('posts/{post}/assistant/changes/{change}/reject', [AssistantChangeController::class, 'reject'])->name('posts.assistant.changes.reject');
    Route::get('posts/{post}/assistant/authoring', [AuthoringArtifactController::class, 'index'])->name('posts.assistant.authoring.index');
    Route::get('posts/{post}/assistant/authoring/tasks/{task}', [AuthoringTaskController::class, 'show'])->name('posts.assistant.authoring.tasks.show');
    Route::get('posts/{post}/assistant/authoring/drafts/{artifact}', [AuthoringDraftController::class, 'show'])->name('posts.assistant.authoring.drafts.show');
    Route::post('posts/{post}/assistant/authoring/plans/{artifact}/select', [AuthoringPlanController::class, 'select'])->name('posts.assistant.authoring.plans.select');
    Route::put('posts/{post}/assistant/authoring/plans/{artifact}/image-flow', [AuthoringImageFlowController::class, 'update'])->name('posts.assistant.authoring.image-flow.update');
    Route::patch('posts/{post}/assistant/authoring/image-compositions/{composition}', [AuthoringImageCompositionController::class, 'update'])->name('posts.assistant.authoring.image-compositions.update');
    Route::post('posts/{post}/assistant/authoring/drafts/{artifact}/select', [AuthoringDraftController::class, 'select'])->name('posts.assistant.authoring.drafts.select');
    Route::post('posts/{post}/assistant/authoring/drafts/{artifact}/apply', [AuthoringDraftController::class, 'apply'])->name('posts.assistant.authoring.drafts.apply');

    // Each of these spawns a metered Claude Code process; the per-post busy
    // guard stops concurrent turns on one post, and this caps how fast an
    // author can fan out turns across posts.
    Route::middleware('throttle:assistant')->group(function (): void {
        Route::post('posts/{post}/assistant/chat', AssistantChatController::class)->name('posts.assistant.chat');
        Route::post('posts/{post}/assistant/metadata', AssistantMetadataController::class)->name('posts.assistant.metadata');
        Route::post('posts/{post}/assistant/transform', AssistantTransformController::class)->name('posts.assistant.transform');
        Route::post('posts/{post}/assistant/outline', AssistantOutlineController::class)->name('posts.assistant.outline');
        Route::post('posts/{post}/assistant/images', AssistantImageController::class)->name('posts.assistant.images');
        Route::post('posts/{post}/assistant/authoring/plans', [AuthoringPlanController::class, 'store'])->name('posts.assistant.authoring.plans.store');
        Route::post('posts/{post}/assistant/authoring/plans/{artifact}/image-guidance', [AuthoringImageGuidanceController::class, 'store'])->name('posts.assistant.authoring.image-guidance.store');
        Route::post('posts/{post}/assistant/authoring/image-compositions/{composition}/passages', [AuthoringImagePassageController::class, 'store'])->name('posts.assistant.authoring.image-passages.store');
        Route::post('posts/{post}/assistant/authoring/plans/{artifact}/drafts', [AuthoringDraftController::class, 'store'])->name('posts.assistant.authoring.drafts.store');
        Route::post('posts/{post}/assistant/authoring/research', [AuthoringResearchController::class, 'store'])->name('posts.assistant.authoring.research.store');
    });

    Route::scopeBindings()->group(function (): void {
        Route::get('posts/{post}/media', [PostMediaController::class, 'index'])->name('posts.media.index');
        Route::post('posts/{post}/media', [PostMediaController::class, 'store'])->name('posts.media.store');
        Route::patch('posts/{post}/media/{media}', [PostMediaController::class, 'update'])->name('posts.media.update');
        Route::post('posts/{post}/media/{media}/feature', [PostMediaController::class, 'feature'])->name('posts.media.feature');
        Route::delete('posts/{post}/media/{media}', [PostMediaController::class, 'destroy'])->name('posts.media.destroy');
        Route::get('posts/{post}/revisions', [PostRevisionController::class, 'index'])->name('posts.revisions.index');
        Route::get('posts/{post}/revisions/{revision}', [PostRevisionController::class, 'show'])->name('posts.revisions.show');
        Route::post('posts/{post}/revisions/{revision}/restore', [PostRevisionController::class, 'restore'])->name('posts.revisions.restore');
    });
});

require __DIR__.'/settings.php';
require __DIR__.'/community.php';
require __DIR__.'/public.php';

Route::middleware(['auth', 'access-author-portal'])
    ->any('{adminPath}', static function (): never {
        abort(404);
    })
    ->where('adminPath', '(?:dashboard|settings|inbox|community|user)(?:/.*)?');
