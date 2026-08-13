@props(['body'])

{{--
    Renders article body text safely.
    Paragraphs are split on double newlines and output as <p> tags.
    No HTML from the body is ever trusted — only plain text is rendered.
--}}
<div class="prose prose-zinc dark:prose-invert max-w-none text-sm leading-relaxed">
    @foreach (array_filter(explode("\n\n", $body)) as $paragraph)
        <p>{{ trim($paragraph) }}</p>
    @endforeach
</div>
