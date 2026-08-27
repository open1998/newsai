<?php

use App\Http\Controllers\SitemapController;
use Illuminate\Support\Facades\Route;

// ── Public news routes ───────────────────────────────────────────────────────

Route::livewire('/', 'pages::news.index')->name('news.index');

// 'home' route: required by auth layouts (card.blade.php) and existing tests
Route::livewire('/home', 'pages::news.index')->name('home');
Route::livewire('/news/{article}', 'pages::news.show')->name('news.show');

Route::get('/sitemap.xml', SitemapController::class);

// ── Authenticated routes ─────────────────────────────────────────────────────

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('dashboard', 'pages::dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
