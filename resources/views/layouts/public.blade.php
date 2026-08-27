<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head', ['title' => $title ?? null])
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-900">
        <flux:header container class="border-b border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
            <a href="{{ route('news.index') }}" wire:navigate class="flex items-center gap-2.5">
                <span class="flex size-8 items-center justify-center rounded-md bg-zinc-900 text-white dark:bg-white dark:text-zinc-900">
                    <x-app-logo-icon class="size-5" />
                </span>
                <span class="grid leading-none">
                    <span class="text-lg font-bold text-zinc-900 dark:text-white leading-none">{{ config('app.name', 'NewsAI') }}</span>
                    <span class="mt-0.5 hidden text-xs text-zinc-500 dark:text-zinc-400 leading-none sm:block">One Story. Three Languages.</span>
                </span>
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

            @auth
                <flux:button variant="ghost" :href="route('dashboard')" wire:navigate>Dashboard</flux:button>
            @else
                <flux:button variant="ghost" :href="route('login')" data-test="header-sign-in">Sign in</flux:button>
            @endauth
        </flux:header>

        <main>
            {{ $slot }}
        </main>

        <footer class="border-t border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
            <div class="mx-auto flex max-w-3xl flex-col items-center justify-between gap-4 px-4 py-6 text-sm sm:flex-row">
                <div class="flex items-center gap-2 text-zinc-500 dark:text-zinc-400">
                    <x-app-logo-icon class="size-4" />
                    <span class="font-semibold text-zinc-900 dark:text-white">{{ config('app.name', 'NewsAI') }}</span>
                    <span aria-hidden="true">—</span>
                    <span>One Story. Three Languages.</span>
                </div>
                <div class="flex items-center gap-4 text-zinc-500 dark:text-zinc-400">
                    <a href="{{ route('news.index', ['lang' => 'en']) }}" wire:navigate>English</a>
                    <a href="{{ route('news.index', ['lang' => 'ta']) }}" wire:navigate>தமிழ்</a>
                    <a href="{{ route('news.index', ['lang' => 'si']) }}" wire:navigate>සිංහල</a>
                </div>
            </div>
            <div class="mx-auto max-w-3xl px-4 pb-4 text-center text-xs text-zinc-400 dark:text-zinc-500">
                © {{ date('Y') }} {{ config('app.name', 'NewsAI') }}
            </div>
        </footer>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
