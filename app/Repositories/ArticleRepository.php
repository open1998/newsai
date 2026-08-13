<?php

namespace App\Repositories;

use App\Contracts\ArticleRepositoryInterface;
use App\Enums\AiStatus;
use App\Enums\Language;
use App\Enums\ScrapeStatus;
use App\Models\Article;
use App\Models\NewsSource;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ArticleRepository implements ArticleRepositoryInterface
{
    /**
     * Create or update an article by (news_source_id, source_url).
     *
     * Only updates scraped fields if the record already exists.
     * AI fields are never overwritten here — they are managed by markAiProcessed.
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
    public function upsertFromScrape(NewsSource $source, array $data): Article
    {
        /** @var Article $article */
        $article = Article::updateOrCreate(
            [
                'news_source_id' => $source->id,
                'source_url' => $data['source_url'],
            ],
            [
                'language' => $data['language'],
                'content_hash' => $data['content_hash'],
                'scrape_status' => ScrapeStatus::Succeeded,
                'last_scrape_error' => null,
                'original_title' => $data['original_title'],
                'original_body' => $data['original_body'],
                'original_image_url' => $data['original_image_url'],
                'published_at' => $data['published_at'],
                'scraped_at' => now(),
            ]
        );

        return $article;
    }

    /**
     * Record a scrape failure, preserving any previously scraped content.
     */
    public function markScrapeError(Article $article, string $error): void
    {
        $article->update([
            'scrape_status' => ScrapeStatus::Failed,
            'last_scrape_error' => $error,
        ]);
    }

    /**
     * Store AI-generated fields and mark the article as AI processed.
     *
     * @param  array{ai_title: string, ai_body: string, ai_summary: string}  $aiData
     */
    public function markAiProcessed(Article $article, array $aiData): void
    {
        $article->update([
            'ai_status' => AiStatus::Succeeded,
            'ai_title' => $aiData['ai_title'],
            'ai_body' => $aiData['ai_body'],
            'ai_summary' => $aiData['ai_summary'],
            'ai_processed_at' => now(),
        ]);
    }

    /**
     * Update only the AI status field.
     */
    public function markAiStatus(Article $article, AiStatus $status): void
    {
        $article->update(['ai_status' => $status]);
    }

    /**
     * Return a paginated, language-filtered list of articles ordered by published_at.
     *
     * @return LengthAwarePaginator<Article>
     */
    public function getPaginatedByLanguage(?Language $language, int $perPage = 15): LengthAwarePaginator
    {
        return Article::with('newsSource')
            ->when($language !== null, fn ($q) => $q->forLanguage($language))
            ->orderByDesc('published_at')
            ->paginate($perPage);
    }

    /**
     * Find a single article by its primary key.
     */
    public function findById(int $id): ?Article
    {
        return Article::find($id);
    }
}
