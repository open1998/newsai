<?php

use App\Contracts\ArticleRepositoryInterface;
use App\Enums\AiStatus;
use App\Enums\Language;
use App\Enums\ScrapeStatus;
use App\Models\Article;
use App\Models\NewsSource;
use App\Repositories\ArticleRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ── Container binding ────────────────────────────────────────────────────────

test('ArticleRepositoryInterface resolves to ArticleRepository', function () {
    $resolved = app(ArticleRepositoryInterface::class);

    expect($resolved)->toBeInstanceOf(ArticleRepository::class);
});

// ── upsertFromScrape ─────────────────────────────────────────────────────────

test('upsertFromScrape creates a new article', function () {
    $source = NewsSource::factory()->create();
    $repo = app(ArticleRepositoryInterface::class);

    $article = $repo->upsertFromScrape($source, [
        'source_url' => 'https://example.com/article/1',
        'language' => Language::En,
        'content_hash' => hash('sha256', 'TitleBody'),
        'original_title' => 'Title',
        'original_body' => 'Body',
        'original_image_url' => null,
        'published_at' => null,
    ]);

    expect($article)->toBeInstanceOf(Article::class)
        ->and($article->id)->toBeInt()
        ->and($article->original_title)->toBe('Title')
        ->and($article->scrape_status)->toBe(ScrapeStatus::Succeeded)
        ->and($article->news_source_id)->toBe($source->id);
});

test('upsertFromScrape updates existing article on same (news_source_id, source_url)', function () {
    $source = NewsSource::factory()->create();
    $repo = app(ArticleRepositoryInterface::class);

    $baseData = [
        'source_url' => 'https://example.com/article/1',
        'language' => Language::En,
        'content_hash' => hash('sha256', 'OldTitleOldBody'),
        'original_title' => 'Old Title',
        'original_body' => 'Old Body',
        'original_image_url' => null,
        'published_at' => null,
    ];

    $first = $repo->upsertFromScrape($source, $baseData);

    $updatedData = array_merge($baseData, [
        'content_hash' => hash('sha256', 'New TitleNew Body'),
        'original_title' => 'New Title',
        'original_body' => 'New Body',
    ]);

    $second = $repo->upsertFromScrape($source, $updatedData);

    expect($second->id)->toBe($first->id) // same row
        ->and($second->original_title)->toBe('New Title')
        ->and($second->content_hash)->toBe(hash('sha256', 'New TitleNew Body'));

    expect(Article::count())->toBe(1); // no duplicate row
});

test('upsertFromScrape allows same source_url for different news sources', function () {
    $source1 = NewsSource::factory()->create();
    $source2 = NewsSource::factory()->create();
    $repo = app(ArticleRepositoryInterface::class);

    $data = [
        'source_url' => 'https://example.com/article/1',
        'language' => Language::En,
        'content_hash' => hash('sha256', 'TitleBody'),
        'original_title' => 'Title',
        'original_body' => 'Body',
        'original_image_url' => null,
        'published_at' => null,
    ];

    $repo->upsertFromScrape($source1, $data);
    $repo->upsertFromScrape($source2, $data);

    expect(Article::count())->toBe(2);
});

// ── markScrapeError ──────────────────────────────────────────────────────────

test('markScrapeError sets scrape_status to Failed and stores error', function () {
    $article = Article::factory()->create(['scrape_status' => ScrapeStatus::Pending]);
    $repo = app(ArticleRepositoryInterface::class);

    $repo->markScrapeError($article, 'Connection timeout');

    $article->refresh();

    expect($article->scrape_status)->toBe(ScrapeStatus::Failed)
        ->and($article->last_scrape_error)->toBe('Connection timeout');
});

// ── markAiProcessed ──────────────────────────────────────────────────────────

test('markAiProcessed stores AI fields and sets ai_status to Succeeded', function () {
    $article = Article::factory()->create(['ai_status' => AiStatus::Processing]);
    $repo = app(ArticleRepositoryInterface::class);

    $repo->markAiProcessed($article, [
        'ai_title' => 'Rewritten Title',
        'ai_body' => 'Rewritten Body',
        'ai_summary' => 'Short summary.',
    ]);

    $article->refresh();

    expect($article->ai_status)->toBe(AiStatus::Succeeded)
        ->and($article->ai_title)->toBe('Rewritten Title')
        ->and($article->ai_body)->toBe('Rewritten Body')
        ->and($article->ai_summary)->toBe('Short summary.')
        ->and($article->ai_processed_at)->not->toBeNull();
});

// ── markAiStatus ─────────────────────────────────────────────────────────────

test('markAiStatus updates only the ai_status field', function () {
    $article = Article::factory()->create(['ai_status' => AiStatus::Pending]);
    $repo = app(ArticleRepositoryInterface::class);

    $repo->markAiStatus($article, AiStatus::Processing);

    $article->refresh();

    expect($article->ai_status)->toBe(AiStatus::Processing);
});

// ── getPaginatedByLanguage ───────────────────────────────────────────────────

test('getPaginatedByLanguage returns all articles when language is null', function () {
    Article::factory()->english()->count(3)->create();
    Article::factory()->tamil()->count(2)->create();
    $repo = app(ArticleRepositoryInterface::class);

    $paginator = $repo->getPaginatedByLanguage(null);

    expect($paginator->total())->toBe(5);
});

test('getPaginatedByLanguage filters by language', function () {
    Article::factory()->english()->count(3)->create();
    Article::factory()->tamil()->count(2)->create();
    $repo = app(ArticleRepositoryInterface::class);

    $paginator = $repo->getPaginatedByLanguage(Language::En);

    expect($paginator->total())->toBe(3);
});

test('getPaginatedByLanguage orders by published_at descending', function () {
    $older = Article::factory()->english()->create(['published_at' => now()->subDays(5)]);
    $newer = Article::factory()->english()->create(['published_at' => now()->subDay()]);
    $repo = app(ArticleRepositoryInterface::class);

    $results = $repo->getPaginatedByLanguage(Language::En)->items();

    expect($results[0]->id)->toBe($newer->id)
        ->and($results[1]->id)->toBe($older->id);
});

// ── getPaginatedByLanguage: source filter ────────────────────────────────────

test('getPaginatedByLanguage filters by source slug', function () {
    $sourceA = NewsSource::factory()->create(['slug' => 'source-a']);
    $sourceB = NewsSource::factory()->create(['slug' => 'source-b']);

    Article::factory()->count(3)->create(['news_source_id' => $sourceA->id]);
    Article::factory()->count(2)->create(['news_source_id' => $sourceB->id]);

    $repo = app(ArticleRepositoryInterface::class);

    expect($repo->getPaginatedByLanguage(null, 15, 'source-a')->total())->toBe(3);
});

test('getPaginatedByLanguage combines language and source filters', function () {
    $sourceA = NewsSource::factory()->create(['slug' => 'source-a']);
    $sourceB = NewsSource::factory()->create(['slug' => 'source-b']);

    Article::factory()->english()->count(3)->create(['news_source_id' => $sourceA->id]);
    Article::factory()->tamil()->count(2)->create(['news_source_id' => $sourceA->id]);
    Article::factory()->english()->count(2)->create(['news_source_id' => $sourceB->id]);

    $repo = app(ArticleRepositoryInterface::class);

    expect($repo->getPaginatedByLanguage(Language::En, 15, 'source-a')->total())->toBe(3);
});

test('getPaginatedByLanguage returns all articles when source slug is null', function () {
    $sourceA = NewsSource::factory()->create(['slug' => 'source-a']);
    $sourceB = NewsSource::factory()->create(['slug' => 'source-b']);

    Article::factory()->count(2)->create(['news_source_id' => $sourceA->id]);
    Article::factory()->count(2)->create(['news_source_id' => $sourceB->id]);

    $repo = app(ArticleRepositoryInterface::class);

    expect($repo->getPaginatedByLanguage(null, 15, null)->total())->toBe(4);
});

// ── getSourcesWithArticles ───────────────────────────────────────────────────

test('getSourcesWithArticles returns only sources that have articles', function () {
    $withArticles = NewsSource::factory()->create(['name' => 'With Articles']);
    $withoutArticles = NewsSource::factory()->create(['name' => 'Without Articles']);

    Article::factory()->create(['news_source_id' => $withArticles->id]);

    $repo = app(ArticleRepositoryInterface::class);

    $names = $repo->getSourcesWithArticles()->pluck('name')->all();

    expect($names)->toContain('With Articles')
        ->and($names)->not->toContain('Without Articles');
});

test('getSourcesWithArticles orders sources by name', function () {
    $zeta = NewsSource::factory()->create(['name' => 'Zeta News']);
    $alpha = NewsSource::factory()->create(['name' => 'Alpha News']);

    Article::factory()->create(['news_source_id' => $zeta->id]);
    Article::factory()->create(['news_source_id' => $alpha->id]);

    $repo = app(ArticleRepositoryInterface::class);

    expect($repo->getSourcesWithArticles()->pluck('name')->all())->toBe(['Alpha News', 'Zeta News']);
});

// ── findById ─────────────────────────────────────────────────────────────────

test('findById returns article by id', function () {
    $article = Article::factory()->create();
    $repo = app(ArticleRepositoryInterface::class);

    $found = $repo->findById($article->id);

    expect($found)->toBeInstanceOf(Article::class)
        ->and($found->id)->toBe($article->id);
});

test('findById returns null for unknown id', function () {
    $repo = app(ArticleRepositoryInterface::class);

    expect($repo->findById(99999))->toBeNull();
});
