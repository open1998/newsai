<?php

use App\Contracts\ArticleAiDriverInterface;
use App\Contracts\ArticleRepositoryInterface;
use App\Contracts\ScraperInterface;
use App\Enums\AiStatus;
use App\Enums\Language;
use App\Exceptions\AiProcessingException;
use App\Jobs\ProcessArticleWithAiJob;
use App\Jobs\ScrapeSourceJob;
use App\Models\Article;
use App\Models\NewsSource;
use App\Services\Scrapers\PlaceholderScraper;
use App\Services\Scrapers\ScraperFactory;
use Illuminate\Contracts\Container\Container;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

// ── ScrapeSourceJob ──────────────────────────────────────────────────────────

test('ScrapeSourceJob updates last_scraped_at on the news source', function () {
    Queue::fake();

    $source = NewsSource::factory()->create(['scraper_class' => PlaceholderScraper::class]);

    expect($source->last_scraped_at)->toBeNull();

    (new ScrapeSourceJob($source))->handle(
        app(ScraperFactory::class),
        app(ArticleRepositoryInterface::class),
    );

    expect($source->fresh()->last_scraped_at)->not->toBeNull();
});

test('ScrapeSourceJob with PlaceholderScraper creates no articles', function () {
    Queue::fake();

    $source = NewsSource::factory()->create(['scraper_class' => PlaceholderScraper::class]);

    (new ScrapeSourceJob($source))->handle(
        app(ScraperFactory::class),
        app(ArticleRepositoryInterface::class),
    );

    expect(Article::count())->toBe(0);
    Queue::assertNothingPushed();
});

test('ScrapeSourceJob dispatches ProcessArticleWithAiJob for each new article', function () {
    Queue::fake();

    $source = NewsSource::factory()->english()->create();

    // Create a scraper that returns two article URLs
    $fakeScraper = new class($source, app(Factory::class)) extends PlaceholderScraper
    {
        public function scrapeArchive(): array
        {
            return [
                'https://example.com/article/1',
                'https://example.com/article/2',
            ];
        }

        public function scrapeArticle(string $url): array
        {
            return [
                'title' => 'Test Title for '.$url,
                'body' => 'Test body content.',
                'image_url' => null,
                'published_at' => null,
            ];
        }
    };

    // Bind the fake scraper via a custom factory
    $factory = new class(app(Container::class)) extends ScraperFactory
    {
        private PlaceholderScraper $customScraper;

        public function setCustomScraper(PlaceholderScraper $scraper): void
        {
            $this->customScraper = $scraper;
        }

        public function make(NewsSource $source): ScraperInterface
        {
            return $this->customScraper;
        }
    };
    $factory->setCustomScraper($fakeScraper);

    (new ScrapeSourceJob($source))->handle($factory, app(ArticleRepositoryInterface::class));

    expect(Article::count())->toBe(2);
    Queue::assertPushed(ProcessArticleWithAiJob::class, 2);
});

test('ScrapeSourceJob does not re-dispatch AI job when content is unchanged and already processed', function () {
    Queue::fake();

    $source = NewsSource::factory()->english()->create();

    $contentHash = hash('sha256', 'Test TitleTest body content.');

    // Pre-create the article as already AI processed with the same hash
    $existingArticle = Article::factory()->aiProcessed()->create([
        'news_source_id' => $source->id,
        'source_url' => 'https://example.com/article/1',
        'language' => Language::En,
        'content_hash' => $contentHash,
    ]);

    $fakeScraper = new class($source, app(Factory::class)) extends PlaceholderScraper
    {
        public function scrapeArchive(): array
        {
            return ['https://example.com/article/1'];
        }

        public function scrapeArticle(string $url): array
        {
            return [
                'title' => 'Test Title',
                'body' => 'Test body content.',
                'image_url' => null,
                'published_at' => null,
            ];
        }
    };

    $factory = new class(app(Container::class)) extends ScraperFactory
    {
        private PlaceholderScraper $customScraper;

        public function setCustomScraper(PlaceholderScraper $scraper): void
        {
            $this->customScraper = $scraper;
        }

        public function make(NewsSource $source): ScraperInterface
        {
            return $this->customScraper;
        }
    };
    $factory->setCustomScraper($fakeScraper);

    (new ScrapeSourceJob($source))->handle($factory, app(ArticleRepositoryInterface::class));

    // ai_processed_at is set and content unchanged — AI job should NOT be dispatched
    Queue::assertNotPushed(ProcessArticleWithAiJob::class);
});

// ── ProcessArticleWithAiJob ──────────────────────────────────────────────────

test('ProcessArticleWithAiJob sets ai_status to Processing then Succeeded', function () {
    $article = Article::factory()->create(['ai_status' => AiStatus::Pending]);

    $mockDriver = Mockery::mock(ArticleAiDriverInterface::class);
    $mockDriver->shouldReceive('rewrite')
        ->once()
        ->with(Mockery::on(fn ($a) => $a->id === $article->id))
        ->andReturn([
            'ai_title' => 'AI Title',
            'ai_body' => 'AI Body',
            'ai_summary' => 'AI Summary',
        ]);

    app()->instance(ArticleAiDriverInterface::class, $mockDriver);

    (new ProcessArticleWithAiJob($article))->handle(
        $mockDriver,
        app(ArticleRepositoryInterface::class),
    );

    $article->refresh();

    expect($article->ai_status)->toBe(AiStatus::Succeeded)
        ->and($article->ai_title)->toBe('AI Title')
        ->and($article->ai_body)->toBe('AI Body')
        ->and($article->ai_summary)->toBe('AI Summary')
        ->and($article->ai_processed_at)->not->toBeNull();
});

test('ProcessArticleWithAiJob marks ai_status as Skipped when already processed', function () {
    $article = Article::factory()->aiProcessed()->create();

    $mockDriver = Mockery::mock(ArticleAiDriverInterface::class);
    $mockDriver->shouldNotReceive('rewrite');

    (new ProcessArticleWithAiJob($article))->handle(
        $mockDriver,
        app(ArticleRepositoryInterface::class),
    );

    $article->refresh();

    expect($article->ai_status)->toBe(AiStatus::Skipped);
});

test('ProcessArticleWithAiJob marks ai_status as Failed and rethrows on AiProcessingException', function () {
    $article = Article::factory()->create(['ai_status' => AiStatus::Pending]);

    $mockDriver = Mockery::mock(ArticleAiDriverInterface::class);
    $mockDriver->shouldReceive('rewrite')
        ->once()
        ->andThrow(AiProcessingException::forParseFailure('bad json'));

    expect(fn () => (new ProcessArticleWithAiJob($article))->handle(
        $mockDriver,
        app(ArticleRepositoryInterface::class),
    ))->toThrow(AiProcessingException::class);

    $article->refresh();

    expect($article->ai_status)->toBe(AiStatus::Failed);
});
