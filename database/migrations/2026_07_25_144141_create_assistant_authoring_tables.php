<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assistant_authoring_tasks', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->uuid('request_id')->unique();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assistant_turn_id')->nullable()->constrained('assistant_turns')->nullOnDelete();
            $table->string('kind');
            $table->string('status')->default('queued');
            $table->unsignedInteger('base_lock_version');
            $table->char('input_hash', 64);
            $table->json('context');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->string('error_code')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(
                ['post_id', 'kind', 'base_lock_version', 'input_hash'],
                'assistant_authoring_tasks_input_index',
            );
            $table->index(
                ['post_id', 'status', 'created_at'],
                'assistant_authoring_tasks_post_status_index',
            );
        });

        // Belt and braces over the portable guard: RequestAuthoringTask claims
        // the slot while holding a row lock on the post, which is what actually
        // serializes concurrent requests. MySQL has no partial indexes, so it
        // relies on that lock alone.
        if (in_array(DB::getDriverName(), ['pgsql', 'sqlite'], true)) {
            DB::statement(
                "CREATE UNIQUE INDEX assistant_authoring_tasks_one_active_per_post
                ON assistant_authoring_tasks (post_id)
                WHERE status IN ('queued', 'running')",
            );
        }

        Schema::create('assistant_authoring_artifacts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assistant_authoring_task_id')
                ->constrained('assistant_authoring_tasks')
                ->cascadeOnDelete();
            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('assistant_authoring_artifacts')
                ->cascadeOnDelete();
            $table->string('type');
            $table->uuid('group_id');
            $table->unsignedSmallInteger('position')->nullable();
            $table->unsignedInteger('base_lock_version');
            $table->json('payload');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(
                ['group_id', 'type', 'position'],
                'assistant_authoring_artifacts_group_position_unique',
            );
            $table->index(
                ['post_id', 'type', 'created_at'],
                'assistant_authoring_artifacts_post_type_index',
            );
            $table->index(
                ['parent_id', 'type', 'created_at'],
                'assistant_authoring_artifacts_parent_type_index',
            );
        });

        Schema::create('assistant_authoring_selections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('post_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('selected_plan_id')
                ->nullable()
                ->constrained('assistant_authoring_artifacts')
                ->nullOnDelete();
            $table->foreignId('selected_draft_id')
                ->nullable()
                ->constrained('assistant_authoring_artifacts')
                ->nullOnDelete();
            $table->foreignId('selected_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('assistant_authoring_image_compositions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')
                ->constrained('assistant_authoring_artifacts')
                ->cascadeOnDelete();
            $table->foreignId('selected_passage_id')
                ->nullable()
                ->constrained('assistant_authoring_artifacts')
                ->nullOnDelete();
            $table->foreignId('selected_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('usage');
            $table->string('section_heading')->nullable();
            $table->unsignedSmallInteger('position');
            $table->text('passage_markdown')->nullable();
            $table->char('passage_input_hash', 64)->nullable();
            $table->timestamps();

            $table->unique(
                ['plan_id', 'position'],
                'assistant_authoring_image_compositions_plan_position_unique',
            );
            $table->index(
                ['post_id', 'plan_id'],
                'assistant_authoring_image_compositions_post_plan_index',
            );
        });

        Schema::create('assistant_authoring_image_media', function (Blueprint $table): void {
            $table->foreignId('composition_id')
                ->constrained('assistant_authoring_image_compositions')
                ->cascadeOnDelete();
            $table->foreignId('plan_id')
                ->constrained('assistant_authoring_artifacts')
                ->cascadeOnDelete();
            $table->foreignId('post_media_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position');

            $table->unique(
                ['plan_id', 'post_media_id'],
                'assistant_authoring_image_media_plan_media_unique',
            );
            $table->unique(
                ['composition_id', 'position'],
                'assistant_authoring_image_media_composition_position_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assistant_authoring_image_media');
        Schema::dropIfExists('assistant_authoring_image_compositions');
        Schema::dropIfExists('assistant_authoring_selections');
        Schema::dropIfExists('assistant_authoring_artifacts');
        Schema::dropIfExists('assistant_authoring_tasks');
    }
};
