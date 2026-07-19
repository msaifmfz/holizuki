<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $addMetrics = static function (Blueprint $table): void {
            $table->unsignedBigInteger('readers')->default(0);
            $table->unsignedBigInteger('meaningful_readers')->default(0);
            $table->unsignedBigInteger('actioning_readers')->default(0);
            $table->unsignedBigInteger('sessions')->default(0);
            $table->unsignedBigInteger('page_views')->default(0);
            $table->unsignedBigInteger('article_progress_25')->default(0);
            $table->unsignedBigInteger('article_progress_50')->default(0);
            $table->unsignedBigInteger('article_progress_75')->default(0);
            $table->unsignedBigInteger('article_progress_90')->default(0);
            $table->unsignedBigInteger('article_engaged')->default(0);
            $table->unsignedBigInteger('select_content')->default(0);
            $table->unsignedBigInteger('shares')->default(0);
            $table->unsignedBigInteger('sign_ups')->default(0);
            $table->unsignedBigInteger('comment_submits')->default(0);
            $table->unsignedBigInteger('outbound_clicks')->default(0);
            $table->unsignedBigInteger('file_downloads')->default(0);
        };

        Schema::create('analytics_daily_site_metrics', function (Blueprint $table) use ($addMetrics): void {
            $table->id();
            $table->date('metric_date')->unique();
            $addMetrics($table);
            $table->timestamp('synced_at');
            $table->timestamps();
        });

        Schema::create('analytics_daily_post_metrics', function (Blueprint $table) use ($addMetrics): void {
            $table->id();
            $table->date('metric_date');
            $table->foreignId('post_id')->nullable()->constrained('posts')->nullOnDelete();
            $table->string('content_key', 64);
            $addMetrics($table);
            $table->timestamp('synced_at');
            $table->timestamps();

            $table->unique(['metric_date', 'content_key']);
            $table->index(['post_id', 'metric_date']);
        });

        Schema::create('analytics_daily_channel_metrics', function (Blueprint $table) use ($addMetrics): void {
            $table->id();
            $table->date('metric_date');
            $table->string('channel', 64);
            $addMetrics($table);
            $table->timestamp('synced_at');
            $table->timestamps();

            $table->unique(['metric_date', 'channel']);
        });

        Schema::create('analytics_weekly_site_metrics', function (Blueprint $table) use ($addMetrics): void {
            $table->id();
            $table->unsignedSmallInteger('iso_year');
            $table->unsignedTinyInteger('iso_week');
            $table->date('week_starts_on');
            $addMetrics($table);
            $table->timestamp('synced_at');
            $table->timestamps();

            $table->unique(['iso_year', 'iso_week']);
        });

        Schema::create('analytics_weekly_post_metrics', function (Blueprint $table) use ($addMetrics): void {
            $table->id();
            $table->unsignedSmallInteger('iso_year');
            $table->unsignedTinyInteger('iso_week');
            $table->date('week_starts_on');
            $table->foreignId('post_id')->nullable()->constrained('posts')->nullOnDelete();
            $table->string('content_key', 64);
            $addMetrics($table);
            $table->timestamp('synced_at');
            $table->timestamps();

            $table->unique(['iso_year', 'iso_week', 'content_key']);
            $table->index(['post_id', 'week_starts_on']);
        });

        Schema::create('analytics_period_snapshots', function (Blueprint $table) use ($addMetrics): void {
            $table->id();
            $table->enum('scope_type', ['site', 'post', 'channel']);
            $table->string('scope_key', 96);
            $table->string('period_key', 32);
            $table->date('starts_on');
            $table->date('ends_on');
            $table->date('comparison_starts_on')->nullable();
            $table->date('comparison_ends_on')->nullable();
            $addMetrics($table);
            $table->unsignedBigInteger('previous_readers')->nullable();
            $table->unsignedBigInteger('previous_meaningful_readers')->nullable();
            $table->unsignedBigInteger('previous_actioning_readers')->nullable();
            $table->unsignedBigInteger('previous_page_views')->nullable();
            $table->unsignedBigInteger('previous_select_content')->nullable();
            $table->unsignedBigInteger('previous_shares')->nullable();
            $table->unsignedBigInteger('previous_sign_ups')->nullable();
            $table->unsignedBigInteger('previous_comment_submits')->nullable();
            $table->string('source', 16)->default('exact');
            $table->timestamp('synced_at');
            $table->timestamps();

            $table->unique(['scope_key', 'starts_on', 'ends_on']);
            $table->index(['scope_type', 'period_key', 'ends_on']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('analytics_period_snapshots');
        Schema::dropIfExists('analytics_weekly_post_metrics');
        Schema::dropIfExists('analytics_weekly_site_metrics');
        Schema::dropIfExists('analytics_daily_channel_metrics');
        Schema::dropIfExists('analytics_daily_post_metrics');
        Schema::dropIfExists('analytics_daily_site_metrics');
    }
};
