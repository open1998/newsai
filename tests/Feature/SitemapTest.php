<?php

use App\Models\Article;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('sitemap returns XML with the site root and home page', function () {
    $this->get('/sitemap.xml')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/xml')
        ->assertSee('urlset', false)
        ->assertSee(route('news.index'), false)
        ->assertSee(route('home'), false);
});

test('sitemap lists article URLs', function () {
    $article = Article::factory()->create();

    $this->get('/sitemap.xml')
        ->assertOk()
        ->assertSee(route('news.show', $article), false);
});

test('sitemap has no article URLs when none exist', function () {
    $this->get('/sitemap.xml')
        ->assertOk()
        ->assertSee('urlset', false)
        ->assertSee(route('news.index'), false)
        ->assertDontSee('/news/', false);
});
