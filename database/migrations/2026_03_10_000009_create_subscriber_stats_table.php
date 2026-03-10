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
        Schema::create('subscriber_stats', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->integer('total_subscribers')->default(0);
            $table->integer('active_subscribers')->default(0);
            $table->integer('messages_sent')->default(0);
            $table->integer('messages_failed')->default(0);
            $table->json('by_role')->nullable(); // { chatter: 10, influencer: 5, ... }
            $table->timestamps();
            $table->unique('date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscriber_stats');
    }
};
