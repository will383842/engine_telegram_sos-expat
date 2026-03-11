<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_bots', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 50)->unique();          // 'main', 'inbox', etc.
            $table->string('name');                         // Display name
            $table->string('token');                        // Bot API token
            $table->string('recipient_chat_id')->nullable(); // Where to send notifications
            $table->json('notifications')->default('{}');   // {event_type: bool}
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_bots');
    }
};
