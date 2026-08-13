<?php

namespace Database\Seeders;

use App\Enums\Language;
use App\Models\NewsSource;
use App\Services\Scrapers\AdaDeranaScraper;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class NewsSourceSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the configured news sources.
     */
    public function run(): void
    {
        NewsSource::updateOrCreate(
            ['slug' => 'ada-derana'],
            [
                'name' => 'Ada Derana',
                'base_url' => 'https://adaderana.lk',
                'archive_url' => 'https://adaderana.lk/news-sitemap.xml',
                'language' => Language::En,
                'scraper_class' => AdaDeranaScraper::class,
                'is_active' => true,
            ]
        );
    }
}
