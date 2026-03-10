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
        Schema::create('notification_templates', function (Blueprint $table) {
            $table->id();
            $table->string('event_type'); // new_registration, call_completed, etc.
            $table->string('language', 5); // fr, en, es, de, pt, ru, zh, hi, ar
            $table->text('template'); // Template with {{VARIABLE}} placeholders
            $table->boolean('is_custom')->default(false); // custom override vs default
            $table->timestamps();
            $table->unique(['event_type', 'language']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_templates');
    }
};
