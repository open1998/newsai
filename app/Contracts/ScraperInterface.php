<?php

namespace App\Contracts;

/**
 * Contract that every site-specific scraper must implement.
 *
 * Scrapers are resolved via ScraperFactory using the NewsSource::scraper_class field.
 * Each scraper is responsible for one news website.
 */
interface ScraperInterface
{
    /**
     * Fetch the archive/listing page and return an array of article URLs to scrape.
     *
     * @return array<int, string>
     */
    public function scrapeArchive(): array;

    /**
     * Fetch and parse a single article page, returning its content.
     *
     * @return array{title: string, body: string, image_url: string|null, published_at: string|null}
     */
    public function scrapeArticle(string $url): array;
}
