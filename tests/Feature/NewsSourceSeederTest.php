<?php

use App\Enums\Language;
use App\Models\NewsSource;
use App\Services\Scrapers\AdaDeranaScraper;
use Database\Seeders\NewsSourceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('NewsSourceSeeder creates the Ada Derana news source', function () {
    $this->seed(NewsSourceSeeder::class);

    $source = NewsSource::where('slug', 'ada-derana')->first();

    expect($source)->not->toBeNull()
        ->and($source->name)->toBe('Ada Derana')
        ->and($source->base_url)->toBe('https://adaderana.lk')
        ->and($source->archive_url)->toBe('https://adaderana.lk/news-sitemap.xml')
        ->and($source->language)->toBe(Language::En)
        ->and($source->scraper_class)->toBe(AdaDeranaScraper::class)
        ->and($source->is_active)->toBeTrue();
});

test('NewsSourceSeeder is idempotent', function () {
    $this->seed(NewsSourceSeeder::class);
    $this->seed(NewsSourceSeeder::class);

    expect(NewsSource::where('slug', 'ada-derana')->count())->toBe(1);
});
