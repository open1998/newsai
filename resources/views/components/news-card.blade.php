@props(['article'])

<a
    href="{{ route('news.show', $article) }}"
    wire:navigate
    class="group flex gap-4 rounded-lg p-3 transition hover:bg-zinc-50 dark:hover:bg-zinc-800/60 no-underline"
>
    {{-- Thumbnail --}}
    <div class="shrink-0 w-24 h-16 rounded overflow-hidden bg-zinc-100 dark:bg-zinc-800">
        @if ($article->original_image_url)
            <img
                src="{{ $article->original_image_url }}"
                alt="{{ $article->displayTitle() }}"
                class="w-full h-full object-cover"
                loading="lazy"
            />
        @else
            <div class="w-full h-full flex items-center justify-center text-zinc-400 dark:text-zinc-600">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
            </div>
        @endif
    </div>

    {{-- Content --}}
    <div class="flex-1 min-w-0">
        <div class="flex items-center gap-2 mb-1 flex-wrap">
            <flux:badge size="sm" variant="outline" class="shrink-0">
                {{ $article->newsSource->name ?? 'Unknown' }}
            </flux:badge>
            <flux:badge size="sm" color="{{ match($article->language->value) { 'en' => 'blue', 'ta' => 'green', 'si' => 'yellow', default => 'zinc' } }}">
                {{ $article->language->label() }}
            </flux:badge>
        </div>

        <h3 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100 line-clamp-2 group-hover:text-blue-600 dark:group-hover:text-blue-400 leading-snug">
            {{ $article->displayTitle() }}
        </h3>

        @if ($article->ai_summary)
            <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400 line-clamp-2 leading-relaxed">
                {{ $article->ai_summary }}
            </p>
        @endif

        <p class="mt-1.5 text-xs text-zinc-400 dark:text-zinc-500">
            @if ($article->published_at)
                {{ $article->published_at->diffForHumans() }}
            @else
                {{ $article->created_at->diffForHumans() }}
            @endif
        </p>
    </div>
</a>
