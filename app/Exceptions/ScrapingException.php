<?php

namespace App\Exceptions;

use App\Models\NewsSource;
use RuntimeException;

class ScrapingException extends RuntimeException
{
    /**
     * Create an exception for a specific news source failure.
     */
    public static function forSource(NewsSource $source, string $reason): self
    {
        return new self(
            "Scraping failed for source [{$source->slug}] ({$source->archive_url}): {$reason}"
        );
    }

    /**
     * Create an exception for an HTTP failure.
     */
    public static function forHttpFailure(string $url, int $statusCode): self
    {
        return new self(
            "HTTP {$statusCode} while fetching URL: {$url}"
        );
    }

    /**
     * Create an exception for an invalid scraper class.
     */
    public static function forInvalidScraperClass(string $class): self
    {
        return new self(
            "Scraper class [{$class}] does not implement ScraperInterface."
        );
    }
}
