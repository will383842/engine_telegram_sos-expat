<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('color', 7)->nullable(); // hex color e.g. #00c896
            $table->timestamps();
        });

        Schema::create('job_contacts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('city')->nullable();
            $table->string('country')->nullable();
            $table->uuid('category_id')->nullable();
            $table->uuid('source_ad_id')->nullable();
            $table->string('source_file')->nullable();
            $table->string('source_type', 30)->default('manual'); // csv, cv_pdf, cv_docx, cv_image, manual
            $table->jsonb('raw_data')->default('{}'); // all extracted data from AI
            $table->jsonb('skills')->default('[]');
            $table->integer('experience_years')->nullable();
            $table->string('current_position')->nullable();
            $table->string('linkedin_url')->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('new'); // new, contacted, interested, rejected, hired
            $table->boolean('is_duplicate')->default(false);
            $table->uuid('duplicate_of')->nullable();
            $table->jsonb('audit')->default('[]');
            $table->jsonb('sos_registered')->nullable(); // {found:true, uid:"...", role:"client", roles:["client","chatter"], name:"...", checkedAt:"..."}
            $table->timestamps();

            $table->foreign('category_id')->references('id')->on('job_categories')->nullOnDelete();
            $table->foreign('source_ad_id')->references('id')->on('job_ads')->nullOnDelete();
            $table->index('email');
            $table->index('phone');
            $table->index('category_id');
            $table->index('status');
            $table->index('source_ad_id');
            $table->index('is_duplicate');
            $table->index('duplicate_of');
            $table->foreign('duplicate_of')->references('id')->on('job_contacts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_contacts');
        Schema::dropIfExists('job_categories');
    }
};
