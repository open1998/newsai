<?php

use App\Contracts\ArticleRepositoryInterface;
use App\Enums\AiStatus;
use App\Models\Article;
use App\Models\NewsSource;
use App\Models\User;
use App\Repositories\ArticleRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

// ── Source stats ──────────────────────────────────────────────────────────────

test('dashboard lists each source with its totals and status', function () {
    $user = User::factory()->create();

    NewsSource::factory()->adaDerana()->create(['name' => 'Ada Derana']);
    NewsSource::factory()->inactive()->create(['name' => 'Other News', 'slug' => 'other-news']);

    Article::factory()->create(['news_source_id' => NewsSource::where('slug', 'ada-derana')->value('id')]);
    Article::factory()->create(['news_source_id' => NewsSource::where('slug', 'other-news')->value('id')]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Ada Derana')
        ->assertSee('ada-derana')
        ->assertSee('Other News')
        ->assertSee('other-news')
        ->assertSee('English')
        ->assertSee('Active')
        ->assertSee('Inactive');
});

test('dashboard component exposes per-source aggregates through the repository', function () {
    $source = NewsSource::factory()->adaDerana()->create();

    Article::factory()->aiProcessed()->count(2)->create(['news_source_id' => $source->id]);
    Article::factory()->create(['news_source_id' => $source->id, 'ai_status' => AiStatus::Pending]);
    Article::factory()->aiFailed()->create(['news_source_id' => $source->id]);

    Livewire::test('pages::dashboard')
        ->assertViewHas('sources', function ($sources) use ($source) {
            $first = $sources->first();

            return $sources->count() === 1
                && $first->id === $source->id
                && $first->articles_count === 4
                && $first->ai_succeeded_count === 2
                && $first->ai_pending_count === 1
                && $first->ai_failed_count === 1;
        });
});

test('dashboard shows global article totals', function () {
    $sourceA = NewsSource::factory()->create();
    $sourceB = NewsSource::factory()->create();

    Article::factory()->count(3)->create(['news_source_id' => $sourceA->id]);
    Article::factory()->count(2)->create(['news_source_id' => $sourceB->id]);

    Livewire::test('pages::dashboard')
        ->assertSee('Total articles')
        ->assertSee('AI succeeded')
        ->assertSee('AI pending')
        ->assertSee('AI failed')
        ->assertSee('5'); // 3 + 2 — the total only appears in the stat card
});

test('dashboard shows the last scraped time when set', function () {
    $source = NewsSource::factory()->create(['last_scraped_at' => '2026-08-01 10:00:00']);
    Article::factory()->create(['news_source_id' => $source->id]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('01 Aug 2026');
});

test('dashboard resolves stats through the repository interface', function () {
    $spy = Mockery::spy(ArticleRepository::class)->makePartial();
    $spy->shouldReceive('getSourceStats')
        ->once()
        ->andReturn(new Collection);

    app()->instance(ArticleRepositoryInterface::class, $spy);

    $user = User::factory()->create();

    $this->actingAs($user)->get(route('dashboard'))->assertOk();

    $spy->shouldHaveReceived('getSourceStats')->once();
});
