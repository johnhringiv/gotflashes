# Livewire 4 — Leverage Plan & Parallel-Request Race Findings

Status as of the `fixes/maintenance-and-deps` branch: the app is upgraded to
**Livewire 4.3.1** (on Laravel 13 / PHP 8.5). This doc captures (a) the planned
work to *take advantage* of Livewire 4's new capabilities, and (b) a full
write-up of the parallel-request race we hit in the browser tests, its root
cause, and the options for fully closing it.

---

## Part 1 — Livewire 4 leverage plan

Three independent enhancement PRs, ordered by value/risk. Each is its own
branch/PR off `develop` (or after this branch merges). None are blockers.

### PR1 — Drop `unsafe-eval` via `csp_safe` *(do first: highest value, lowest risk)*
- **Why:** removes `'unsafe-eval'` from the `script-src` CSP — a real
  XSS-hardening win that advances the earlier Mozilla-CSP work.
- **Why low-risk for us:** the app uses **no custom Alpine directives** (only
  `@cspNonce`). The sole reason we carry `unsafe-eval` is Livewire's *internal*
  Alpine. Livewire 4's `csp_safe` swaps in Alpine's CSP build (no `eval`), and
  since we author no Alpine expressions, nothing of ours can break under it.
- **Changes:** `config/livewire.php` → `'csp_safe' => true`;
  `app/Http/Middleware/ContentSecurityPolicy.php` → drop `'unsafe-eval'`.
- **Verify:** browser suite + manual — flash form, leaderboard tabs, toasts
  still reactive under the stricter CSP; watch console for CSP violations.
- **Effort:** small.

### PR2 — Replace morph hooks with the v4 interceptor API *(robustness)*
- **Why:** the flatpickr/TomSelect/password reinit rides on
  `morph.added`/`morph.updated` + `requestAnimationFrame` — the timing-sensitive
  pattern CLAUDE.md flags as production-fragile. v4's
  `Livewire.interceptRequest({ onMorph, onRender })` is the purpose-built
  "after-DOM-morph" hook, removing the rAF guesswork.
- **Files (5):** `multi-date-picker.js`, `flash-form.js`, `user-profile-form.js`,
  `password-toggle.js`, `verification-banner.js`. (`toast.js`/`sailor-logs.js`
  use `Livewire.on` events — stable, leave alone.)
- **Approach:** migrate one component at a time behind existing init guards;
  browser-verify each (esp. the flatpickr "logged date becomes disabled after
  save" flow) before the next.
- **Note:** this is *also* the API needed for the race-fix "latest-wins" option
  below (see Part 2) — the two efforts may share the interceptor groundwork.
- **Effort:** medium.

### PR3 — Islands / deferred load *(optional perf, lowest priority)*
- **Why:** ProgressCard re-renders on every `flash-saved`/`flash-deleted`;
  Leaderboard paginates. `@island` lets regions update independently;
  `#[Defer]`/lazy can load below-the-fold content after first paint.
- **Candidates:** ProgressCard (defer/island), Leaderboard tabs (island per tab).
- **Bonus:** islands isolate a region's re-render, which is also a *structural*
  mitigation for the race in Part 2 (an island's response won't morph fields
  outside it).
- **Effort:** medium. Pursue only if perf warrants.

**Recommended order:** PR1 → PR2 → (PR3 if warranted).

---

## Part 2 — The parallel-request race (issue + findings)

### Symptom
The Pest browser suite's registration/profile tests flaked: after filling all
fields and submitting, the form stayed on `/register` with a Livewire
"The X field is required" error for a field that *was* filled — and the empty
field changed run-to-run (zip one run, date-of-birth the next). Non-deterministic
failure of near-identical tests ⇒ a race, not a logic bug.

### Root cause — Livewire's stateless snapshot model
Livewire 4 runs `wire:model.live` syncs **in parallel** (v3 serialized them).
But the deeper cause is intrinsic to Livewire's architecture:

- The **server is stateless** between requests. Every request ships the
  **entire component snapshot**; the snapshot *is* the component's only memory.
- A request is **not** "set B = valB" — the server rehydrates the whole
  component from the request's snapshot, applies the change, **re-runs
  `render()` (the whole Blade template)**, and returns a full new snapshot + HTML
  diff. `render()` is opaque and genuinely coupled here (`updated()` →
  `validateOnly()`, shared error bag, etc.), so Livewire must assume any change
  can affect any part of the output.
- Each request also carries only its **own dirty change** on top of the **last
  server-confirmed snapshot** (once a property is sent, it's no longer dirty).

So two "independent" field updates are not independent: they both read+rewrite
the same whole-component state and re-render the same whole view. Whichever
response is **morphed in last** overwrites the DOM wholesale — **including fields
it never knew about.**

### Why "B winning" clobbers A
```
S0 (confirmed) = { A: "", B: "" }

1. Blur A → RA = { snapshot: S0, updates: { A: "valA" } }   (A now clean)
2. Blur B → RB sent before RA returns, so base is still S0:
            RB = { snapshot: S0, updates: { B: "valB" } }    (RB's base has A empty;
                                                              A is NOT in RB.updates)
3. RA returns → SA = { A:"valA", B:"" }  → morph: A=valA, B=""
4. RB returns LAST → SB = { A:"", B:"valB" } → morph: **A="" (clobbered)**, B=valB
```
RB "won" the arrival order, but RB's server-side worldview never contained A
(stale base + only B's change), so its full re-render wipes A. The victim is
whichever field's confirming response doesn't land last.

Serializing the fills (let RA fully complete so the confirmed snapshot includes
A *before* RB is built) eliminates it — RB then carries `{A:valA}` in its base.

### Real-world exposure — vanishingly small (even on a phone / poor connection)
The race needs two field changes whose requests **overlap**: you leave field A
(fires A's request) and then leave field B *within A's round-trip window*.

- The gap between "leave A" and "leave B" = **the time spent typing in B** — for
  a person filling name/email/address, that's **several seconds per field**.
- A round-trip, even on a rough mobile connection, is ~**0.3–2s**.
- So A's request completes *seconds before* B is ever left → the requests don't
  overlap → no clobber. A **worse** connection widens the window, but human
  typing speed (seconds/field) stays far above it, so the gap still doesn't
  close. Triggering it would require **tab-tab-tabbing through fields in under a
  second**, which isn't how anyone fills a form they're actually completing.
- The browser tests only hit it because Playwright's `fill()` is **instant** —
  it fires all ~11 blurs within milliseconds, faster than any network answers.
  That's the harness, not a user.
- **Browser autofill likely does NOT trigger it** — it sets values without
  per-field `blur`, so the `.live.blur` syncs don't fire until submit, and
  `wire:submit` bundles everything in one request.
- **One residual edge:** typing the *last* field and *instantly* tapping Submit
  on a slow link could show a transient "field required" for a filled field —
  but `wire:submit` generally bundles the still-dirty value, and worst case is a
  re-tap. Always transient; **no data loss, corruption, or security impact.**

**Verdict:** not a real user-facing reliability problem. The test harness's
instant fill is the only thing that reliably reproduces it.

### This is a known Livewire limitation
Maintainer position (livewire discussion #8466): **"not a bug per-se" — a design
limitation**; there is **no built-in mechanism** to cancel superseded requests or
apply "latest-wins." Documented community solutions:
1. **Consolidate/serialize** (debounce / Alpine batch into one request) — official
   mitigation.
2. **Latest-wins** (request-id/timestamp; discard stale responses) — works, but
   the community (and the Laracasts OP who shipped it) calls it **"hacky"**; no
   native API.
3. **Input freezing** — bad UX for text.
4. **Top-level props over nested Form objects** — nested form objects are
   wholesale-replaced; top-level props are patched individually.

**Checked:** `RegistrationForm` and `ProfileForm` already use **top-level public
properties**, not nested `Form` objects. So mitigation #4 is already in place and
is *not sufficient* — confirming our clobber is the client-side out-of-order
**morph**, which top-level-vs-nested doesn't address.

### Current mitigation (committed)
Treated as a **test-fidelity** fix (the tests must wait for v4's async syncs, the
way a real user naturally does), not an app change — because normal users are
effectively safe and we want to keep live per-field validation.

`tests/Pest.php` helpers (`trackLivewireRequests`, `settleLivewire`, `fillLive`,
`fillRegistrationForm`) **serialize each field's sync** — fill, blur, then wait
until **no Livewire request is in flight** before the next field. The wait is
**condition-based** (resolves as soon as Livewire is idle), **not a fixed sleep**.
Applied to the registration, register+profile, and profile-update browser tests.

**Verification:** two consecutive full `php artisan test tests/Browser` runs
(157 passed, 0 failed; was 153/4-failed), RegistrationTest ×3, worst test ×5,
plus `composer check` (382 Unit/Feature + Pint + PHPStan + vitest) — all green.

### Options to FULLY close it app-side (so even slow-network users are safe)
1. **Defer to submit** (`wire:model`, no `.live`): eliminates per-field requests
   ⇒ no race. Simplest/bulletproof, but **loses per-field live validation**
   (validation moves to submit). Rejected by product preference.
2. **Latest-wins via interceptor** (PR2 territory): use
   `interceptMessage`/`interceptRequest` to discard/skip a superseded response's
   morph (or abort the in-flight request). Keeps live validation + parallelism.
   **Unverified:** need to confirm the v4 interceptor can actually *skip applying*
   a stale response/morph (not just observe/cancel). If it can't, this hits the
   same "no native support" wall the community describes.
3. **Islands (PR3):** isolating the form (or sub-regions) limits what a response
   re-renders. Only helps at **per-field/section granularity** (a field's response
   can't morph a field in another island), which is heavy and not islands' intended
   use; it does **not** fix the within-island race. So islands are *tangential*
   here, not the clean fix — their real value is perf (ProgressCard / Leaderboard).

### Recommendation — WON'T DO unless reported
**Decision:** stop at the committed **test-level fix**. Do **not** build the
app-level closure (interceptor latest-wins) speculatively.

Rationale: maintainers treat the race as by-design; our real-world exposure is
**vanishingly small** (see "Real-world exposure" above — human typing is seconds
per field, far slower than any round-trip, so requests don't overlap during
normal use); and the only reliable reproducer is the test harness's instant
`fill()`, which the committed serialization fix already handles. Closing it
app-side would mean either losing per-field live validation (option 1) or
shipping the "hacky," unverified interceptor pattern (option 2) — cost without a
real user benefit.

**Revisit only if** we get an actual field report of a user seeing a filled field
blank itself out (or a spurious "required" on a filled field). At that point,
spike the **interceptor latest-wins (option 2)** and prove it by reverting the
test helpers to instant `fill()`: if the suite then passes reliably with no
test-side waits, the app genuinely protects real users.

---

## Sources
- Livewire discussion #8466 — race condition with `.live.debounce`:
  https://github.com/livewire/livewire/discussions/8466
- Laracasts — "Better approach to ignoring stale Livewire updates?":
  https://laracasts.com/discuss/channels/livewire/better-approach-to-ignoring-stale-livewire-updates
- Medium — "When Livewire Forms and File Uploads Collide: A Race Condition":
  https://medium.com/@n.davis_34774/when-livewire-forms-and-file-uploads-collide-a-race-condition-8e99ab6151ae
- Livewire 4 docs — Actions / Async: https://livewire.laravel.com/docs/4.x/attribute-async
- Livewire 4 docs — Troubleshooting: https://livewire.laravel.com/docs/4.x/troubleshooting
- Livewire 4 upgrade guide: https://livewire.laravel.com/docs/upgrading
