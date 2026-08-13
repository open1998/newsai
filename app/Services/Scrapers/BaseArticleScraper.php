<?php

namespace App\Services\Scrapers;

use App\Contracts\ScraperInterface;
use App\Exceptions\ScrapingException;
use App\Models\NewsSource;
use DOMDocument;
use Illuminate\Http\Client\Factory as HttpFactory;

/**
 * Abstract base class for all site-specific scrapers.
 *
 * Provides the shared HTTP fetch + DOM parsing infrastructure.
 * Concrete scrapers extend this class and implement scrapeArchive()
 * and scrapeArticle() using the protected fetchDom() helper.
 */
abstract class BaseArticleScraper implements ScraperInterface
{
    public function __construct(
        protected readonly NewsSource $source,
        protected readonly HttpFactory $http,
    ) {}

    /**
     * Fetch a URL and parse its HTML into a DOMDocument.
     *
     * Uses the Laravel HTTP client (not file_get_contents) for testability
     * and respects timeout / redirect settings.
     *
     * An explicit XML encoding declaration is prepended so DOMDocument
     * parses the HTML as UTF-8 instead of assuming ISO-8859-1 (the HTML 4
     * default), which would mangle multilingual content (Sinhala, Tamil).
     *
     * @throws ScrapingException if the HTTP request fails or returns non-2xx.
     */
    protected function fetchDom(string $url): DOMDocument
    {
        $response = $this->http->timeout(15)->get($url);

        if ($response->failed()) {
            throw ScrapingException::forHttpFailure($url, $response->status());
        }

        $dom = new DOMDocument('1.0', 'UTF-8');

        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">'.$response->body(), LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $dom;
    }
}
