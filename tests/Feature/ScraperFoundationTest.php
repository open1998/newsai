<?php

use App\Contracts\ScraperInterface;
use App\Exceptions\ScrapingException;
use App\Models\NewsSource;
use App\Services\Scrapers\PlaceholderScraper;
use App\Services\Scrapers\ScraperFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

// ── PlaceholderScraper ───────────────────────────────────────────────────────

test('PlaceholderScraper implements ScraperInterface', function () {
    $source = NewsSource::factory()->create(['scraper_class' => PlaceholderScraper::class]);
    $scraper = new PlaceholderScraper($source, app(HttpFactory::class));

    expect($scraper)->toBeInstanceOf(ScraperInterface::class);
});

test('PlaceholderScraper::scrapeArchive returns empty array', function () {
    $source = NewsSource::factory()->create(['scraper_class' => PlaceholderScraper::class]);
    $scraper = new PlaceholderScraper($source, app(HttpFactory::class));

    expect($scraper->scrapeArchive())->toBe([]);
});

test('PlaceholderScraper::scrapeArticle returns all expected keys with empty values', function () {
    $source = NewsSource::factory()->create(['scraper_class' => PlaceholderScraper::class]);
    $scraper = new PlaceholderScraper($source, app(HttpFactory::class));

    $result = $scraper->scrapeArticle('https://example.com/article/1');

    expect($result)->toHaveKeys(['title', 'body', 'image_url', 'published_at'])
        ->and($result['title'])->toBe('')
        ->and($result['body'])->toBe('')
        ->and($result['image_url'])->toBeNull()
        ->and($result['published_at'])->toBeNull();
});

// ── ScraperFactory ───────────────────────────────────────────────────────────

test('ScraperFactory resolves PlaceholderScraper for a NewsSource', function () {
    $source = NewsSource::factory()->create(['scraper_class' => PlaceholderScraper::class]);
    $factory = app(ScraperFactory::class);

    $scraper = $factory->make($source);

    expect($scraper)->toBeInstanceOf(PlaceholderScraper::class)
        ->and($scraper)->toBeInstanceOf(ScraperInterface::class);
});

test('ScraperFactory throws ScrapingException for a class that does not implement ScraperInterface', function () {
    $source = NewsSource::factory()->create(['scraper_class' => stdClass::class]);
    $factory = app(ScraperFactory::class);

    expect(fn () => $factory->make($source))
        ->toThrow(ScrapingException::class, 'does not implement ScraperInterface');
});

// ── BaseArticleScraper::fetchDom ─────────────────────────────────────────────

test('fetchDom returns a DOMDocument on a successful HTTP response', function () {
    Http::fake([
        'https://example.com/archive' => Http::response('<html><body><h1>Hello</h1></body></html>', 200),
    ]);

    $source = NewsSource::factory()->create([
        'scraper_class' => PlaceholderScraper::class,
        'archive_url' => 'https://example.com/archive',
    ]);

    // Expose fetchDom via an anonymous subclass for testing
    $scraper = new class($source, app(HttpFactory::class)) extends PlaceholderScraper
    {
        public function exposedFetchDom(string $url): DOMDocument
        {
            return $this->fetchDom($url);
        }
    };

    $dom = $scraper->exposedFetchDom('https://example.com/archive');

    expect($dom)->toBeInstanceOf(DOMDocument::class);
});

test('fetchDom throws ScrapingException on HTTP failure', function () {
    Http::fake([
        'https://example.com/archive' => Http::response('Not Found', 404),
    ]);

    $source = NewsSource::factory()->create([
        'scraper_class' => PlaceholderScraper::class,
        'archive_url' => 'https://example.com/archive',
    ]);

    $scraper = new class($source, app(HttpFactory::class)) extends PlaceholderScraper
    {
        public function exposedFetchDom(string $url): DOMDocument
        {
            return $this->fetchDom($url);
        }
    };

    expect(fn () => $scraper->exposedFetchDom('https://example.com/archive'))
        ->toThrow(ScrapingException::class, 'HTTP 404');
});

// ── BaseArticleScraper::fetchDom: UTF-8 handling ─────────────────────────────

test('fetchDom preserves Sinhala and Tamil text', function () {
    $scraper = scraperForHtmlFixture('<html><body><p>ශ්‍රී ලංකා ප්‍රවෘත්ති</p><p>தமிழ் செய்திகள்</p></body></html>');

    $dom = $scraper->exposedFetchDom('https://example.com/page');

    expect($dom->textContent)->toContain('ශ්‍රී ලංකා ප්‍රවෘත්ති')
        ->and($dom->textContent)->toContain('தமிழ் செய்திகள்')
        ->and($dom->textContent)->not->toContain("\u{FFFD}");
});

test('fetchDom preserves Sinhala text even without a charset meta tag', function () {
    $scraper = scraperForHtmlFixture('<html><body><p>අද ප්‍රධාන පුවත්</p></body></html>');

    $dom = $scraper->exposedFetchDom('https://example.com/page');

    expect($dom->textContent)->toContain('අද ප්‍රධාන පුවත්')
        ->and($dom->textContent)->not->toContain("\u{FFFD}");
});

test('fetchDom decodes HTML entities correctly', function () {
    $scraper = scraperForHtmlFixture('<html><body><p>Quote &quot;test&quot; &amp; non-breaking&nbsp;space</p></body></html>');

    $dom = $scraper->exposedFetchDom('https://example.com/page');

    expect($dom->textContent)->toContain('Quote "test" & non-breaking'."\u{00A0}".'space');
});

// ── ScrapingException ────────────────────────────────────────────────────────

test('ScrapingException::forSource includes source slug in message', function () {
    $source = NewsSource::factory()->create(['slug' => 'ada-derana']);

    $exception = ScrapingException::forSource($source, 'timeout');

    expect($exception->getMessage())->toContain('ada-derana')
        ->and($exception->getMessage())->toContain('timeout');
});

test('ScrapingException::forInvalidScraperClass includes class name in message', function () {
    $exception = ScrapingException::forInvalidScraperClass('App\\Services\\Scrapers\\FakeClass');

    expect($exception->getMessage())->toContain('FakeClass');
});

// ── Helpers ──────────────────────────────────────────────────────────────────

/**
 * Build a scraper with a faked HTTP response and expose fetchDom for testing.
 *
 * @return PlaceholderScraper
 */
function scraperForHtmlFixture(string $html): object
{
    Http::fake([
        'https://example.com/page' => Http::response($html, 200),
    ]);

    $source = NewsSource::factory()->create([
        'scraper_class' => PlaceholderScraper::class,
        'archive_url' => 'https://example.com/page',
    ]);

    return new class($source, app(HttpFactory::class)) extends PlaceholderScraper
    {
        public function exposedFetchDom(string $url): DOMDocument
        {
            return $this->fetchDom($url);
        }
    };
}
