<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('post_authoring_journeys', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('post_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('kind')->default('creation');
            $table->string('current_step')->default('idea');
            $table->json('completed_steps')->default('[]');
            $table->json('brief')->default('{}');
            $table->string('personality')->default('friendly');
            $table->text('custom_instructions')->nullable();
            $table->boolean('web_research_enabled')->default(false);
            $table->string('last_surface')->default('guided');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_authoring_journeys');
    }
};
