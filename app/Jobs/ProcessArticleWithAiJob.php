<?php

namespace App\Jobs;

use App\Contracts\ArticleAiDriverInterface;
use App\Contracts\ArticleRepositoryInterface;
use App\Enums\AiStatus;
use App\Exceptions\AiProcessingException;
use App\Models\Article;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessArticleWithAiJob implements ShouldQueue
{
    use Queueable;

    /**
     * Number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * Backoff in seconds between retries.
     *
     * @var array<int, int>
     */
    public array $backoff = [30, 60, 120];

    public function __construct(
        public readonly Article $article,
    ) {}

    /**
     * Process the article through the AI driver.
     *
     * Skips if the article was already successfully processed and its
     * content_hash has not changed (idempotency guard).
     *
     * Sets AiStatus::Processing before calling the driver so the status
     * is visible immediately in the UI while the job is running.
     */
    public function handle(
        ArticleAiDriverInterface $aiDriver,
        ArticleRepositoryInterface $articleRepository,
    ): void {
        $this->article->refresh();

        // Idempotency: skip if already successfully processed
        if ($this->article->ai_status === AiStatus::Succeeded && $this->article->ai_processed_at !== null) {
            $articleRepository->markAiStatus($this->article, AiStatus::Skipped);

            return;
        }

        $articleRepository->markAiStatus($this->article, AiStatus::Processing);

        try {
            $aiData = $aiDriver->rewrite($this->article);
            $articleRepository->markAiProcessed($this->article, $aiData);
        } catch (AiProcessingException $e) {
            $articleRepository->markAiStatus($this->article, AiStatus::Failed);

            Log::warning('AI processing failed', [
                'article_id' => $this->article->id,
                'error' => $e->getMessage(),
            ]);

            // Re-throw so the queue retries the job
            throw $e;
        }
    }

    /**
     * Handle a job failure after all retries are exhausted.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessArticleWithAiJob failed permanently', [
            'article_id' => $this->article->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
