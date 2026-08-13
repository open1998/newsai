<?php

namespace App\Services\Scrapers;

/**
 * A no-op scraper used as the default for all NewsSource records
 * until a real site-specific scraper is implemented.
 *
 * Returns empty arrays so the job pipeline runs end-to-end without
 * making any real HTTP requests or producing any articles.
 * Replace this for each website in Task 9+.
 */
class PlaceholderScraper extends BaseArticleScraper
{
    /**
     * Return an empty list of article URLs — placeholder does not scrape.
     *
     * @return array<int, string>
     */
    public function scrapeArchive(): array
    {
        return [];
    }

    /**
     * Return empty article data — placeholder does not parse articles.
     *
     * @return array{title: string, body: string, image_url: string|null, published_at: string|null}
     */
    public function scrapeArticle(string $url): array
    {
        return [
            'title' => '',
            'body' => '',
            'image_url' => null,
            'published_at' => null,
        ];
    }
}
