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
            $table->string('status')->default('idle')->index();
            $table->timestamp('turn_started_at')->nullable();
            $table->timestamps();
        });

        Schema::create('assistant_turns', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('assistant_session_id')->constrained()->cascadeOnDelete();
            $table->string('task_type')->index();
            $table->string('status')->index();
            $table->text('user_prompt');
            $table->text('assistant_message')->nullable();
            $table->json('context')->nullable();
            $table->longText('snapshot_draft')->nullable();
            $table->json('snapshot_meta')->nullable();
            $table->decimal('cost_usd', 10, 6)->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
        });

        Schema::create('assistant_changes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('assistant_turn_id')->constrained()->cascadeOnDelete();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->string('type')->index();
            $table->string('status')->default('proposed')->index();
            $table->json('payload');
            $table->unsignedInteger('base_lock_version');
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
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
