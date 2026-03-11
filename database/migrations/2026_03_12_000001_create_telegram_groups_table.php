<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_groups', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();           // e.g. "chatter_continent_AF_fr"
            $table->string('name');                       // e.g. "Chatters 🌍 Afrique 🇫🇷"
            $table->string('link')->default('');          // e.g. "https://t.me/+XXXX"
            $table->string('language', 5);                // e.g. "fr", "en"
            $table->string('role');                        // e.g. "chatter", "influencer", "partner"
            $table->string('type');                        // "continent" or "language"
            $table->string('continent_code', 2)->nullable(); // "AF", "EU", etc.
            $table->boolean('enabled')->default(false);
            $table->timestamps();

            $table->index(['role', 'enabled']);
            $table->index(['role', 'type', 'continent_code', 'language']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_groups');
    }
};
