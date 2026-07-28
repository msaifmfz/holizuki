<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('post_sources', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('kind', 32);
            $table->string('title');
            $table->string('url', 2048)->nullable();
            $table->text('content')->nullable();
            $table->string('path')->nullable()->unique();
            $table->string('original_name')->nullable();
            $table->string('mime_type', 127)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->string('visibility', 32)->default('private');
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index(['post_id', 'kind']);
            $table->index(['post_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_sources');
    }
};
