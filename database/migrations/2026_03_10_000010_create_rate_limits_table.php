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
        Schema::create('rate_limits', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->integer('messages_sent')->default(0);
            $table->integer('messages_failed')->default(0);
            $table->integer('peak_per_second')->default(0);
            $table->timestamps();
            $table->unique('date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rate_limits');
    }
};
