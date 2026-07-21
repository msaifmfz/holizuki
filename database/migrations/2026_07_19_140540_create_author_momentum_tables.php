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
        Schema::create('author_publications', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('post_id')->unique();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('first_published_at')->index();
            $table->timestamps();
        });

        Schema::create('analytics_momentum_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('scored_on');
            $table->unsignedTinyInteger('score')->nullable();
            $table->enum('level', ['starting', 'building', 'growing', 'compounding'])->nullable();
            $table->json('components');
            $table->enum('freshness', ['fresh', 'delayed', 'stale', 'unavailable']);
            $table->timestamp('data_freshness_at')->nullable();
            $table->timestamp('calculated_at');
            $table->timestamps();

            $table->unique(['user_id', 'scored_on']);
        });

        Schema::create('analytics_milestones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('code', 64);
            $table->string('scope_key', 96);
            $table->json('evidence')->nullable();
            $table->timestamp('achieved_at');
            $table->timestamps();

            $table->unique(['code', 'scope_key']);
        });

        Schema::create('analytics_insights', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('post_id')->nullable()->constrained('posts')->nullOnDelete();
            $table->string('rule_id', 64);
            $table->string('scope_key', 96);
            $table->enum('confidence', ['exploratory', 'medium', 'high']);
            $table->enum('status', ['active', 'dismissed', 'snoozed', 'completed'])->default('active')->index();
            $table->json('evidence');
            $table->text('observation');
            $table->text('suggested_action');
            $table->string('dismissal_reason', 64)->nullable();
            $table->timestamp('detected_at');
            $table->timestamp('last_seen_at');
            $table->timestamp('dismissed_until')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['rule_id', 'scope_key']);
            $table->index(['user_id', 'status', 'confidence']);
        });

        Schema::create('author_product_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('event_id', 48);
            $table->string('deduplication_key', 128)->unique();
            $table->string('context_key', 96)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->timestamp('expires_at')->index();
            $table->timestamps();

            $table->index(['user_id', 'event_id', 'occurred_at']);
        });

        Schema::create('author_activity_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('post_id')->nullable();
            $table->string('event_id', 48);
            $table->string('event_key', 128)->unique();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->timestamp('expires_at')->index();
            $table->timestamps();

            $table->index(['user_id', 'event_id', 'occurred_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('author_activity_events');
        Schema::dropIfExists('author_product_events');
        Schema::dropIfExists('analytics_insights');
        Schema::dropIfExists('analytics_milestones');
        Schema::dropIfExists('analytics_momentum_snapshots');
        Schema::dropIfExists('author_publications');
    }
};
