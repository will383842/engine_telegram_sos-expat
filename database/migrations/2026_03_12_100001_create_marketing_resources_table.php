<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketing_resources', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Ciblage : qui voit cette ressource
            // ["chatter","captain","influencer","blogger","group_admin","partner","press"]
            $table->jsonb('target_roles');

            // Catégorie & Type
            $table->string('category', 50); // brand, promotional, social_media, templates, seo, group_posts, partner_kit, press_kit
            $table->string('type', 50);     // logo, image, banner, text, template, article, video, document, widget

            // Traductions 9 langues (jsonb) — fallback: user_lang → en → fr
            $table->jsonb('name');           // {"fr":"Logo SOS","en":"SOS Logo","es":"Logo SOS",...}
            $table->jsonb('description')->nullable();
            $table->jsonb('content')->nullable(); // textes copiables / articles complets

            // Fichiers
            $table->string('file_path', 500)->nullable();
            $table->string('thumbnail_path', 500)->nullable();
            $table->string('file_format', 20)->nullable();
            $table->unsignedInteger('file_size')->default(0);

            // Spécificités optionnelles
            $table->jsonb('placeholders')->nullable();    // ["{{AFFILIATE_LINK}}","{{GROUP_NAME}}"]
            $table->jsonb('seo_keywords')->nullable();    // ["expat legal","immigration"]
            $table->unsignedInteger('word_count')->nullable();

            // Tracking & état
            $table->unsignedInteger('download_count')->default(0);
            $table->unsignedInteger('copy_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();
        });

        // Index GIN pour filtrage rapide par rôle
        DB::statement('CREATE INDEX idx_marketing_resources_target_roles ON marketing_resources USING GIN (target_roles)');

        // Index pour les requêtes fréquentes
        Schema::table('marketing_resources', function (Blueprint $table) {
            $table->index(['is_active', 'category', 'sort_order']);
        });

        Schema::create('marketing_usage_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('resource_id');
            $table->string('user_uid', 128);      // Firebase UID
            $table->string('user_role', 30);       // chatter, captain, influencer, blogger, group_admin, partner
            $table->string('action', 20);          // view, download, copy
            $table->string('language', 5)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('resource_id')
                  ->references('id')
                  ->on('marketing_resources')
                  ->cascadeOnDelete();

            $table->index(['resource_id', 'action']);
            $table->index(['user_uid', 'created_at']);
            $table->index(['user_role', 'action', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_usage_logs');
        Schema::dropIfExists('marketing_resources');
    }
};
