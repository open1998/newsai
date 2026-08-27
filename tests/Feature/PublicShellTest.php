<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('public header shows a Sign in link for guests', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('data-test="header-sign-in"', false)
        ->assertSee('Sign in');
});

test('public header shows a Dashboard link for authenticated users', function () {
    $this->actingAs(User::factory()->create());

    $this->get('/')
        ->assertOk()
        ->assertSee('Dashboard');
});

test('public footer contains the brand tagline', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('One Story. Three Languages.');
});

test('robots.txt advertises the sitemap', function () {
    $robots = file_get_contents(public_path('robots.txt'));

    expect($robots)->toContain('Sitemap:')
        ->and($robots)->toContain('/sitemap.xml');
});

test('authenticated app shell no longer shows starter repository or documentation links', function () {
    $this->actingAs(User::factory()->create());

    $this->get('/dashboard')
        ->assertOk()
        ->assertDontSee('Repository')
        ->assertDontSee('Documentation');
});

test('public feed shows the NewsAI brand', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('NewsAI');
});
