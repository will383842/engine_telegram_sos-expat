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
        Schema::create('withdrawal_confirmations', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('withdrawal_id'); // Firestore withdrawal doc ID
            $table->string('user_id'); // firebase uid
            $table->string('chat_id');
            $table->integer('amount_cents');
            $table->string('payment_method')->nullable();
            $table->string('status')->default('pending'); // pending, confirmed, cancelled, expired
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();
            $table->index('withdrawal_id');
            $table->index('user_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('withdrawal_confirmations');
    }
};
