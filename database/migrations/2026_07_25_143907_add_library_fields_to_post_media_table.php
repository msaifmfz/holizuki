<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('post_media', function (Blueprint $table): void {
            $table->string('display_name')->nullable();
            $table->string('alt_text')->nullable();
            $table->text('caption')->nullable();
            $table->string('role', 32)->default('unassigned');
            $table->string('placement', 32)->default('unassigned');
            $table->string('origin', 32)->default('inline_editor');
            $table->string('status', 32)->default('ready');
            $table->string('story_priority', 32)->default('optional');
            $table->text('story_notes')->nullable();

            $table->index(['post_id', 'origin', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('post_media', function (Blueprint $table): void {
            $table->dropIndex(['post_id', 'origin', 'created_at']);
            $table->dropColumn([
                'display_name',
                'alt_text',
                'caption',
                'role',
                'placement',
                'origin',
                'status',
                'story_priority',
                'story_notes',
            ]);
        });
    }
};
