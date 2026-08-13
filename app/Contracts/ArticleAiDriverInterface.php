<?php

namespace App\Contracts;

use App\Exceptions\AiProcessingException;
use App\Models\Article;

/**
 * Contract for AI rewriting drivers.
 *
 * Abstracts the AI provider so the job layer never depends on a specific
 * model or SDK. Swap implementations in AppServiceProvider to change
 * which AI backend is used without touching any business logic.
 */
interface ArticleAiDriverInterface
{
    /**
     * Rewrite the article using AI and return the generated content.
     *
     * Implementations must:
     * - Use the article's language to respond in the same language.
     * - Return all three keys.
     * - Throw AiProcessingException on parse or provider failure.
     *
     * @return array{ai_title: string, ai_body: string, ai_summary: string}
     *
     * @throws AiProcessingException
     */
    public function rewrite(Article $article): array;
}
