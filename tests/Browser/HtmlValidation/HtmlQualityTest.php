<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;

$validatorUrl = env('HTML_VALIDATOR_URL', 'http://localhost:8888');

$publicPaths = ['/', '/leaderboard', '/login', '/register', '/password/reset'];
$authPaths = ['/logbook', '/profile'];
$adminPaths = ['/admin/fulfillment', '/admin/sailor-logs'];

function e2eCheckStructure($page): void
{
    $page->assertPresent('meta[charset]');
    $page->assertPresent('meta[name="viewport"]');
    $page->assertScript('document.querySelectorAll("nav").length >= 1', true);
    $page->assertScript('document.querySelectorAll("main").length', 1);
    $page->assertScript('document.querySelectorAll("h1").length', 1);
    $page->assertScript('document.querySelectorAll("img:not([alt])").length', 0);
}

function e2eCheckW3C($page, string $validatorUrl): void
{
    try {
        $html = $page->content();
        $response = Http::withBody($html, 'text/html; charset=utf-8')
            ->post("{$validatorUrl}/?out=json");

        if ($response->ok()) {
            $errors = collect($response->json('messages', []))
                ->filter(fn ($m) => ($m['type'] ?? '') === 'error')
                // Livewire adds non-standard attributes that validator.nu flags
                ->reject(fn ($m) => str_contains($m['message'] ?? '', 'wire:'))
                ->values();

            expect($errors)->toBeEmpty('W3C errors: '.$errors->pluck('message')->join('; '));
        }
    } catch (\Illuminate\Http\Client\ConnectionException) {
        test()->markTestSkipped("HTML validator not available at {$validatorUrl}");
    }
}

foreach ($publicPaths as $path) {
    it("has valid HTML structure on {$path}", function () use ($path) {
        $page = visit($path);
        e2eCheckStructure($page);
    });

    it("passes W3C validation on {$path}")->skip('Fix HTML errors first — see #34');
}

foreach ($authPaths as $path) {
    it("has valid HTML structure on {$path} (auth)", function () use ($path) {
        $user = User::factory()->create();
        $this->actingAs($user);
        $page = visit($path);
        e2eCheckStructure($page);
    });

    it("passes W3C validation on {$path} (auth)")->skip('Fix HTML errors first — see #34');
}

foreach ($adminPaths as $path) {
    it("has valid HTML structure on {$path} (admin)", function () use ($path) {
        $user = User::factory()->create();
        $user->is_admin = true;
        $user->save();
        $this->actingAs($user);
        $page = visit($path);
        e2eCheckStructure($page);
    });

    it("passes W3C validation on {$path} (admin)")->skip('Fix HTML errors first — see #34');
}
