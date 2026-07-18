# HTML Validation (W3C / validator.nu) — Fixes for #34

The browser suite validates every page's **live DOM** against a
[validator.nu](https://validator.nu) (`ghcr.io/validator/validator`) instance —
see `tests/Browser/HtmlValidation/HtmlQualityTest.php`. Those W3C checks were
previously stubbed out (`->skip('Fix HTML errors first — see #34')`). This
document records the errors that were found, how each was fixed, and why the
fixes are **visually zero-impact** (verified by before/after full-page pixel
diffs — see "Verification" below).

## Running the checks locally

```bash
# 1. Start the validator (same image CI uses)
docker run -d --name vnu-validator -p 8888:8888 ghcr.io/validator/validator

# 2. Run the suite
HTML_VALIDATOR_URL=http://localhost:8888 APP_ENV=testing \
  ./vendor/bin/pest tests/Browser/HtmlValidation
```

If the validator isn't reachable, the W3C tests `markTestSkipped()` rather than
fail, so the suite stays green without Docker (CI provides the validator as a
service in `.github/workflows/coverage.yml`).

## Errors found and fixes

| # | Validator error | Where it came from | Fix | Visual impact |
|---|---|---|---|---|
| 1 | `aria-label` not allowed on a generic `<span>` (×42) | flatpickr renders each calendar day as `<span class="flatpickr-day" aria-label="…">` | `resources/js/multi-date-picker.js` — the existing `onDayCreate` hook now sets `role="button"` on each day. The days are interactive, so the role is semantically correct and makes `aria-label` valid. | None (ARIA attr, no rendered box) |
| 2 | `<div>` not allowed as a child of `<label>` (×6) | DaisyUI's `.label` markup uses `<div>` children, and two field labels wrapped a `<div class="tooltip">` | `flash-form.blade.php`: the two `<label class="form-control">` wrappers became `<div class="form-control">`; label→control association is preserved via a real `<label for>` (Activity Type) and `aria-labelledby` (Sailing Type). `user-profile-fields.blade.php` + `flash-form.blade.php`: the tooltip `<div class="tooltip">` became `<span class="tooltip">` (phrasing content, valid inside a label). | None |
| 3 | Heading level skipped (`h1`→`h3`) (×2) | `home.blade.php` award-tier "Days" cards and the `sailor-logs` empty-state heading were `<h3>` directly under the page `<h1>` | Changed those `<h3>`→`<h2>`. Tailwind Preflight strips default heading sizes and the elements keep their explicit `text-*`/`font-*` classes, so size/weight are unchanged. | None |
| 4 | Duplicate `id="toast-container"` (×1) | `forgot-password`/`reset-password` each defined their own `#toast-container` **and** inherit one from `<x-layout>` | Removed the per-page containers. They were already dead: the layout's container appears first in the DOM, so `document.getElementById('toast-container')` always returned it — page toasts already rendered there. | None (removed element never received toasts) |
| 5 | `autocomplete` on/off not allowed on `<input type="hidden">` (×10) | Laravel's `@csrf` / `csrf_field()` emits `<input type="hidden" name="_token" … autocomplete="off">` | **Not a DOM change** — filtered in the harness (see below). | None |
| 6 | Duplicate `id="sailing-type-label"` (edit-modal state only) | The logbook edit modal renders a **second** flash-form (edit mode) while the create-mode form stays on the page; that id was hardcoded, not mode-suffixed like its siblings | `flash-form.blade.php` — made the id (and its `aria-labelledby`) mode-aware (`sailing-type-label` / `sailing-type-label-edit`), matching the existing `activity_type` / `activity_type_edit` pattern | None |

> Error #6 was **pre-existing** (the id was hardcoded before this work) but was
> never caught because the harness only validated the default, modal-closed
> logbook render. A dedicated test now opens the edit modal and validates that
> state — see `HtmlQualityTest.php` ("… with the edit modal open").

### Why #5 is filtered instead of "fixed"

`autocomplete="off"` on the hidden CSRF token is a **deliberate Laravel default**
(`Illuminate\Foundation\helpers.php::csrf_field()`). It stops browsers from
restoring a stale token value from the back/forward cache on back-navigation,
which would otherwise produce a spurious `419 Page Expired` on the next submit —
a case this app specifically handles (`bootstrap/app.php`, `tests/Feature/Auth/StaleCsrfTest.php`).
The WHATWG spec only permits autofill-detail tokens (not `on`/`off`) on hidden
inputs, so validator.nu flags it, but the attribute is framework-owned and
protective. Removing it site-wide would fight the framework and drop a real
safeguard, so `HtmlQualityTest::e2eCheckW3C()` filters this one message —
exactly the same pattern already used for Livewire's non-standard `wire:*`
attributes.

## Verification (no visual change)

For each affected page, full-page screenshots were captured before and after the
changes (identical viewport, same routes) and diffed with ImageMagick
(`compare -metric AE -fuzz 1%`):

- **`/` and `/register`: 0 differing pixels** — byte-identical. (`/register`
  exercises the district/fleet tooltip `div`→`span` change with no random data,
  so it proves that change is neutral; `/profile` uses the same component.)
- **`/logbook` create form and the edit modal: 0 differing pixels** — measured
  with a *deterministic* user (fixed name/data) so the only variable is the code.
  This covers both flash-form modes (`label`→`div`, tooltip `div`→`span`,
  `span`→`label`, mode-aware ids, `aria-labelledby`).
- `/profile`, `/admin/sailor-logs`: the only differing pixels are **random
  factory-user data** (names/emails/addresses that regenerate per test run) and
  the nav username — never the structural elements that changed.
- `/password/reset`: only a transient email-field placeholder render-timing
  difference; the removed toast container had no rendered footprint.

### Scope of the guarantee
Validation is enforced on the harness's routes in their **default rendered
state**, plus the logbook **edit-modal** state. Not exhaustively validated:
other interaction states (form-error renders, the delete-confirm and admin
modals, TomSelect/flatpickr open panels), the token reset page
(`/password/reset/{token}`), framework error pages, and non-desktop viewports
(mobile is covered only by the tooltip-overflow guard, not a full W3C pass).

## Accessibility note (side benefit, not a visual change)

Two fixes slightly *improve* semantics: flatpickr day cells now expose
`role="button"`, and the Activity/Sailing Type selects gained explicit
label associations (`<label for>` / `aria-labelledby`) that replace the implicit
association previously provided by the `<label>` wrapper.
