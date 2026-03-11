<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('message_queue', function (Blueprint $table) {
            $table->string('bot_slug', 50)->default('main')->after('source');
            $table->index('bot_slug');
        });
    }

    public function down(): void
    {
        Schema::table('message_queue', function (Blueprint $table) {
            $table->dropIndex(['bot_slug']);
            $table->dropColumn('bot_slug');
        });
    }
};
