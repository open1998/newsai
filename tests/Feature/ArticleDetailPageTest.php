<?php

use App\Contracts\ArticleRepositoryInterface;
use App\Enums\AiStatus;
use App\Enums\Language;
use App\Models\Article;
use App\Models\NewsSource;
use App\Repositories\ArticleRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

// ── 1. Route is publicly accessible ──────────────────────────────────────────

test('article detail route is publicly accessible without authentication', function () {
    $article = Article::factory()->create();

    $this->get(route('news.show', $article))
        ->assertOk();
});

// ── 2. Correct article is displayed ──────────────────────────────────────────

test('article detail page displays the correct article', function () {
    $source  = NewsSource::factory()->create(['name' => 'Daily Mirror LK']);
    $article = Article::factory()->aiProcessed()->create([
        'news_source_id' => $source->id,
        'original_title' => 'Unique Original Headline Here',
    ]);

    $this->get(route('news.show', $article))
        ->assertOk()
        ->assertSee('Daily Mirror LK');
});

// ── 3. AI title displayed when available ─────────────────────────────────────

test('AI title is shown when article has been AI processed', function () {
    $article = Article::factory()->aiProcessed()->create([
        'ai_title'       => 'AI Rewritten Title',
        'original_title' => 'Original Title',
    ]);

    $this->get(route('news.show', $article))
        ->assertSee('AI Rewritten Title')
        ->assertDontSee('Original Title');
});

// ── 4. Original title fallback ────────────────────────────────────────────────

test('original title is shown when AI has not processed the article', function () {
    $article = Article::factory()->create([
        'ai_title'       => null,
        'original_title' => 'Unprocessed Original Title',
    ]);

    $this->get(route('news.show', $article))
        ->assertSee('Unprocessed Original Title');
});

// ── 5. AI body displayed when available ──────────────────────────────────────

test('AI body is shown when article has been AI processed', function () {
    $article = Article::factory()->aiProcessed()->create([
        'ai_body'       => 'AI rewritten body paragraph.',
        'original_body' => 'Original body paragraph.',
    ]);

    $this->get(route('news.show', $article))
        ->assertSee('AI rewritten body paragraph.')
        ->assertDontSee('Original body paragraph.');
});

// ── 6. Original body fallback ─────────────────────────────────────────────────

test('original body is shown when AI has not processed the article', function () {
    $article = Article::factory()->create([
        'ai_body'       => null,
        'original_body' => 'The original body of the article.',
    ]);

    $this->get(route('news.show', $article))
        ->assertSee('The original body of the article.');
});

// ── 7. AI summary displayed when available ────────────────────────────────────

test('AI summary callout is shown when available', function () {
    $article = Article::factory()->aiProcessed()->create([
        'ai_summary' => 'A concise AI-generated summary.',
    ]);

    $this->get(route('news.show', $article))
        ->assertSee('AI Summary')
        ->assertSee('A concise AI-generated summary.');
});

test('AI summary callout is not shown when summary is null', function () {
    $article = Article::factory()->create(['ai_summary' => null]);

    $this->get(route('news.show', $article))
        ->assertDontSee('AI Summary');
});

// ── 8. Original source link is displayed ─────────────────────────────────────

test('original source link points to the article source URL', function () {
    $article = Article::factory()->create([
        'source_url' => 'https://adaderana.lk/news/12345/test-article',
    ]);

    $this->get(route('news.show', $article))
        ->assertSee('https://adaderana.lk/news/12345/test-article');
});

// ── 9. Language, source name, and published date are shown ────────────────────

test('language badge is displayed on the article page', function () {
    $article = Article::factory()->english()->create();

    $this->get(route('news.show', $article))
        ->assertSee('English');
});

test('source name is displayed on the article page', function () {
    $source  = NewsSource::factory()->create(['name' => 'Hiru News']);
    $article = Article::factory()->create(['news_source_id' => $source->id]);

    $this->get(route('news.show', $article))
        ->assertSee('Hiru News');
});

test('published date is displayed when set', function () {
    $article = Article::factory()->create([
        'published_at' => \Illuminate\Support\Carbon::parse('2026-01-15 10:00:00'),
    ]);

    $this->get(route('news.show', $article))
        ->assertSee('15 Jan 2026');
});

// ── 10. Unknown article returns 404 ──────────────────────────────────────────

test('requesting an unknown article id returns 404', function () {
    $this->get(route('news.show', 999999))
        ->assertNotFound();
});

test('Livewire component aborts with 404 for unknown article id', function () {
    Livewire::test('pages::news.show', ['article' => 999999])
        ->assertStatus(404);
});

// ── 11. Page uses the public layout ──────────────────────────────────────────

test('article detail page renders the public layout with language tabs', function () {
    $article = Article::factory()->english()->create();

    $this->get(route('news.show', $article))
        ->assertOk()
        ->assertSee('English')   // layout nav tab
        ->assertSee('தமிழ்')    // layout nav tab
        ->assertSee('සිංහල');   // layout nav tab
});

// ── 12. Repository abstraction is respected ───────────────────────────────────

test('article detail page resolves the article through the repository interface', function () {
    $article = Article::factory()->create();

    // Swap in a spy to verify the repository is called
    $spy = Mockery::spy(ArticleRepository::class)->makePartial();
    $spy->shouldReceive('findById')
        ->once()
        ->with($article->id)
        ->andReturn($article->load('newsSource'));

    app()->instance(ArticleRepositoryInterface::class, $spy);

    $this->get(route('news.show', $article))->assertOk();

    $spy->shouldHaveReceived('findById')->with($article->id);
});
