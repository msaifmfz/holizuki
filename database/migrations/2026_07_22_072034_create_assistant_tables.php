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
        Schema::create('assistant_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('post_id')->unique()->constrained()->cascadeOnDelete();
            $table->uuid('claude_session_id');
            $table->string('status')->default('idle');
            $table->uuid('active_run_token')->nullable()->unique();
            $table->boolean('resume_ready')->default(false);
            $table->string('conversation_model')->nullable();
            $table->longText('outline_markdown')->nullable();
            $table->boolean('outline_ready')->default(false);
            $table->unsignedInteger('outline_version')->default(0);
            $table->timestamp('turn_started_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'turn_started_at']);
        });

        Schema::create('assistant_turns', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('assistant_session_id')->constrained()->cascadeOnDelete();
            $table->uuid('run_token')->unique();
            $table->uuid('request_id')->unique();
            $table->string('task_type');
            $table->string('status');
            $table->text('user_prompt');
            $table->text('assistant_message')->nullable();
            $table->json('context')->nullable();
            $table->longText('snapshot_draft')->nullable();
            $table->json('snapshot_meta')->nullable();
            $table->string('result_subtype')->nullable();
            $table->string('stop_reason')->nullable();
            $table->json('usage')->nullable();
            $table->decimal('cost_usd', 10, 6)->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->string('error_code')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('retry_at')->nullable();
            $table->timestamp('cancel_requested_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['assistant_session_id', 'status', 'id']);
            $table->index(['status', 'created_at']);
        });

        Schema::create('assistant_changes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('assistant_turn_id')->constrained()->cascadeOnDelete();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('status')->default('proposed');
            $table->json('payload');
            $table->unsignedInteger('base_lock_version');
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->index(['post_id', 'status', 'type']);
            $table->index(['post_id', 'status', 'id']);
            $table->index(['assistant_turn_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assistant_changes');
        Schema::dropIfExists('assistant_turns');
        Schema::dropIfExists('assistant_sessions');
    }
};
