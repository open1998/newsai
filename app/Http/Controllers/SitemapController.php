<?php

namespace App\Http\Controllers;

use App\Models\Article;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    /**
     * Render a public XML sitemap of site pages and article URLs.
     *
     * Read-only. Premium-aware exclusion (if any) is handled by the premium
     * access layer in Phase 3 Chunk C; for now every published article is a
     * free article and belongs in the sitemap.
     */
    public function __invoke(): Response
    {
        $articles = Article::query()
            ->orderByDesc('updated_at')
            ->limit(10_000)
            ->get(['id', 'updated_at']);

        $lines = ['<?xml version="1.0" encoding="UTF-8"?>'];
        $lines[] = '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

        $siteUrls = [
            route('news.index'),
            route('home'),
        ];

        foreach ($siteUrls as $loc) {
            $lines[] = '  <url>';
            $lines[] = '    <loc>'.e($loc).'</loc>';
            $lines[] = '    <lastmod>'.now()->toAtomString().'</lastmod>';
            $lines[] = '  </url>';
        }

        foreach ($articles as $article) {
            $lines[] = '  <url>';
            $lines[] = '    <loc>'.e(route('news.show', $article)).'</loc>';
            $lines[] = '    <lastmod>'.$article->updated_at->toAtomString().'</lastmod>';
            $lines[] = '  </url>';
        }

        $lines[] = '</urlset>';

        return response(implode("\n", $lines), 200, ['Content-Type' => 'application/xml']);
    }
}
