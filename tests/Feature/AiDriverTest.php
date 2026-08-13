<?php

use App\Agents\ArticleRewriterAgent;
use App\Contracts\ArticleAiDriverInterface;
use App\Drivers\Ai\LaravelAiDriver;
use App\Exceptions\AiProcessingException;
use App\Models\Article;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Ai;

uses(RefreshDatabase::class);

// ── Container binding ────────────────────────────────────────────────────────

test('ArticleAiDriverInterface resolves to LaravelAiDriver', function () {
    expect(app(ArticleAiDriverInterface::class))->toBeInstanceOf(LaravelAiDriver::class);
});

// ── LaravelAiDriver::rewrite with fake AI ───────────────────────────────────

test('LaravelAiDriver::rewrite returns correct array shape from AI response', function () {
    Ai::fakeAgent(ArticleRewriterAgent::class, [
        json_encode([
            'title' => 'Rewritten Title',
            'body' => 'Rewritten body paragraph 1.\n\nParagraph 2.',
            'summary' => 'This is a short summary.',
        ]),
    ]);

    $article = Article::factory()->english()->create();
    $driver = app(ArticleAiDriverInterface::class);

    $result = $driver->rewrite($article);

    expect($result)->toHaveKeys(['ai_title', 'ai_body', 'ai_summary'])
        ->and($result['ai_title'])->toBe('Rewritten Title')
        ->and($result['ai_summary'])->toBe('This is a short summary.');
});

test('LaravelAiDriver::rewrite throws AiProcessingException on invalid JSON', function () {
    Ai::fakeAgent(ArticleRewriterAgent::class, [
        'This is not JSON at all.',
    ]);

    $article = Article::factory()->english()->create();
    $driver = app(ArticleAiDriverInterface::class);

    expect(fn () => $driver->rewrite($article))
        ->toThrow(AiProcessingException::class);
});

test('LaravelAiDriver::rewrite throws AiProcessingException when required key is missing', function () {
    Ai::fakeAgent(ArticleRewriterAgent::class, [
        json_encode([
            'title' => 'Only title, no body or summary',
        ]),
    ]);

    $article = Article::factory()->english()->create();
    $driver = app(ArticleAiDriverInterface::class);

    expect(fn () => $driver->rewrite($article))
        ->toThrow(AiProcessingException::class);
});

test('LaravelAiDriver::rewrite strips markdown code fences from response', function () {
    $jsonPayload = json_encode([
        'title' => 'Clean Title',
        'body' => 'Clean body.',
        'summary' => 'Clean summary.',
    ]);

    Ai::fakeAgent(ArticleRewriterAgent::class, [
        "```json\n{$jsonPayload}\n```",
    ]);

    $article = Article::factory()->english()->create();
    $driver = app(ArticleAiDriverInterface::class);

    $result = $driver->rewrite($article);

    expect($result['ai_title'])->toBe('Clean Title');
});

// ── ArticleRewriterAgent ─────────────────────────────────────────────────────

test('ArticleRewriterAgent instructions mention the article language', function () {
    $article = Article::factory()->english()->create();
    $agent = new ArticleRewriterAgent($article);

    expect($agent->instructions())->toContain('English');
});

test('ArticleRewriterAgent instructions are language-aware for Tamil', function () {
    $article = Article::factory()->tamil()->create();
    $agent = new ArticleRewriterAgent($article);

    expect($agent->instructions())->toContain('Tamil');
});

test('ArticleRewriterAgent buildPrompt contains the original title and body', function () {
    $article = Article::factory()->create([
        'original_title' => 'My Test Title',
        'original_body' => 'My test body content.',
    ]);
    $agent = new ArticleRewriterAgent($article);

    expect($agent->buildPrompt())
        ->toContain('My Test Title')
        ->toContain('My test body content.');
});

// ── AiProcessingException ────────────────────────────────────────────────────

test('AiProcessingException::forParseFailure includes truncated raw response', function () {
    $exception = AiProcessingException::forParseFailure('bad json response');

    expect($exception->getMessage())->toContain('bad json response');
});

test('AiProcessingException::forMissingKey includes the missing key', function () {
    $exception = AiProcessingException::forMissingKey('body', '{"title":"only"}');

    expect($exception->getMessage())->toContain('body');
});
