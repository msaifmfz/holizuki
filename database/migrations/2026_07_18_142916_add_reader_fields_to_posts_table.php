<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table): void {
            $table->text('featured_image_caption')->nullable();
            $table->timestamp('featured_at')->nullable();
            $table->unsignedSmallInteger('reading_time_minutes')->nullable();
            $table->text('search_text')->nullable();

            $table->index(['status', 'published_at', 'id']);
            $table->index(['status', 'featured_at']);
            $table->index(['category_id', 'status', 'published_at']);
            $table->index(['author_id', 'status', 'published_at']);

            if (DB::connection()->getDriverName() === 'pgsql') {
                $table->fullText('search_text')->language('english');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table): void {
            if (DB::connection()->getDriverName() === 'pgsql') {
                $table->dropFullText(['search_text']);
            }

            $table->dropIndex(['status', 'published_at', 'id']);
            $table->dropIndex(['status', 'featured_at']);
            $table->dropIndex(['category_id', 'status', 'published_at']);
            $table->dropIndex(['author_id', 'status', 'published_at']);
            $table->dropColumn([
                'featured_image_caption',
                'featured_at',
                'reading_time_minutes',
                'search_text',
            ]);
        });
    }
};
