<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_ads', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->string('site')->nullable();
            $table->string('site_url')->nullable();
            $table->string('ad_url')->nullable();
            $table->string('country')->nullable();
            $table->string('lang', 5)->nullable();
            $table->string('status', 20)->default('draft'); // draft, pending, approved, active, expired, rejected
            $table->date('expiry')->nullable();
            $table->string('category')->nullable();
            $table->string('login')->nullable();
            $table->text('password')->nullable(); // AES-encrypted
            $table->integer('responses')->default(0);
            $table->jsonb('response_history')->default('[]');
            $table->date('pub_date')->nullable();
            $table->text('notes')->nullable();
            $table->string('poster')->nullable();
            $table->jsonb('tags')->default('[]');
            $table->jsonb('audit')->default('[]');
            $table->string('deleted_by')->nullable();
            $table->timestamp('deleted_at')->nullable(); // soft delete = trash
            $table->timestamps();

            $table->index('status');
            $table->index('country');
            $table->index('lang');
            $table->index('deleted_at');
        });

        Schema::create('job_sites', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('url')->nullable();
            $table->string('country')->nullable();
            $table->string('lang', 5)->nullable();
            $table->string('category')->nullable();
            $table->string('preapproval', 10)->default('no'); // no, yes, auto
            $table->integer('validity')->default(0); // days, 0 = unlimited
            $table->decimal('cost', 10, 2)->default(0);
            $table->string('login')->nullable();
            $table->text('password')->nullable(); // AES-encrypted
            $table->text('notes')->nullable();
            $table->boolean('is_paid')->default(false);
            $table->timestamp('paid_detected_at')->nullable();
            $table->jsonb('audit')->default('[]');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_ads');
        Schema::dropIfExists('job_sites');
    }
};
