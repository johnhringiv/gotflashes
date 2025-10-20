<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SitemapTest extends TestCase
{
    use RefreshDatabase;

    public function test_sitemap_is_accessible(): void
    {
        $response = $this->get('/sitemap.xml');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/xml');
    }

    public function test_sitemap_contains_valid_xml(): void
    {
        $response = $this->get('/sitemap.xml');

        $response->assertStatus(200);

        // Check for XML declaration and urlset
        $content = $response->getContent();
        $this->assertStringContainsString('<?xml version="1.0" encoding="UTF-8"?>', $content);
        $this->assertStringContainsString('<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">', $content);
        $this->assertStringContainsString('</urlset>', $content);
    }

    public function test_sitemap_includes_home_page(): void
    {
        $response = $this->get('/sitemap.xml');

        $response->assertStatus(200);

        $baseUrl = config('app.url');
        $content = $response->getContent();

        $this->assertStringContainsString("<loc>{$baseUrl}/</loc>", $content);
        $this->assertStringContainsString('<priority>1.0</priority>', $content);
        $this->assertStringContainsString('<changefreq>monthly</changefreq>', $content);
    }

    public function test_sitemap_includes_all_public_routes(): void
    {
        $response = $this->get('/sitemap.xml');

        $response->assertStatus(200);

        $baseUrl = config('app.url');
        $content = $response->getContent();

        // Check all expected routes
        $this->assertStringContainsString("<loc>{$baseUrl}/</loc>", $content);
        $this->assertStringContainsString("<loc>{$baseUrl}/register</loc>", $content);
        $this->assertStringContainsString("<loc>{$baseUrl}/login</loc>", $content);
        $this->assertStringContainsString("<loc>{$baseUrl}/leaderboard</loc>", $content);
    }

    public function test_sitemap_uses_configured_app_url(): void
    {
        // Temporarily change the app URL
        config(['app.url' => 'https://example.com']);

        $response = $this->get('/sitemap.xml');

        $response->assertStatus(200);

        $content = $response->getContent();
        $this->assertStringContainsString('<loc>https://example.com/', $content);
        $this->assertStringNotContainsString('<loc>https://gotflashes.com/', $content);
    }

    public function test_sitemap_has_higher_priority_for_leaderboard(): void
    {
        $response = $this->get('/sitemap.xml');

        $response->assertStatus(200);

        $content = $response->getContent();
        $baseUrl = config('app.url');

        // Leaderboard should have priority 0.9 and daily changefreq
        $this->assertMatchesRegularExpression(
            '/<url>.*<loc>'.preg_quote($baseUrl, '/').'\/leaderboard<\/loc>.*<changefreq>daily<\/changefreq>.*<priority>0\.9<\/priority>.*<\/url>/s',
            $content
        );
    }
}
