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
        Schema::create('newsletter_subscribers', function (Blueprint $table): void {
            $table->id();
            $table->text('email')->nullable();
            $table->char('email_hash', 64)->unique();
            $table->enum('status', ['pending', 'confirmed', 'unsubscribed'])->default('pending')->index();
            $table->char('confirmation_token_hash', 64)->nullable()->unique();
            $table->char('unsubscribe_token_hash', 64)->nullable()->unique();
            $table->foreignId('source_post_id')->nullable()->constrained('posts')->nullOnDelete();
            $table->string('source_method', 32)->default('form');
            $table->string('source_location', 32)->default('footer');
            $table->string('source_content_key', 64)->nullable();
            $table->string('consent_version', 32);
            $table->timestamp('confirmation_sent_at')->nullable();
            $table->timestamp('confirmation_expires_at')->nullable()->index();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('unsubscribed_at')->nullable();
            $table->timestamp('erased_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'source_location']);
        });

        Schema::create('comments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('post_id')->constrained('posts')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('body')->nullable();
            $table->char('body_hash', 64);
            $table->enum('status', ['pending', 'approved', 'rejected', 'deleted'])->default('pending')->index();
            $table->timestamp('edit_deadline_at');
            $table->foreignId('moderated_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('moderation_reason')->nullable();
            $table->timestamp('submitted_at')->index();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable()->index();
            $table->timestamp('deleted_at')->nullable()->index();
            $table->timestamp('body_erased_at')->nullable();
            $table->timestamps();

            $table->index(['post_id', 'status', 'id']);
            $table->index(['user_id', 'submitted_at']);
            $table->index(['post_id', 'user_id', 'body_hash', 'submitted_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('comments');
        Schema::dropIfExists('newsletter_subscribers');
    }
};
