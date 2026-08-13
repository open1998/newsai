<?php

namespace App\Models;

use App\Enums\Language;
use Database\Factories\NewsSourceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string $base_url
 * @property string $archive_url
 * @property Language $language
 * @property string $scraper_class
 * @property bool $is_active
 * @property Carbon|null $last_scraped_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable(['name', 'slug', 'base_url', 'archive_url', 'language', 'scraper_class', 'is_active', 'last_scraped_at'])]
class NewsSource extends Model
{
    /** @use HasFactory<NewsSourceFactory> */
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
            'is_active' => 'boolean',
            'last_scraped_at' => 'datetime',
        ];
    }

    /**
     * Get all articles for this source.
     *
     * @return HasMany<Article, $this>
     */
    public function articles(): HasMany
    {
        return $this->hasMany(Article::class);
    }
}
