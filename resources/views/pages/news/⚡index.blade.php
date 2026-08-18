<?php

use App\Contracts\ArticleRepositoryInterface;
use App\Enums\Language;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Sri Lanka News')] #[Layout('layouts.public')] class extends Component
{
    use WithPagination;

    /**
     * The active language filter, bound to the ?lang= URL query param.
     * Null means "all languages".
     */
    #[Url(as: 'lang', keep: true)]
    public ?string $lang = null;

    /**
     * The active source filter, bound to the ?source= URL query param.
     * Null means "all sources".
     */
    #[Url(as: 'source', keep: true)]
    public ?string $source = null;

    /**
     * Reset pagination when the language filter changes.
     */
    public function updatedLang(): void
    {
        $this->resetPage();
    }

    /**
     * Reset pagination when the source filter changes.
     */
    public function updatedSource(): void
    {
        $this->resetPage();
    }

    /**
     * Resolve the Language enum from the URL param, returning null for "all".
     */
    protected function resolvedLanguage(): ?Language
    {
        return match ($this->lang) {
            'en' => Language::En,
            'ta' => Language::Ta,
            'si' => Language::Si,
            default => null,
        };
    }

    public function render(ArticleRepositoryInterface $articleRepository): \Illuminate\Contracts\View\View
    {
        $articles = $articleRepository->getPaginatedByLanguage(
            $this->resolvedLanguage(),
            perPage: 15,
            sourceSlug: $this->source,
        );

        return $this->view([
            'articles' => $articles,
            'sources' => $articleRepository->getSourcesWithArticles(),
        ]);
    }
};
?>

<div class="max-w-3xl mx-auto px-4 py-6">

    {{-- Page heading --}}
    <div class="mb-6">
        <flux:heading size="xl" class="font-bold">
            Sri Lanka News &amp; Latest Breaking Headlines
        </flux:heading>
        <flux:subheading class="mt-1 text-zinc-500 dark:text-zinc-400">
            Updated continuously in English, Tamil and Sinhala
        </flux:subheading>
    </div>

    {{-- Language tabs --}}
    <div class="flex gap-2 mb-6 flex-wrap" role="tablist" aria-label="Filter by language">
        <a
            href="{{ route('news.index') }}"
            wire:navigate
            role="tab"
            aria-selected="{{ $lang === null ? 'true' : 'false' }}"
            @class([
                'px-4 py-1.5 rounded-full text-sm font-medium transition',
                'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900' => $lang === null,
                'bg-zinc-100 text-zinc-600 hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700' => $lang !== null,
            ])
        >
            All
        </a>
        @foreach (['en' => 'English', 'ta' => 'தமிழ்', 'si' => 'සිංහල'] as $code => $label)
            <a
                href="{{ route('news.index', ['lang' => $code]) }}"
                wire:navigate
                role="tab"
                aria-selected="{{ $lang === $code ? 'true' : 'false' }}"
                @class([
                    'px-4 py-1.5 rounded-full text-sm font-medium transition',
                    'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900' => $lang === $code,
                    'bg-zinc-100 text-zinc-600 hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700' => $lang !== $code,
                ])
            >
                {{ $label }}
            </a>
        @endforeach
    </div>

    {{-- Source chips --}}
    @if ($sources->isNotEmpty())
        <div class="flex gap-2 mb-6 flex-wrap" role="tablist" aria-label="Filter by source">
            <a
                href="{{ route('news.index', ['lang' => $lang]) }}"
                wire:navigate
                role="tab"
                aria-selected="{{ $source === null ? 'true' : 'false' }}"
                @class([
                    'px-4 py-1.5 rounded-full text-sm font-medium transition',
                    'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900' => $source === null,
                    'bg-zinc-100 text-zinc-600 hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700' => $source !== null,
                ])
            >
                All sources
            </a>
            @foreach ($sources as $item)
                <a
                    href="{{ route('news.index', ['lang' => $lang, 'source' => $item->slug]) }}"
                    wire:navigate
                    role="tab"
                    aria-selected="{{ $source === $item->slug ? 'true' : 'false' }}"
                    @class([
                        'px-4 py-1.5 rounded-full text-sm font-medium transition',
                        'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900' => $source === $item->slug,
                        'bg-zinc-100 text-zinc-600 hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700' => $source !== $item->slug,
                    ])
                >
                    {{ $item->name }}
                </a>
            @endforeach
        </div>
    @endif

    {{-- Article list --}}
    @if ($articles->isEmpty())
        <div class="py-16 text-center">
            <flux:text class="text-zinc-400 dark:text-zinc-500">
                No articles found. Check back soon!
            </flux:text>
        </div>
    @else
        <div class="divide-y divide-zinc-100 dark:divide-zinc-800">
            @foreach ($articles as $article)
                <x-news-card :article="$article" wire:key="article-{{ $article->id }}" />
            @endforeach
        </div>

        {{-- Pagination --}}
        <div class="mt-6">
            {{ $articles->links() }}
        </div>
    @endif

</div>
