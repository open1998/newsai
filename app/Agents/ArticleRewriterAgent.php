<?php

namespace App\Agents;

use App\Models\Article;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;

/**
 * AI agent that rewrites news articles using the DeepSeek model.
 *
 * The agent receives original article content and returns a JSON object
 * with a rewritten title, rewritten body, and a concise summary —
 * all in the same language as the original article.
 */
#[Provider('deepseek-custom')]
#[Model('deepseek-chat')]
class ArticleRewriterAgent implements Agent
{
    use Promptable;

    public function __construct(
        private readonly Article $article,
    ) {}

    /**
     * System instructions telling the AI how to process articles.
     *
     * Language-aware: the response must match the article's original language
     * (English, Sinhala, or Tamil) so multilingual content is preserved.
     */
    public function instructions(): string
    {
        $languageLabel = $this->article->language->label();

        return <<<INSTRUCTIONS
        You are a professional news editor. You will receive the original title and body of a news article.
        Your task is to:
        1. Rewrite the title to be clearer, more engaging, and factually accurate.
        2. Rewrite the body to improve readability, grammar, and flow while preserving all facts.
        3. Write a concise 2-3 sentence summary of the article.

        CRITICAL RULES:
        - You MUST respond in {$languageLabel} — the same language as the original article.
        - Do NOT add any information not present in the original.
        - Do NOT include any markdown formatting in the body or title.
        - Respond with ONLY a valid JSON object. No preamble, no explanation.

        Required JSON format:
        {
            "title": "rewritten title here",
            "body": "rewritten body here with paragraphs separated by \\n\\n",
            "summary": "2-3 sentence summary here"
        }
        INSTRUCTIONS;
    }

    /**
     * Build the user prompt containing the article content to process.
     */
    public function buildPrompt(): string
    {
        return "Original Title:\n{$this->article->original_title}\n\nOriginal Body:\n{$this->article->original_body}";
    }
}
