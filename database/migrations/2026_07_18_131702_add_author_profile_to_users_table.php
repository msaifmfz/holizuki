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
        Schema::table('users', function (Blueprint $table): void {
            $table->string('author_slug')->nullable()->unique()->after('role');
            $table->string('avatar_path')->nullable()->after('author_slug');
            $table->text('bio')->nullable()->after('avatar_path');
            $table->json('social_links')->nullable()->after('bio');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['author_slug', 'avatar_path', 'bio', 'social_links']);
        });
    }
};
