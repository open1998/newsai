<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

@php
    $meta = $meta ?? [];
    $ogTitle = $meta['title'] ?? ($title ?? config('app.name'));
    $ogDescription = $meta['description'] ?? 'Sri Lanka news in English, Tamil and Sinhala — one story, three languages.';
    $ogImage = $meta['image'] ?? null;
@endphp

<title>
    {{ filled($title ?? null) ? $title.' - '.config('app.name', 'Laravel') : config('app.name', 'Laravel') }}
</title>

<meta name="description" content="{{ $ogDescription }}" />

<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">

<meta name="theme-color" content="#18181b" />

<meta property="og:type" content="{{ $meta['type'] ?? 'website' }}" />
<meta property="og:title" content="{{ $ogTitle }}" />
<meta property="og:description" content="{{ $ogDescription }}" />
<meta property="og:url" content="{{ url()->current() }}" />
@if ($ogImage)
    <meta property="og:image" content="{{ $ogImage }}" />
@endif

<meta name="twitter:card" content="summary_large_image" />
<meta name="twitter:title" content="{{ $ogTitle }}" />
<meta name="twitter:description" content="{{ $ogDescription }}" />

@fonts

@vite(['resources/css/app.css', 'resources/js/app.js'])
@fluxAppearance
