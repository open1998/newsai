<?php

use App\Jobs\ScrapeSourceJob;
use App\Models\NewsSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

// ── news:scrape command ───────────────────────────────────────────────────────

test('news:scrape dispatches a job for each active source', function () {
    Queue::fake();

    NewsSource::factory()->count(3)->create(['is_active' => true]);

    $this->artisan('news:scrape')
        ->assertSuccessful();

    Queue::assertPushed(ScrapeSourceJob::class, 3);
});

test('news:scrape skips inactive sources', function () {
    Queue::fake();

    NewsSource::factory()->count(2)->create(['is_active' => true]);
    NewsSource::factory()->count(3)->inactive()->create();

    $this->artisan('news:scrape')
        ->assertSuccessful();

    Queue::assertPushed(ScrapeSourceJob::class, 2);
});

test('news:scrape --source dispatches only the matching source', function () {
    Queue::fake();

    $target = NewsSource::factory()->create(['is_active' => true, 'slug' => 'ada-derana']);
    NewsSource::factory()->count(2)->create(['is_active' => true]);

    $this->artisan('news:scrape', ['--source' => 'ada-derana'])
        ->assertSuccessful();

    Queue::assertPushed(ScrapeSourceJob::class, 1);
    Queue::assertPushed(ScrapeSourceJob::class, fn ($job) => $job->newsSource->id === $target->id);
});

test('news:scrape --source exits with failure for unknown slug', function () {
    Queue::fake();

    $this->artisan('news:scrape', ['--source' => 'nonexistent-source'])
        ->assertFailed();

    Queue::assertNothingPushed();
});

test('news:scrape exits with failure when no active sources exist', function () {
    Queue::fake();

    NewsSource::factory()->count(2)->inactive()->create();

    $this->artisan('news:scrape')
        ->assertFailed();

    Queue::assertNothingPushed();
});

test('news:scrape --source only matches active sources', function () {
    Queue::fake();

    // Create an inactive source with the target slug
    NewsSource::factory()->inactive()->create(['slug' => 'inactive-source']);

    $this->artisan('news:scrape', ['--source' => 'inactive-source'])
        ->assertFailed();

    Queue::assertNothingPushed();
});
