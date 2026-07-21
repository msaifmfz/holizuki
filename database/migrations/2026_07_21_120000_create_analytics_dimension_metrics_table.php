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
        Schema::create('analytics_dimension_period_metrics', function (Blueprint $table): void {
            $table->id();
            $table->string('dimension_type', 24);
            $table->string('dimension_value', 128);
            $table->unsignedTinyInteger('position');
            $table->string('period_key', 32);
            $table->date('starts_on');
            $table->date('ends_on');
            $table->unsignedBigInteger('readers')->default(0);
            $table->unsignedBigInteger('page_views')->default(0);
            $table->unsignedBigInteger('previous_readers')->nullable();
            $table->unsignedBigInteger('previous_page_views')->nullable();
            $table->timestamp('synced_at');
            $table->timestamps();

            $table->unique(['dimension_type', 'dimension_value', 'starts_on', 'ends_on']);
            $table->index(['dimension_type', 'period_key', 'ends_on']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('analytics_dimension_period_metrics');
    }
};
