<?php

namespace App\Models;

use App\Enums\AiStatus;
use App\Enums\Language;
use App\Enums\ScrapeStatus;
use Database\Factories\ArticleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $news_source_id
 * @property string $source_url
 * @property Language $language
 * @property string|null $content_hash
 * @property ScrapeStatus $scrape_status
 * @property string|null $last_scrape_error
 * @property string $original_title
 * @property string $original_body
 * @property string|null $original_image_url
 * @property AiStatus $ai_status
 * @property string|null $ai_title
 * @property string|null $ai_body
 * @property string|null $ai_summary
 * @property Carbon|null $published_at
 * @property Carbon|null $scraped_at
 * @property Carbon|null $ai_processed_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read NewsSource $newsSource
 */
#[Fillable([
    'news_source_id',
    'source_url',
    'language',
    'content_hash',
    'scrape_status',
    'last_scrape_error',
    'original_title',
    'original_body',
    'original_image_url',
    'ai_status',
    'ai_title',
    'ai_body',
    'ai_summary',
    'published_at',
    'scraped_at',
    'ai_processed_at',
])]
class Article extends Model
{
    /** @use HasFactory<ArticleFactory> */
    use HasFactory;

    /**
     * Get the attribute casts for the model.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'language' => Language::class,
            'scrape_status' => ScrapeStatus::class,
            'ai_status' => AiStatus::class,
            'published_at' => 'datetime',
            'scraped_at' => 'datetime',
            'ai_processed_at' => 'datetime',
        ];
    }

    /**
     * Get the news source this article belongs to.
     *
     * @return BelongsTo<NewsSource, $this>
     */
    public function newsSource(): BelongsTo
    {
        return $this->belongsTo(NewsSource::class);
    }

    /**
     * Scope to articles that have been successfully AI processed.
     *
     * @param  Builder<Article>  $query
     * @return Builder<Article>
     */
    public function scopeAiProcessed(Builder $query): Builder
    {
        return $query->where('ai_status', AiStatus::Succeeded);
    }

    /**
     * Scope to articles by language.
     *
     * @param  Builder<Article>  $query
     * @return Builder<Article>
     */
    public function scopeForLanguage(Builder $query, Language $language): Builder
    {
        return $query->where('language', $language->value);
    }

    /**
     * Get the display title — AI title when available, fallback to original.
     */
    public function displayTitle(): string
    {
        return $this->ai_title ?? $this->original_title;
    }

    /**
     * Get the display body — AI body when available, fallback to original.
     */
    public function displayBody(): string
    {
        return $this->ai_body ?? $this->original_body;
    }
}
