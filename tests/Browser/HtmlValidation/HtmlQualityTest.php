<?php

use App\Models\Flash;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

$validatorUrl = env('HTML_VALIDATOR_URL', 'http://localhost:8888');

$publicPaths = ['/', '/leaderboard', '/login', '/register', '/password/reset'];
$authPaths = ['/logbook', '/profile'];
$adminPaths = ['/admin/fulfillment', '/admin/sailor-logs'];

// Guarded against redeclaration: Pest loads test files into a shared scope, so
// a same-named helper in another file would otherwise fatal.
if (! function_exists('e2eCheckStructure')) {
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
                    // Laravel's @csrf helper emits <input type="hidden" name="_token"
                    // autocomplete="off">. The WHATWG spec disallows autocomplete on/off on
                    // hidden inputs, so validator.nu flags it, but the attribute is a
                    // deliberate framework default (it stops browsers restoring a stale token
                    // from bfcache on back-navigation, which would cause a 419). We don't
                    // control this markup and don't want to lose that protection, so it is
                    // filtered here — same rationale as the wire: exclusion above.
                    ->reject(fn ($m) => str_contains($m['message'] ?? '', 'autocomplete')
                        && str_contains($m['message'] ?? '', 'hidden'))
                    ->values();

                expect($errors)->toBeEmpty('W3C errors: '.$errors->pluck('message')->join('; '));
            }
        } catch (ConnectionException) {
            test()->markTestSkipped("HTML validator not available at {$validatorUrl}");
        }
    }

    function e2eAssertNoTooltipOverflow($page): void
    {
        // At a mobile width, force every DaisyUI tooltip bubble visible (they only
        // render on :hover, which synthetic events can't trigger), then assert that
        // doing so didn't widen the page. We compare against a baseline captured
        // with tooltips closed so pre-existing horizontal overflow (e.g. a wide
        // admin table) isn't mistaken for a tooltip problem — we only catch overflow
        // the tooltips themselves introduce. NOTE: a tooltip inside an
        // overflow-x-auto container (e.g. leaderboard table headers) scrolls within
        // the table rather than widening the document, so it isn't asserted here.
        $page->resize(390, 844);
        $page->script('window.__baseScrollWidth = document.documentElement.scrollWidth');
        $page->script("document.querySelectorAll('.tooltip').forEach(el => el.classList.add('tooltip-open'))");
        $page->assertScript('document.documentElement.scrollWidth <= window.__baseScrollWidth', true);
    }
}

foreach ($publicPaths as $path) {
    it("has valid HTML structure on {$path}", function () use ($path) {
        $page = visit($path);
        e2eCheckStructure($page);
    });

    it("passes W3C validation on {$path}", function () use ($path, $validatorUrl) {
        $page = visit($path);
        e2eCheckW3C($page, $validatorUrl);
    });
}

foreach ($authPaths as $path) {
    it("has valid HTML structure on {$path} (auth)", function () use ($path) {
        $user = User::factory()->create();
        $this->actingAs($user);
        $page = visit($path);
        e2eCheckStructure($page);
    });

    it("passes W3C validation on {$path} (auth)", function () use ($path, $validatorUrl) {
        $user = User::factory()->create();
        $this->actingAs($user);
        $page = visit($path);
        e2eCheckW3C($page, $validatorUrl);
    });
}

// Dynamic state: the logbook edit modal renders a SECOND flash-form (edit mode)
// while the create-mode form stays on the page. Element IDs must stay unique
// across both, so this state gets its own validation pass rather than relying on
// the default (modal-closed) logbook render above.
it('passes W3C validation on /logbook with the edit modal open (auth)', function () use ($validatorUrl) {
    $user = User::factory()->create();
    Flash::create([
        'user_id' => $user->id,
        'date' => now()->format('Y-m-d'),
        'activity_type' => 'sailing',
        'event_type' => 'regatta',
    ]);
    $this->actingAs($user);

    $page = visit('/logbook');
    $page->click('button:has-text("Edit")');
    // The edit modal's submit button reads "Update Activity" once rendered.
    $page->waitForText('Update Activity');
    e2eCheckW3C($page, $validatorUrl);
});

foreach ($adminPaths as $path) {
    it("has valid HTML structure on {$path} (admin)", function () use ($path) {
        $user = User::factory()->create();
        $user->is_admin = true;
        $user->save();
        $this->actingAs($user);
        $page = visit($path);
        e2eCheckStructure($page);
    });

    it("passes W3C validation on {$path} (admin)", function () use ($path, $validatorUrl) {
        $user = User::factory()->create();
        $user->is_admin = true;
        $user->save();
        $this->actingAs($user);
        $page = visit($path);
        e2eCheckW3C($page, $validatorUrl);
    });
}

// Mobile tooltip-overflow regression guard (pages that render tooltips).
$tooltipPublicPaths = ['/register', '/leaderboard'];
$tooltipAuthPaths = ['/logbook', '/profile'];
$tooltipAdminPaths = ['/admin/sailor-logs', '/admin/fulfillment'];

foreach ($tooltipPublicPaths as $path) {
    it("has no tooltip overflow at mobile width on {$path}", function () use ($path) {
        $page = visit($path);
        e2eAssertNoTooltipOverflow($page);
    });
}

foreach ($tooltipAuthPaths as $path) {
    it("has no tooltip overflow at mobile width on {$path} (auth)", function () use ($path) {
        $user = User::factory()->create();
        $this->actingAs($user);
        $page = visit($path);
        e2eAssertNoTooltipOverflow($page);
    });
}

foreach ($tooltipAdminPaths as $path) {
    it("has no tooltip overflow at mobile width on {$path} (admin)", function () use ($path) {
        $user = User::factory()->create();
        $user->is_admin = true;
        $user->save();
        $this->actingAs($user);
        $page = visit($path);
        e2eAssertNoTooltipOverflow($page);
    });
}
