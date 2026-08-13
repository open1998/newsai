<?php

use App\Contracts\ArticleRepositoryInterface;
use App\Contracts\ScraperInterface;
use App\Enums\Language;
use App\Exceptions\ScrapingException;
use App\Jobs\ProcessArticleWithAiJob;
use App\Jobs\ScrapeSourceJob;
use App\Models\Article;
use App\Models\NewsSource;
use App\Services\Scrapers\AdaDeranaScraper;
use App\Services\Scrapers\ScraperFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

const ADA_DERANA_SITEMAP_URL = 'https://adaderana.lk/news-sitemap.xml';

// ── Architecture ─────────────────────────────────────────────────────────────

test('AdaDeranaScraper implements ScraperInterface', function () {
    $source = NewsSource::factory()->adaDerana()->create();

    $scraper = new AdaDeranaScraper($source, app(HttpFactory::class));

    expect($scraper)->toBeInstanceOf(ScraperInterface::class);
});

test('ScraperFactory resolves AdaDeranaScraper for a NewsSource', function () {
    $source = NewsSource::factory()->adaDerana()->create();

    $scraper = app(ScraperFactory::class)->make($source);

    expect($scraper)->toBeInstanceOf(AdaDeranaScraper::class);
});

// ── scrapeArchive: sitemap ───────────────────────────────────────────────────

test('scrapeArchive parses the sitemap and returns CUID article URLs only', function () {
    Http::fake([
        ADA_DERANA_SITEMAP_URL => Http::response(adaDeranaFixture('news-sitemap.xml'), 200),
    ]);

    $source = NewsSource::factory()->adaDerana()->create();

    $scraper = new AdaDeranaScraper($source, app(HttpFactory::class));

    expect($scraper->scrapeArchive())->toBe([
        'https://adaderana.lk/news/cmsrrdxm8000a356q3041osft',
        'https://adaderana.lk/news/cmsroyvzu0009356qg4rz5jp0',
        'https://adaderana.lk/news/cmsr9elup000d356p5dkhj3xr',
    ]);
});

test('scrapeArchive filters out articles published before last_scraped_at', function () {
    Http::fake([
        ADA_DERANA_SITEMAP_URL => Http::response(adaDeranaFixture('news-sitemap.xml'), 200),
    ]);

    $source = NewsSource::factory()->adaDerana()->create([
        'last_scraped_at' => '2026-08-13 00:00:00',
    ]);

    $scraper = new AdaDeranaScraper($source, app(HttpFactory::class));

    expect($scraper->scrapeArchive())->toBe([
        'https://adaderana.lk/news/cmsrrdxm8000a356q3041osft',
        'https://adaderana.lk/news/cmsroyvzu0009356qg4rz5jp0',
    ]);
});

test('scrapeArchive returns an empty array for an empty sitemap', function () {
    Http::fake([
        ADA_DERANA_SITEMAP_URL => Http::response(
            '<?xml version="1.0"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></urlset>',
            200,
        ),
    ]);

    $source = NewsSource::factory()->adaDerana()->create();

    $scraper = new AdaDeranaScraper($source, app(HttpFactory::class));

    expect($scraper->scrapeArchive())->toBe([]);
    Http::assertSentCount(1);
});

// ── scrapeArchive: listing fallback ──────────────────────────────────────────

test('scrapeArchive falls back to the listing when the sitemap request fails', function () {
    Http::fake([
        ADA_DERANA_SITEMAP_URL => Http::response('Server Error', 500),
        'https://adaderana.lk/categories/latest?page=0&pageSize=20' => Http::response(adaDeranaFixture('listing-page-0.html'), 200),
        'https://adaderana.lk/categories/latest?page=1&pageSize=20' => Http::response(adaDeranaFixture('listing-page-1.html'), 200),
        'https://adaderana.lk/categories/latest?page=2&pageSize=20' => Http::response(adaDeranaFixture('listing-page-2.html'), 200),
    ]);

    $source = NewsSource::factory()->adaDerana()->create();

    $scraper = new AdaDeranaScraper($source, app(HttpFactory::class));

    $urls = $scraper->scrapeArchive();

    expect($urls)->toBe([
        'https://adaderana.lk/news/cmsrrdxm8000a356q3041osft',
        'https://adaderana.lk/news/cmsroyvzu0009356qg4rz5jp0',
        'https://adaderana.lk/news/cmsr9elup000d356p5dkhj3xr',
    ])
        // Numeric legacy IDs, category and external links are ignored; duplicates are removed.
        ->and($urls)->not->toContain('https://adaderana.lk/news/115700')
        ->and($urls)->not->toContain('https://adaderana.lk/news/230052');

    // Pagination stops on the first page that yields nothing new.
    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'page=3'));
});

test('scrapeArchive falls back to the listing when the sitemap XML is malformed', function () {
    Http::fake([
        ADA_DERANA_SITEMAP_URL => Http::response('this is <<< not valid xml', 200),
        'https://adaderana.lk/categories/latest?page=0&pageSize=20' => Http::response(adaDeranaFixture('listing-page-0.html'), 200),
        'https://adaderana.lk/categories/latest?page=1&pageSize=20' => Http::response(adaDeranaFixture('listing-page-1.html'), 200),
        'https://adaderana.lk/categories/latest?page=2&pageSize=20' => Http::response(adaDeranaFixture('listing-page-2.html'), 200),
    ]);

    $source = NewsSource::factory()->adaDerana()->create();

    $scraper = new AdaDeranaScraper($source, app(HttpFactory::class));

    expect($scraper->scrapeArchive())->toHaveCount(3);
});

test('scrapeArchive throws ScrapingException when both discovery sources fail', function () {
    Http::fake([
        ADA_DERANA_SITEMAP_URL => Http::response('Server Error', 500),
        'https://adaderana.lk/categories/latest?page=0&pageSize=20' => Http::response('Server Error', 500),
    ]);

    $source = NewsSource::factory()->adaDerana()->create();

    $scraper = new AdaDeranaScraper($source, app(HttpFactory::class));

    expect(fn () => $scraper->scrapeArchive())
        ->toThrow(ScrapingException::class, 'HTTP 500');
});

// ── scrapeArticle ────────────────────────────────────────────────────────────

test('scrapeArticle extracts title, body, date and image from the article page', function () {
    Http::fake([
        'https://adaderana.lk/news/cmsrrdxm8000a356q3041osft' => Http::response(adaDeranaFixture('article.html'), 200),
    ]);

    $source = NewsSource::factory()->adaDerana()->create();

    $scraper = new AdaDeranaScraper($source, app(HttpFactory::class));

    $result = $scraper->scrapeArticle('https://adaderana.lk/news/cmsrrdxm8000a356q3041osft');

    expect($result)->toHaveKeys(['title', 'body', 'image_url', 'published_at'])
        ->and($result['title'])->toBe('US can keep naval blockade on Iranian ports "indefinitely," Pentagon chief says')
        ->and($result['body'])->toContain('Hegseth told reporters.') // link text kept inline
        ->and($result['body'])->toContain("\n\n")
        ->and($result['body'])->not->toContain('Sponsored advertisement')
        ->and($result['body'])->not->toContain('nbsp')
        ->and($result['published_at'])->toBe('2026-08-13T16:56:14.495Z')
        ->and($result['image_url'])->toBe('https://ada-derana-prod-english-news-temp.s3.amazonaws.com/articles/cmsrrdxm8000a356q3041osft/MediaFile_20260813_222613_366.jpg');
});

test('scrapeArticle falls back to og:title and og:image when JSON-LD fields are missing', function () {
    Http::fake([
        'https://adaderana.lk/news/cmsroyvzu0009356qg4rz5jp0' => Http::response(adaDeranaFixture('article-minimal.html'), 200),
    ]);

    $source = NewsSource::factory()->adaDerana()->create();

    $scraper = new AdaDeranaScraper($source, app(HttpFactory::class));

    $result = $scraper->scrapeArticle('https://adaderana.lk/news/cmsroyvzu0009356qg4rz5jp0');

    expect($result['title'])->toBe('OG Title Fallback')
        ->and($result['image_url'])->toBe('https://cdn.example.com/fallback-image.jpg')
        ->and($result['published_at'])->toBeNull()
        ->and($result['body'])->toContain('fallback sources');
});

test('scrapeArticle extracts content from legacy numeric-ID article pages', function () {
    Http::fake([
        'https://adaderana.lk/news/115700' => Http::response(adaDeranaFixture('article-legacy.html'), 200),
    ]);

    $source = NewsSource::factory()->adaDerana()->create();

    $scraper = new AdaDeranaScraper($source, app(HttpFactory::class));

    $result = $scraper->scrapeArticle('https://adaderana.lk/news/115700');

    expect($result['title'])->toBe('President calls on Atamasthanadipathi Thero')
        ->and($result['published_at'])->toBe('2025-12-07T13:23:22.000Z')
        ->and($result['image_url'])->toContain('ada-derana-prod-english-news-legacy-temp.s3.amazonaws.com')
        ->and($result['body'])->toContain('Maha Sangha');
});

test('scrapeArticle throws ScrapingException when the page has no NewsArticle JSON-LD', function () {
    Http::fake([
        'https://adaderana.lk/news/cmsr9elup000d356p5dkhj3xr' => Http::response(adaDeranaFixture('article-no-jsonld.html'), 200),
    ]);

    $source = NewsSource::factory()->adaDerana()->create();

    $scraper = new AdaDeranaScraper($source, app(HttpFactory::class));

    expect(fn () => $scraper->scrapeArticle('https://adaderana.lk/news/cmsr9elup000d356p5dkhj3xr'))
        ->toThrow(ScrapingException::class, 'NewsArticle JSON-LD');
});

test('scrapeArticle throws ScrapingException when the body is empty', function () {
    Http::fake([
        'https://adaderana.lk/news/cmsr9elup000d356p5dkhj3xr' => Http::response(adaDeranaFixture('article-empty-body.html'), 200),
    ]);

    $source = NewsSource::factory()->adaDerana()->create();

    $scraper = new AdaDeranaScraper($source, app(HttpFactory::class));

    expect(fn () => $scraper->scrapeArticle('https://adaderana.lk/news/cmsr9elup000d356p5dkhj3xr'))
        ->toThrow(ScrapingException::class, 'no body');
});

test('scrapeArticle throws ScrapingException when the canonical URL points to a different article', function () {
    Http::fake([
        'https://adaderana.lk/news/cmsrrdxm8000a356q3041osft' => Http::response(adaDeranaFixture('article-canonical-mismatch.html'), 200),
    ]);

    $source = NewsSource::factory()->adaDerana()->create();

    $scraper = new AdaDeranaScraper($source, app(HttpFactory::class));

    expect(fn () => $scraper->scrapeArticle('https://adaderana.lk/news/cmsrrdxm8000a356q3041osft'))
        ->toThrow(ScrapingException::class, 'canonical URL mismatch');
});

// ── Job integration ──────────────────────────────────────────────────────────

test('ScrapeSourceJob with AdaDeranaScraper ingests sitemap articles', function () {
    Queue::fake();

    Http::fake([
        ADA_DERANA_SITEMAP_URL => Http::response(adaDeranaFixture('news-sitemap.xml'), 200),
        'https://adaderana.lk/news/cmsrrdxm8000a356q3041osft' => Http::response(adaDeranaFixture('article.html'), 200),
        'https://adaderana.lk/news/cmsroyvzu0009356qg4rz5jp0' => Http::response(adaDeranaFixture('article-minimal.html'), 200),
    ]);

    $source = NewsSource::factory()->adaDerana()->create([
        'last_scraped_at' => '2026-08-13 00:00:00',
    ]);

    (new ScrapeSourceJob($source))->handle(
        app(ScraperFactory::class),
        app(ArticleRepositoryInterface::class),
    );

    expect(Article::count())->toBe(2);
    Queue::assertPushed(ProcessArticleWithAiJob::class, 2);

    $article = Article::where('source_url', 'https://adaderana.lk/news/cmsrrdxm8000a356q3041osft')->first();

    expect($article)->not->toBeNull()
        ->and($article->original_title)->toContain('naval blockade')
        ->and($article->language)->toBe(Language::En)
        ->and($article->published_at?->toDateString())->toBe('2026-08-13')
        ->and($article->original_image_url)->toContain('MediaFile_20260813_222613_366.jpg');

    $minimal = Article::where('source_url', 'https://adaderana.lk/news/cmsroyvzu0009356qg4rz5jp0')->first();

    expect($minimal?->original_title)->toBe('OG Title Fallback')
        ->and($minimal?->published_at)->toBeNull();
});

// ── Helpers ──────────────────────────────────────────────────────────────────

/**
 * Load a fixture file from tests/Fixtures/ada-derana.
 */
function adaDeranaFixture(string $name): string
{
    return (string) file_get_contents(__DIR__.'/../Fixtures/ada-derana/'.$name);
}
