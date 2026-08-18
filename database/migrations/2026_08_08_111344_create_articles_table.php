<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('news_source_id')->constrained()->cascadeOnDelete();
            $table->index('news_source_id');
            $table->string('source_url');
            $table->string('language')->index(); // Language enum value
            $table->string('content_hash', 64)->nullable()->index();

            // Scrape observability
            $table->string('scrape_status')->default('pending')->index(); // ScrapeStatus enum
            $table->text('last_scrape_error')->nullable();

            // Original scraped content
            $table->string('original_title');
            $table->text('original_body');
            $table->string('original_image_url')->nullable();

            // AI-generated content
            $table->string('ai_status')->default('pending')->index(); // AiStatus enum
            $table->string('ai_title')->nullable();
            $table->text('ai_body')->nullable();
            $table->text('ai_summary')->nullable();

            // Timestamps
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamp('scraped_at')->nullable();
            $table->timestamp('ai_processed_at')->nullable()->index();
            $table->timestamps();

            // Uniqueness: one article per source URL per news source
            $table->unique(['news_source_id', 'source_url']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('articles');
    }
};
