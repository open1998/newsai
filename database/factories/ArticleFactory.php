<?php

namespace Database\Factories;

use App\Enums\AiStatus;
use App\Enums\Language;
use App\Enums\ScrapeStatus;
use App\Models\Article;
use App\Models\NewsSource;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Article>
 */
class ArticleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $originalTitle = fake()->sentence();
        $originalBody = implode("\n\n", fake()->paragraphs(4));

        return [
            'news_source_id' => NewsSource::factory(),
            'source_url' => fake()->unique()->url(),
            'language' => fake()->randomElement(Language::cases()),
            'content_hash' => hash('sha256', $originalTitle.$originalBody),
            'scrape_status' => ScrapeStatus::Succeeded,
            'last_scrape_error' => null,
            'original_title' => $originalTitle,
            'original_body' => $originalBody,
            'original_image_url' => fake()->optional()->imageUrl(),
            'ai_status' => AiStatus::Pending,
            'ai_title' => null,
            'ai_body' => null,
            'ai_summary' => null,
            'published_at' => fake()->dateTimeBetween('-7 days'),
            'scraped_at' => now(),
            'ai_processed_at' => null,
        ];
    }

    /**
     * Indicate the article has been successfully AI processed.
     */
    public function aiProcessed(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'ai_status' => AiStatus::Succeeded,
                'ai_title' => 'AI: '.fake()->sentence(),
                'ai_body' => implode("\n\n", fake()->paragraphs(4)),
                'ai_summary' => fake()->paragraph(),
                'ai_processed_at' => now(),
            ];
        });
    }

    /**
     * Indicate the article's AI processing has failed.
     */
    public function aiFailed(): static
    {
        return $this->state([
            'ai_status' => AiStatus::Failed,
        ]);
    }

    /**
     * Indicate the article's scraping has failed.
     */
    public function scrapeFailed(): static
    {
        return $this->state([
            'scrape_status' => ScrapeStatus::Failed,
            'last_scrape_error' => fake()->sentence(),
        ]);
    }

    /**
     * Indicate the article is in English.
     */
    public function english(): static
    {
        return $this->state(['language' => Language::En]);
    }

    /**
     * Indicate the article is in Tamil.
     */
    public function tamil(): static
    {
        return $this->state(['language' => Language::Ta]);
    }

    /**
     * Indicate the article is in Sinhala.
     */
    public function sinhala(): static
    {
        return $this->state(['language' => Language::Si]);
    }
}
