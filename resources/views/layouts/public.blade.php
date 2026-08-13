<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head', ['title' => $title ?? null])
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-900">
        <flux:header container class="border-b border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
            <a href="{{ route('news.index') }}" wire:navigate class="flex items-center gap-2 font-bold text-zinc-900 dark:text-white text-lg">
                🇱🇰 <span>{{ config('app.name', 'SL News') }}</span>
            </a>

            <flux:spacer />

            <flux:navbar class="py-0!">
                <flux:navbar.item
                    :href="route('news.index')"
                    :current="request()->routeIs('news.index') && !request()->query('lang')"
                    wire:navigate
                >
                    All
                </flux:navbar.item>
                <flux:navbar.item
                    :href="route('news.index', ['lang' => 'en'])"
                    :current="request()->query('lang') === 'en'"
                    wire:navigate
                >
                    English
                </flux:navbar.item>
                <flux:navbar.item
                    :href="route('news.index', ['lang' => 'ta'])"
                    :current="request()->query('lang') === 'ta'"
                    wire:navigate
                >
                    தமிழ்
                </flux:navbar.item>
                <flux:navbar.item
                    :href="route('news.index', ['lang' => 'si'])"
                    :current="request()->query('lang') === 'si'"
                    wire:navigate
                >
                    සිංහල
                </flux:navbar.item>
            </flux:navbar>
        </flux:header>

        <main>
            {{ $slot }}
        </main>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
