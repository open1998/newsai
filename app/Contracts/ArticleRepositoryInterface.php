<?php

namespace App\Contracts;

use App\Enums\AiStatus;
use App\Enums\Language;
use App\Models\Article;
use App\Models\NewsSource;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * Contract for all article data access operations.
 *
 * All callers must depend on this interface, not the concrete implementation,
 * keeping the system testable and the storage layer swappable.
 */
interface ArticleRepositoryInterface
{
    /**
     * Create or update an article for a given news source and URL.
     *
     * The unique key is (news_source_id, source_url). If the article already
     * exists, its scraped fields and content_hash are updated in place.
     *
     * @param  array{
     *     source_url: string,
     *     language: Language,
     *     content_hash: string,
     *     original_title: string,
     *     original_body: string,
     *     original_image_url: string|null,
     *     published_at: string|null,
     * }  $data
     */
    public function upsertFromScrape(NewsSource $source, array $data): Article;

    /**
     * Record a scrape failure on the article.
     */
    public function markScrapeError(Article $article, string $error): void;

    /**
     * Store AI-generated content and mark the article as AI processed.
     *
     * @param  array{ai_title: string, ai_body: string, ai_summary: string}  $aiData
     */
    public function markAiProcessed(Article $article, array $aiData): void;

    /**
     * Update only the AI status field on an article.
     */
    public function markAiStatus(Article $article, AiStatus $status): void;

    /**
     * Return a paginated list of articles, optionally filtered by language
     * and/or source slug. Ordered by published_at descending.
     *
     * @return LengthAwarePaginator<int, Article>
     */
    public function getPaginatedByLanguage(?Language $language, int $perPage = 15, ?string $sourceSlug = null): LengthAwarePaginator;

    /**
     * Return all news sources that currently have at least one article,
     * ordered by name.
     *
     * @return Collection<int, NewsSource>
     */
    public function getSourcesWithArticles(): Collection;

    /**
     * Find a single article by its primary key.
     */
    public function findById(int $id): ?Article;

    /**
     * Return per-source article statistics for the dashboard, ordered by name.
     *
     * Each source carries aggregate attributes: articles_count, ai_succeeded_count,
     * ai_pending_count, ai_processing_count, ai_failed_count, ai_skipped_count.
     *
     * @return Collection<int, NewsSource>
     */
    public function getSourceStats(): Collection;
}
