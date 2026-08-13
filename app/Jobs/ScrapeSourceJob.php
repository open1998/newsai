<?php

namespace App\Jobs;

use App\Contracts\ArticleRepositoryInterface;
use App\Exceptions\ScrapingException;
use App\Models\Article;
use App\Models\NewsSource;
use App\Services\Scrapers\ScraperFactory;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ScrapeSourceJob implements ShouldQueue
{
    use Queueable;

    /**
     * Number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * Backoff in seconds between retries.
     *
     * @var array<int, int>
     */
    public array $backoff = [60, 180, 600];

    public function __construct(
        public readonly NewsSource $newsSource,
    ) {}

    /**
     * Execute the scraping job for the news source.
     *
     * For each article URL found in the archive:
     *  1. Scrape the article content.
     *  2. Compute a content_hash from title + body.
     *  3. Upsert the article via the repository.
     *  4. Dispatch ProcessArticleWithAiJob only if the article is new or content changed.
     */
    public function handle(
        ScraperFactory $scraperFactory,
        ArticleRepositoryInterface $articleRepository,
    ): void {
        $scraper = $scraperFactory->make($this->newsSource);

        $articleUrls = $scraper->scrapeArchive();

        foreach ($articleUrls as $url) {
            try {
                $scraped = $scraper->scrapeArticle($url);

                // Skip articles with no usable content
                if (empty($scraped['title']) || empty($scraped['body'])) {
                    continue;
                }

                $contentHash = hash('sha256', $scraped['title'].$scraped['body']);

                $article = $articleRepository->upsertFromScrape($this->newsSource, [
                    'source_url' => $url,
                    'language' => $this->newsSource->language,
                    'content_hash' => $contentHash,
                    'original_title' => $scraped['title'],
                    'original_body' => $scraped['body'],
                    'original_image_url' => $scraped['image_url'] ?? null,
                    'published_at' => $scraped['published_at'] ?? null,
                ]);

                $this->dispatchAiJobIfNeeded($article, $contentHash);

            } catch (ScrapingException $e) {
                Log::warning('Scraping error for single article', [
                    'source' => $this->newsSource->slug,
                    'url' => $url,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->newsSource->update(['last_scraped_at' => now()]);
    }

    /**
     * Dispatch the AI job only for new articles or articles whose content changed.
     *
     * A new article has wasRecentlyCreated = true.
     * A changed article has a different content_hash from what was stored before upserting.
     *
     * We re-dispatch if ai_processed_at is null (never processed) OR the article was
     * just created (regardless of ai_processed_at) OR the content changed (hash was updated).
     */
    private function dispatchAiJobIfNeeded(Article $article, string $newContentHash): void
    {
        $isNew = $article->wasRecentlyCreated;
        $contentChanged = ! $isNew && $article->ai_processed_at !== null
            && $article->getOriginal('content_hash') !== $newContentHash;
        $neverProcessed = $article->ai_processed_at === null;

        if ($isNew || $contentChanged || $neverProcessed) {
            ProcessArticleWithAiJob::dispatch($article);
        }
    }

    /**
     * Handle a job failure after all retries are exhausted.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('ScrapeSourceJob failed', [
            'source' => $this->newsSource->slug,
            'error' => $exception->getMessage(),
        ]);
    }
}
