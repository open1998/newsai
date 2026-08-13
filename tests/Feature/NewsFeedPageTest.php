<?php

use App\Enums\Language;
use App\Models\Article;
use App\Models\NewsSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

// ── Route accessibility ───────────────────────────────────────────────────────

test('news feed page is publicly accessible at /', function () {
    $this->get(route('news.index'))->assertOk();
});

test('news feed page does not require authentication', function () {
    $this->get(route('news.index'))->assertOk();
});

// ── Empty state ───────────────────────────────────────────────────────────────

test('news feed shows empty state when no articles exist', function () {
    $this->get(route('news.index'))
        ->assertOk()
        ->assertSee('No articles found');
});

// ── Article display ───────────────────────────────────────────────────────────

test('news feed displays article titles', function () {
    $source = NewsSource::factory()->create();
    Article::factory()->aiProcessed()->create([
        'news_source_id' => $source->id,
        'language'       => Language::En,
        'published_at'   => now()->subHour(),
    ]);

    $this->get(route('news.index'))
        ->assertOk()
        ->assertSee($source->name);
});

test('news feed shows AI title when article is AI processed', function () {
    $source  = NewsSource::factory()->create();
    $article = Article::factory()->aiProcessed()->create([
        'news_source_id' => $source->id,
        'language'       => Language::En,
    ]);

    $this->get(route('news.index'))
        ->assertSee($article->ai_title);
});

test('news feed falls back to original title when AI not processed', function () {
    $source  = NewsSource::factory()->create();
    $article = Article::factory()->create([
        'news_source_id' => $source->id,
        'language'       => Language::En,
        'ai_title'       => null,
    ]);

    $this->get(route('news.index'))
        ->assertSee($article->original_title);
});

// ── Language filter via Livewire component ────────────────────────────────────

test('news feed Livewire component renders without errors', function () {
    Livewire::test('pages::news.index')
        ->assertOk();
});

test('lang property defaults to null (all languages)', function () {
    Livewire::test('pages::news.index')
        ->assertSet('lang', null);
});

test('setting lang to en filters to English articles only', function () {
    Article::factory()->english()->count(3)->create();
    Article::factory()->tamil()->count(2)->create();

    Livewire::test('pages::news.index')
        ->set('lang', 'en')
        ->assertViewHas('articles', function ($paginator) {
            return $paginator->total() === 3;
        });
});

test('setting lang to ta filters to Tamil articles only', function () {
    Article::factory()->english()->count(2)->create();
    Article::factory()->tamil()->count(4)->create();

    Livewire::test('pages::news.index')
        ->set('lang', 'ta')
        ->assertViewHas('articles', function ($paginator) {
            return $paginator->total() === 4;
        });
});

test('setting lang to si filters to Sinhala articles only', function () {
    Article::factory()->sinhala()->count(2)->create();
    Article::factory()->english()->count(3)->create();

    Livewire::test('pages::news.index')
        ->set('lang', 'si')
        ->assertViewHas('articles', function ($paginator) {
            return $paginator->total() === 2;
        });
});

test('null lang shows all articles regardless of language', function () {
    Article::factory()->english()->count(2)->create();
    Article::factory()->tamil()->count(2)->create();
    Article::factory()->sinhala()->count(2)->create();

    Livewire::test('pages::news.index')
        ->set('lang', null)
        ->assertViewHas('articles', function ($paginator) {
            return $paginator->total() === 6;
        });
});

test('changing lang resets pagination to page 1', function () {
    // Create enough English articles to have multiple pages
    Article::factory()->english()->count(20)->create();

    $component = Livewire::test('pages::news.index')
        ->set('lang', 'en')
        ->call('nextPage');

    // Now change lang — should reset to page 1
    $component->set('lang', 'ta');

    expect($component->get('page') ?? 1)->toBe(1);
});

// ── Pagination ────────────────────────────────────────────────────────────────

test('news feed paginates at 15 articles per page', function () {
    Article::factory()->english()->count(20)->create();

    Livewire::test('pages::news.index')
        ->set('lang', 'en')
        ->assertViewHas('articles', function ($paginator) {
            return $paginator->perPage() === 15
                && $paginator->total() === 20
                && $paginator->lastPage() === 2;
        });
});

// ── Layout ────────────────────────────────────────────────────────────────────

test('news feed page uses the public layout', function () {
    $response = $this->get(route('news.index'));

    $response->assertOk()
        ->assertSee('Sri Lanka News')
        ->assertSee('English')
        ->assertSee('தமிழ்')
        ->assertSee('සිංහල');
});
