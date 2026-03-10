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
        Schema::create('message_queue', function (Blueprint $table) {
            $table->id();
            $table->string('chat_id');
            $table->text('message');
            $table->string('parse_mode')->default('HTML');
            $table->string('status')->default('pending'); // pending, processing, sent, failed, dead
            $table->integer('attempts')->default(0);
            $table->integer('max_retries')->default(3);
            $table->string('idempotency_key')->nullable();
            $table->text('error')->nullable();
            $table->string('source')->nullable(); // event_type or 'campaign' or 'withdrawal' etc.
            $table->timestamp('next_retry_at')->nullable();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->index('status');
            $table->index('next_retry_at');
            $table->index(['idempotency_key'], 'idx_idempotency');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('message_queue');
    }
};
