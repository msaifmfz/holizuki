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
        Schema::create('analytics_url_aliases', function (Blueprint $table): void {
            $table->id();
            $table->string('path', 2048)->unique();
            $table->foreignId('post_id')->nullable()->constrained('posts')->nullOnDelete();
            $table->string('content_key', 64);
            $table->boolean('is_canonical')->default(false);
            $table->timestamp('retired_at')->nullable();
            $table->timestamps();

            $table->index(['content_key', 'is_canonical']);
        });

        Schema::create('analytics_unmapped_paths', function (Blueprint $table): void {
            $table->id();
            $table->string('path', 2048)->unique();
            $table->unsignedBigInteger('readers')->default(0);
            $table->unsignedBigInteger('page_views')->default(0);
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at')->index();
            $table->timestamps();
        });

        Schema::create('analytics_sync_runs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('run_id')->unique();
            $table->string('command', 48)->index();
            $table->enum('status', ['running', 'succeeded', 'failed'])->default('running')->index();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->unsignedTinyInteger('attempt')->default(1);
            $table->unsignedInteger('request_count')->default(0);
            $table->unsignedInteger('page_count')->default(0);
            $table->unsignedBigInteger('row_count')->default(0);
            $table->json('quota')->nullable();
            $table->text('sanitized_error')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'completed_at']);
        });

        Schema::create('analytics_snapshot_preparations', function (Blueprint $table): void {
            $table->id();
            $table->string('preparation_key', 64)->unique();
            $table->foreignId('requested_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('scope_key', 96)->default('site');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->enum('status', ['queued', 'preparing', 'ready', 'failed'])->default('queued')->index();
            $table->text('sanitized_error')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('analytics_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 64)->unique();
            $table->json('value');
            $table->foreignId('updated_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('analytics_settings');
        Schema::dropIfExists('analytics_snapshot_preparations');
        Schema::dropIfExists('analytics_sync_runs');
        Schema::dropIfExists('analytics_unmapped_paths');
        Schema::dropIfExists('analytics_url_aliases');
    }
};
