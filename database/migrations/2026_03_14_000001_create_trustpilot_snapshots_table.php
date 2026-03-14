<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trustpilot_snapshots', function (Blueprint $table) {
            $table->id();

            // Global stats at snapshot time
            $table->decimal('trust_score', 3, 1)->nullable();       // e.g. 4.7
            $table->integer('total_reviews')->default(0);
            $table->integer('new_reviews_count')->default(0);       // vs previous snapshot

            // Star distribution (count per star level)
            $table->integer('stars_5')->default(0);
            $table->integer('stars_4')->default(0);
            $table->integer('stars_3')->default(0);
            $table->integer('stars_2')->default(0);
            $table->integer('stars_1')->default(0);

            // New reviews detail (JSON array of individual reviews)
            $table->json('new_reviews')->nullable();

            // Raw scraped data for debugging
            $table->json('raw_data')->nullable();

            // Period covered
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trustpilot_snapshots');
    }
};
