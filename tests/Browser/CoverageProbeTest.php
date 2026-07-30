<?php

// Guards the browser-JS coverage pipeline (issue #75): an instrumented build
// (COVERAGE=true npm run build) must expose Istanbul counters, and the
// layout's harvest script must land page snapshots on the /__coverage__
// collector. Skipped outside coverage runs — the plumbing doesn't exist there.
it('harvests browser-JS coverage snapshots', function () {
    $page = visit('/');
    $page->assertScript("typeof window.__coverage__ === 'object' && Object.keys(window.__coverage__).length > 0", true);
    $page->assertScript("document.querySelector('script[data-coverage-harvest]') !== null", true);
    // The harvest script sends immediately and re-sends on idle; give it
    // a beat, then confirm at least one snapshot reached the collector.
    $page->script('new Promise((resolve) => setTimeout(resolve, 500))');
    expect(glob(storage_path('app/coverage/page-*.json')))->not->toBeEmpty();
})->skip(fn (): bool => ! config('app.coverage'), 'Only meaningful when COVERAGE=true (coverage workflow)');
