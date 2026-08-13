<?php

use App\Contracts\ArticleRepositoryInterface;
use App\Enums\AiStatus;
use App\Models\Article;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.public')] class extends Component
{
    public $article;

    /**
     * Mount resolves the article via the repository (not direct Eloquent binding)
     * so the data-access layer is always respected.
     *
     * The route parameter name is `article` (from /news/{article}), so Livewire
     * passes it as int $article here.
     */
    public function mount(int $article, ArticleRepositoryInterface $articleRepository): void
    {
        $found = $articleRepository->findById($article);

        if ($found === null) {
            abort(404);
        }

        $this->article = $found;
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        return $this->view();
    }
};
?>

<div class="max-w-3xl mx-auto px-4 py-8">

    {{-- Dynamic page title via layout title slot --}}
    <x-slot name="title">{{ $article->displayTitle() }} — {{ config('app.name') }}</x-slot>

    {{-- Back link --}}
    <div class="mb-6">
        <a href="{{ route('news.index') }}" wire:navigate
            class="inline-flex items-center gap-1 text-sm text-zinc-500 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white transition">
            ← Back to News
        </a>
    </div>

    {{-- Source + meta row --}}
    <div class="flex flex-wrap items-center gap-2 mb-4">
        <flux:badge variant="outline" size="sm">
            {{ $article->newsSource->name ?? 'Unknown Source' }}
        </flux:badge>

        <flux:badge size="sm"
            color="{{ match($article->language->value) { 'en' => 'blue', 'ta' => 'green', 'si' => 'yellow', default => 'zinc' } }}">
            {{ $article->language->label() }}
        </flux:badge>

        @if ($article->published_at)
            <span class="text-xs text-zinc-400 dark:text-zinc-500">
                {{ $article->published_at->format('d M Y, g:i a') }}
                <span class="ml-1">({{ $article->published_at->diffForHumans() }})</span>
            </span>
        @endif
    </div>

    {{-- Title --}}
    <h1 class="text-2xl font-bold text-zinc-900 dark:text-white leading-tight mb-4">
        {{ $article->displayTitle() }}
    </h1>

    {{-- AI summary callout --}}
    @if ($article->ai_summary)
        <div class="rounded-lg border border-blue-200 bg-blue-50 dark:border-blue-800 dark:bg-blue-950/40 px-4 py-3 mb-6">
            <p class="text-xs font-semibold text-blue-600 dark:text-blue-400 uppercase tracking-wide mb-1">
                AI Summary
            </p>
            <p class="text-sm text-blue-900 dark:text-blue-200 leading-relaxed">
                {{ $article->ai_summary }}
            </p>
        </div>
    @endif

    {{-- Hero image --}}
    @if ($article->original_image_url)
        <div class="mb-6 rounded-lg overflow-hidden bg-zinc-100 dark:bg-zinc-800">
            <img
                src="{{ $article->original_image_url }}"
                alt="{{ $article->displayTitle() }}"
                class="w-full object-cover max-h-96"
                loading="lazy"
            />
        </div>
    @endif

    {{-- AI processing pending notice --}}
    @if ($article->ai_status !== \App\Enums\AiStatus::Succeeded)
        <div class="rounded-lg border border-amber-200 bg-amber-50 dark:border-amber-800 dark:bg-amber-950/30 px-4 py-2 mb-4 text-xs text-amber-700 dark:text-amber-400">
            This article is queued for AI rewriting. Showing original content.
        </div>
    @endif

    {{-- Article body --}}
    <x-article-body :body="$article->displayBody()" />

    {{-- Original source link --}}
    <div class="mt-8 pt-6 border-t border-zinc-200 dark:border-zinc-800">
        <a
            href="{{ $article->source_url }}"
            target="_blank"
            rel="noopener noreferrer"
            class="inline-flex items-center gap-2 text-sm font-medium text-blue-600 dark:text-blue-400 hover:underline"
            data-test="original-source-link"
        >
            View original article at {{ $article->newsSource->name ?? 'source' }}
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
            </svg>
        </a>
    </div>

</div>
