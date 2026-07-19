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
        Schema::table('posts', function (Blueprint $table): void {
            $table->string('seo_title')->nullable();
            $table->string('meta_description')->nullable();
            $table->string('canonical_url', 2048)->nullable();
            $table->string('og_title')->nullable();
            $table->string('og_description', 500)->nullable();
            $table->string('og_image_path')->nullable();
            $table->boolean('noindex')->default(false);
            $table->timestamp('content_updated_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table): void {
            $table->dropColumn([
                'seo_title',
                'meta_description',
                'canonical_url',
                'og_title',
                'og_description',
                'og_image_path',
                'noindex',
                'content_updated_at',
            ]);
        });
    }
};
