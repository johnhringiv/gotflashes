<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $baseUrl = 'https://gotflashes.com';

        $urls = [
            [
                'loc' => $baseUrl.'/',
                'changefreq' => 'monthly',
                'priority' => '1.0',
            ],
            [
                'loc' => $baseUrl.'/register',
                'changefreq' => 'monthly',
                'priority' => '0.8',
            ],
            [
                'loc' => $baseUrl.'/login',
                'changefreq' => 'monthly',
                'priority' => '0.8',
            ],
            [
                'loc' => $baseUrl.'/leaderboard',
                'changefreq' => 'daily',
                'priority' => '0.9',
            ],
        ];

        $sitemap = view('sitemap', ['urls' => $urls])->render();

        return response($sitemap, 200)
            ->header('Content-Type', 'application/xml');
    }
}
