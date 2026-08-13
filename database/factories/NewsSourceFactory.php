<?php

namespace Database\Factories;

use App\Enums\Language;
use App\Models\NewsSource;
use App\Services\Scrapers\AdaDeranaScraper;
use App\Services\Scrapers\PlaceholderScraper;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NewsSource>
 */
class NewsSourceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->company().' News';

        return [
            'name' => $name,
            'slug' => str($name)->slug()->value(),
            'base_url' => fake()->url(),
            'archive_url' => fake()->url().'/news',
            'language' => fake()->randomElement(Language::cases()),
            'scraper_class' => PlaceholderScraper::class,
            'is_active' => true,
            'last_scraped_at' => null,
        ];
    }

    /**
     * Indicate the source is for English news.
     */
    public function english(): static
    {
        return $this->state(['language' => Language::En]);
    }

    /**
     * Indicate the source is for Tamil news.
     */
    public function tamil(): static
    {
        return $this->state(['language' => Language::Ta]);
    }

    /**
     * Indicate the source is for Sinhala news.
     */
    public function sinhala(): static
    {
        return $this->state(['language' => Language::Si]);
    }

    /**
     * Indicate the source is inactive.
     */
    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    /**
     * Indicate the source is the Ada Derana English news site.
     */
    public function adaDerana(): static
    {
        return $this->state([
            'name' => 'Ada Derana',
            'slug' => 'ada-derana',
            'base_url' => 'https://adaderana.lk',
            'archive_url' => 'https://adaderana.lk/news-sitemap.xml',
            'language' => Language::En,
            'scraper_class' => AdaDeranaScraper::class,
            'is_active' => true,
        ]);
    }
}
