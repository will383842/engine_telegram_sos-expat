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
        Schema::create('onboarding_links', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // deep link code
            $table->string('user_id'); // firebase uid
            $table->string('role'); // chatter, influencer, blogger, groupAdmin, affiliate
            $table->string('status')->default('pending'); // pending, linked, expired
            $table->bigInteger('telegram_id')->nullable();
            $table->string('telegram_username')->nullable();
            $table->string('telegram_first_name')->nullable();
            $table->string('telegram_last_name')->nullable();
            $table->string('deep_link'); // t.me/bot?start=CODE
            $table->timestamp('linked_at')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();
            $table->index('user_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('onboarding_links');
    }
};
