<?php

use App\Contracts\ArticleRepositoryInterface;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Dashboard')] class extends Component
{
    public function render(ArticleRepositoryInterface $articleRepository): \Illuminate\Contracts\View\View
    {
        return $this->view(['sources' => $articleRepository->getSourceStats()]);
    }
};
?>

<section class="w-full">
    {{-- Page heading --}}
    <div class="mb-6">
        <flux:heading size="xl" class="font-bold">Dashboard</flux:heading>
        <flux:subheading class="mt-1 text-zinc-500 dark:text-zinc-400">
            Scraping and AI processing overview across all news sources
        </flux:subheading>
    </div>

    {{-- Global totals --}}
    <div class="grid gap-4 sm:grid-cols-2 md:grid-cols-4 mb-6">
        <div class="rounded-xl border border-neutral-200 dark:border-neutral-700 p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-zinc-400 dark:text-zinc-500">Total articles</p>
            <p class="mt-1 text-2xl font-bold text-zinc-900 dark:text-white">{{ $sources->sum('articles_count') }}</p>
        </div>
        <div class="rounded-xl border border-neutral-200 dark:border-neutral-700 p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-zinc-400 dark:text-zinc-500">AI succeeded</p>
            <p class="mt-1 text-2xl font-bold text-blue-600 dark:text-blue-400">{{ $sources->sum('ai_succeeded_count') }}</p>
        </div>
        <div class="rounded-xl border border-neutral-200 dark:border-neutral-700 p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-zinc-400 dark:text-zinc-500">AI pending</p>
            <p class="mt-1 text-2xl font-bold text-amber-600 dark:text-amber-400">{{ $sources->sum('ai_pending_count') }}</p>
        </div>
        <div class="rounded-xl border border-neutral-200 dark:border-neutral-700 p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-zinc-400 dark:text-zinc-500">AI failed</p>
            <p class="mt-1 text-2xl font-bold text-red-600 dark:text-red-400">{{ $sources->sum('ai_failed_count') }}</p>
        </div>
    </div>

    {{-- Per-source table --}}
    <div class="rounded-xl border border-neutral-200 dark:border-neutral-700 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-zinc-50 dark:bg-zinc-800/60">
                <tr class="text-left text-xs uppercase tracking-wide text-zinc-400 dark:text-zinc-500">
                    <th class="px-4 py-3 font-medium">Source</th>
                    <th class="px-4 py-3 font-medium">Language</th>
                    <th class="px-4 py-3 font-medium">Status</th>
                    <th class="px-4 py-3 font-medium">Last scraped</th>
                    <th class="px-4 py-3 font-medium text-right">Articles</th>
                    <th class="px-4 py-3 font-medium text-right">AI ✓</th>
                    <th class="px-4 py-3 font-medium text-right">AI pending</th>
                    <th class="px-4 py-3 font-medium text-right">AI failed</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                @forelse ($sources as $source)
                    <tr>
                        <td class="px-4 py-3">
                            <p class="font-medium text-zinc-900 dark:text-zinc-100">{{ $source->name }}</p>
                            <p class="text-xs text-zinc-400 dark:text-zinc-500">{{ $source->slug }}</p>
                        </td>
                        <td class="px-4 py-3">
                            <flux:badge size="sm" color="{{ match($source->language->value) { 'en' => 'blue', 'ta' => 'green', 'si' => 'yellow', default => 'zinc' } }}">
                                {{ $source->language->label() }}
                            </flux:badge>
                        </td>
                        <td class="px-4 py-3">
                            @if ($source->is_active)
                                <flux:badge size="sm" color="green">Active</flux:badge>
                            @else
                                <flux:badge size="sm" color="zinc">Inactive</flux:badge>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-zinc-500 dark:text-zinc-400">
                            {{ $source->last_scraped_at?->format('d M Y, g:i a') ?? '—' }}
                        </td>
                        <td class="px-4 py-3 text-right">{{ $source->articles_count }}</td>
                        <td class="px-4 py-3 text-right text-blue-600 dark:text-blue-400">{{ $source->ai_succeeded_count }}</td>
                        <td class="px-4 py-3 text-right text-amber-600 dark:text-amber-400">{{ $source->ai_pending_count }}</td>
                        <td class="px-4 py-3 text-right text-red-600 dark:text-red-400">{{ $source->ai_failed_count }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-12 text-center text-zinc-400 dark:text-zinc-500">
                            No news sources yet.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
