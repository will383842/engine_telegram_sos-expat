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
        Schema::create('admin_configs', function (Blueprint $table) {
            $table->id();
            $table->string('recipient_phone_number')->nullable();
            $table->string('recipient_chat_id')->nullable();
            $table->json('notifications')->default('{}'); // enabled event types
            $table->string('updated_by')->nullable(); // firebase uid
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admin_configs');
    }
};
