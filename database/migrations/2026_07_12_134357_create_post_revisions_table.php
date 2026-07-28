<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('post_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('editor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('revision_number');
            $table->string('event');
            $table->json('snapshot');
            // Projections of the snapshot. Reference checks (media deletion,
            // revision pruning, image cleanup) query these instead of decoding
            // every snapshot, which carries the whole article body.
            $table->json('referenced_paths');
            $table->json('referenced_media_ids');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['post_id', 'revision_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_revisions');
    }
};
