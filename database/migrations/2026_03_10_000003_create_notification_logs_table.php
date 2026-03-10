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
        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();
            $table->string('event_type');
            $table->string('chat_id');
            $table->text('message')->nullable();
            $table->string('status'); // sent, failed, filtered
            $table->text('error')->nullable();
            $table->json('variables')->nullable();
            $table->json('filters')->nullable();
            $table->timestamps();
            $table->index('event_type');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
    }
};
