<?php

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

uses(TestCase::class)->in('Feature', 'Unit');
uses(TestCase::class, LazilyRefreshDatabase::class)->in('Browser');

/*
|--------------------------------------------------------------------------
| Browser test helpers — Livewire 4 request settling
|--------------------------------------------------------------------------
|
| Livewire 4 runs wire:model.live syncs in PARALLEL (v3 serialized them). When
| a browser test fills many .live.blur fields instantly, the requests overlap
| and a stale response can morph the form and clobber a field filled just after
| it — a non-deterministic lost-update. Real users never hit this (they fill
| fields seconds apart), but instant fill() does.
|
| These helpers serialize the fills the way a human naturally would: after each
| field, blur it to trigger its sync, then wait until Livewire is idle BEFORE
| touching the next field. The wait is condition-based (it resolves as soon as
| no Livewire request is in flight), not a fixed sleep.
|
*/

/** Install a fetch counter exposing in-flight Livewire requests on window.__lwInflight. Call once after visiting. */
function trackLivewireRequests($page): void
{
    $page->script(<<<'JS'
        if (!window.__lwTracked) {
            window.__lwInflight = 0;
            const orig = window.fetch.bind(window);
            window.fetch = function (input, init) {
                let url = input;
                if (input && typeof input === 'object' && 'url' in input) url = input.url;
                const isLw = typeof url === 'string' && url.indexOf('/livewire') !== -1;
                if (isLw) window.__lwInflight++;
                const p = orig(input, init);
                if (isLw) p.then(() => {}, () => {}).finally(() => { window.__lwInflight = Math.max(0, window.__lwInflight - 1); });
                return p;
            };
            window.__lwTracked = true;
        }
    JS);
}

/** Resolve once no Livewire request is in flight (condition-based; small floor lets a debounced .live request fire first). */
function settleLivewire($page): void
{
    $page->script(<<<'JS'
        (async () => {
            await new Promise((resolve) => {
                const start = Date.now();
                (function check() {
                    const idle = (window.__lwInflight || 0) === 0;
                    if (idle && Date.now() - start >= 250) return resolve();
                    if (Date.now() - start > 8000) return resolve();
                    setTimeout(check, 25);
                })();
            });
        })()
    JS);
}

/** Fill a wire:model.live.blur field, blur it to trigger the sync, then wait for Livewire to settle before returning. */
function fillLive($page, string $selector, string $value)
{
    $page->fill($selector, $value);
    $page->script('document.activeElement && document.activeElement.blur()');
    settleLivewire($page);

    return $page;
}

/** Fill the shared registration/profile personal-info fields, serializing each .live.blur sync. */
function fillRegistrationForm($page, array $data): void
{
    trackLivewireRequests($page);

    foreach (['first_name', 'last_name', 'email', 'password', 'password_confirmation', 'date_of_birth'] as $field) {
        fillLive($page, '[wire\\:model\\.live\\.blur="'.$field.'"]', $data[$field]);
    }

    $page->select('[wire\\:model\\.live\\.blur="gender"]', $data['gender']);
    settleLivewire($page);

    foreach (['address_line1', 'city', 'state', 'zip_code'] as $field) {
        fillLive($page, '[wire\\:model\\.live\\.blur="'.$field.'"]', $data[$field]);
    }
}
