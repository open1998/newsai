<?php

namespace App\Drivers\Ai;

use App\Agents\ArticleRewriterAgent;
use App\Contracts\ArticleAiDriverInterface;
use App\Exceptions\AiProcessingException;
use App\Models\Article;

/**
 * AI rewriting driver backed by laravel/ai v0.10.2.
 *
 * Resolves and prompts the ArticleRewriterAgent, parses the JSON response,
 * and returns the three required fields. Throws AiProcessingException on
 * any parse or provider failure so the job retry mechanism handles it.
 */
class LaravelAiDriver implements ArticleAiDriverInterface
{
    /**
     * The required keys that must be present in the AI response.
     *
     * @var array<int, string>
     */
    private const REQUIRED_KEYS = ['title', 'body', 'summary'];

    /**
     * Rewrite an article using the ArticleRewriterAgent.
     *
     * @return array{ai_title: string, ai_body: string, ai_summary: string}
     *
     * @throws AiProcessingException
     */
    public function rewrite(Article $article): array
    {
        $agent = new ArticleRewriterAgent($article);

        $response = $agent->prompt($agent->buildPrompt());
        $rawText = $response->text;

        return $this->parseResponse($rawText);
    }

    /**
     * Parse and validate the JSON response from the AI.
     *
     * @return array{ai_title: string, ai_body: string, ai_summary: string}
     *
     * @throws AiProcessingException
     */
    private function parseResponse(string $rawText): array
    {
        // Strip potential markdown code fences that some models add
        $cleaned = preg_replace('/^```(?:json)?\s*/m', '', $rawText);
        $cleaned = preg_replace('/\s*```$/m', '', $cleaned ?? $rawText);
        $cleaned = trim($cleaned ?? $rawText);

        $decoded = json_decode($cleaned, associative: true);

        if (! is_array($decoded)) {
            throw AiProcessingException::forParseFailure($rawText);
        }

        foreach (self::REQUIRED_KEYS as $key) {
            if (! array_key_exists($key, $decoded) || ! is_string($decoded[$key])) {
                throw AiProcessingException::forMissingKey($key, $rawText);
            }
        }

        return [
            'ai_title' => trim($decoded['title']),
            'ai_body' => trim($decoded['body']),
            'ai_summary' => trim($decoded['summary']),
        ];
    }
}
