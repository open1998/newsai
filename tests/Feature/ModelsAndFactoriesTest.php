<?php

use App\Enums\AiStatus;
use App\Enums\Language;
use App\Enums\ScrapeStatus;
use App\Models\Article;
use App\Models\NewsSource;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ── Language enum ────────────────────────────────────────────────────────────

test('Language enum has correct values', function () {
    expect(Language::En->value)->toBe('en')
        ->and(Language::Ta->value)->toBe('ta')
        ->and(Language::Si->value)->toBe('si');
});

test('Language enum labels are human readable', function () {
    expect(Language::En->label())->toBe('English')
        ->and(Language::Ta->label())->toBe('Tamil')
        ->and(Language::Si->label())->toBe('Sinhala');
});

// ── ScrapeStatus enum ────────────────────────────────────────────────────────

test('ScrapeStatus enum has correct values', function () {
    expect(ScrapeStatus::Pending->value)->toBe('pending')
        ->and(ScrapeStatus::Succeeded->value)->toBe('succeeded')
        ->and(ScrapeStatus::Failed->value)->toBe('failed');
});

// ── AiStatus enum ────────────────────────────────────────────────────────────

test('AiStatus enum has correct values', function () {
    expect(AiStatus::Pending->value)->toBe('pending')
        ->and(AiStatus::Processing->value)->toBe('processing')
        ->and(AiStatus::Succeeded->value)->toBe('succeeded')
        ->and(AiStatus::Failed->value)->toBe('failed')
        ->and(AiStatus::Skipped->value)->toBe('skipped');
});

// ── NewsSource model ─────────────────────────────────────────────────────────

test('NewsSource factory creates a valid model', function () {
    $source = NewsSource::factory()->create();

    expect($source)->toBeInstanceOf(NewsSource::class)
        ->and($source->id)->toBeInt()
        ->and($source->name)->toBeString()->not->toBeEmpty()
        ->and($source->slug)->toBeString()->not->toBeEmpty()
        ->and($source->language)->toBeInstanceOf(Language::class)
        ->and($source->is_active)->toBeTrue();
});

test('NewsSource factory language states work', function () {
    expect(NewsSource::factory()->english()->create()->language)->toBe(Language::En)
        ->and(NewsSource::factory()->tamil()->create()->language)->toBe(Language::Ta)
        ->and(NewsSource::factory()->sinhala()->create()->language)->toBe(Language::Si);
});

test('NewsSource factory inactive state works', function () {
    $source = NewsSource::factory()->inactive()->create();

    expect($source->is_active)->toBeFalse();
});

test('NewsSource slug is unique', function () {
    NewsSource::factory()->create(['slug' => 'test-source']);

    expect(fn () => NewsSource::factory()->create(['slug' => 'test-source']))
        ->toThrow(\Illuminate\Database\QueryException::class);
});

// ── Article model ────────────────────────────────────────────────────────────

test('Article factory creates a valid model', function () {
    $article = Article::factory()->create();

    expect($article)->toBeInstanceOf(Article::class)
        ->and($article->id)->toBeInt()
        ->and($article->news_source_id)->toBeInt()
        ->and($article->original_title)->toBeString()->not->toBeEmpty()
        ->and($article->original_body)->toBeString()->not->toBeEmpty()
        ->and($article->language)->toBeInstanceOf(Language::class)
        ->and($article->scrape_status)->toBe(ScrapeStatus::Succeeded)
        ->and($article->ai_status)->toBe(AiStatus::Pending)
        ->and($article->content_hash)->toHaveLength(64);
});

test('Article factory aiProcessed state sets all AI fields', function () {
    $article = Article::factory()->aiProcessed()->create();

    expect($article->ai_status)->toBe(AiStatus::Succeeded)
        ->and($article->ai_title)->toBeString()->not->toBeEmpty()
        ->and($article->ai_body)->toBeString()->not->toBeEmpty()
        ->and($article->ai_summary)->toBeString()->not->toBeEmpty()
        ->and($article->ai_processed_at)->not->toBeNull();
});

test('Article factory scrapeFailed state sets error fields', function () {
    $article = Article::factory()->scrapeFailed()->create();

    expect($article->scrape_status)->toBe(ScrapeStatus::Failed)
        ->and($article->last_scrape_error)->toBeString()->not->toBeEmpty();
});

// ── Article-NewsSource relationship ──────────────────────────────────────────

test('Article belongs to a NewsSource', function () {
    $source = NewsSource::factory()->create();
    $article = Article::factory()->create(['news_source_id' => $source->id]);

    expect($article->newsSource)->toBeInstanceOf(NewsSource::class)
        ->and($article->newsSource->id)->toBe($source->id);
});

test('NewsSource has many Articles', function () {
    $source = NewsSource::factory()->create();
    Article::factory()->count(3)->create(['news_source_id' => $source->id]);

    expect($source->articles)->toHaveCount(3);
});

// ── Unique constraint on (news_source_id, source_url) ────────────────────────

test('articles table enforces unique constraint on news_source_id and source_url', function () {
    $source = NewsSource::factory()->create();
    Article::factory()->create(['news_source_id' => $source->id, 'source_url' => 'https://example.com/article/1']);

    expect(fn () => Article::factory()->create([
        'news_source_id' => $source->id,
        'source_url' => 'https://example.com/article/1',
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});

test('same source_url is allowed for different news sources', function () {
    $source1 = NewsSource::factory()->create();
    $source2 = NewsSource::factory()->create();

    Article::factory()->create(['news_source_id' => $source1->id, 'source_url' => 'https://example.com/article/1']);
    $article = Article::factory()->create(['news_source_id' => $source2->id, 'source_url' => 'https://example.com/article/1']);

    expect($article->id)->toBeInt();
});

// ── Article display methods ───────────────────────────────────────────────────

test('displayTitle returns AI title when available', function () {
    $article = Article::factory()->aiProcessed()->create();

    expect($article->displayTitle())->toBe($article->ai_title);
});

test('displayTitle falls back to original title when AI not processed', function () {
    $article = Article::factory()->create(['ai_title' => null]);

    expect($article->displayTitle())->toBe($article->original_title);
});

test('displayBody returns AI body when available', function () {
    $article = Article::factory()->aiProcessed()->create();

    expect($article->displayBody())->toBe($article->ai_body);
});

// ── Article scopes ───────────────────────────────────────────────────────────

test('scopeAiProcessed returns only successfully processed articles', function () {
    Article::factory()->aiProcessed()->count(2)->create();
    Article::factory()->aiFailed()->create();
    Article::factory()->create(); // pending

    expect(Article::aiProcessed()->count())->toBe(2);
});

test('scopeForLanguage filters by language', function () {
    Article::factory()->english()->count(3)->create();
    Article::factory()->tamil()->count(2)->create();

    expect(Article::forLanguage(Language::En)->count())->toBe(3)
        ->and(Article::forLanguage(Language::Ta)->count())->toBe(2);
});
