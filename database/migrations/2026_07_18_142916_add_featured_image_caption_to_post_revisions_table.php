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
        Schema::table('post_revisions', function (Blueprint $table): void {
            $table->text('featured_image_caption')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('post_revisions', function (Blueprint $table): void {
            $table->dropColumn('featured_image_caption');
        });
    }
};
