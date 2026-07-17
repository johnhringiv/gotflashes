// Cloudflare Worker: branded maintenance page for infrastructure outages.
// Operations & deployment: docs/deployment.md ("Cloudflare Maintenance Worker").

// Statuses that mean "infrastructure is down", NOT an app bug.
//
// 502/503/504 — gateway errors from our own stack: HAProxy with no live
//   backend (docker reset), or nginx with PHP-FPM dead. Laravel's
//   `artisan down` also emits 503, which genuinely is maintenance.
//   Real app bugs surface as 500/501 and are deliberately NOT matched.
//
// 520-526/530 — Cloudflare-generated origin errors. When the origin is
//   fully unreachable (pfSense/host reboot), the Worker's fetch() does
//   NOT throw — Cloudflare resolves it with its own error response:
//   521 (connection refused), 522 (timeout), 523 (unreachable),
//   525/526 (origin TLS), 520/524/530 (misc origin failures). These are
//   never emitted by Laravel or HAProxy, so matching them can't mask a bug.
const MAINTENANCE_STATUSES = new Set([502, 503, 504, 520, 521, 522, 523, 524, 525, 526, 530]);

export default {
    async fetch(request) {
        let resp;
        try {
            resp = await fetch(request);
        } catch {
            // Belt-and-suspenders: most origin failures come back as 52x
            // responses (handled below), but the runtime can still throw
            // on internal errors.
            return maintenance(request);
        }

        if (MAINTENANCE_STATUSES.has(resp.status)) {
            return maintenance(request);
        }

        // Everything else — including genuine app 500s — passes through untouched.
        return resp;
    },
};

function maintenance(request) {
    // Cosmetic gate: browser navigations get the full HTML page; failed
    // assets/XHR get a bare 503 (no point shipping an HTML document as the
    // body of a .js/.css/Livewire-update request).
    if (!isPageRequest(request)) {
        return new Response('Service Unavailable', {
            status: 503,
            headers: {
                'retry-after': '120',
                'cache-control': 'no-store',
            },
        });
    }
    return new Response(MAINTENANCE_HTML, {
        status: 503,
        headers: {
            'content-type': 'text/html; charset=utf-8',
            'retry-after': '120',
            'cache-control': 'no-store',
        },
    });
}

// A "page" is a browser navigation (address bar, link click, form submit).
// All current browsers send Sec-Fetch-Mode: navigations are "navigate";
// assets and XHR/fetch — including Livewire's hashed update endpoint — are
// "cors"/"no-cors". Clients that omit the header (curl, uptime monitors)
// fall back to the Accept header.
function isPageRequest(request) {
    const mode = request.headers.get('sec-fetch-mode');
    if (mode) {
        return mode === 'navigate';
    }
    return (request.headers.get('accept') || '').includes('text/html');
}

const MAINTENANCE_HTML = `<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Careening the Hull — G.O.T. Flashes</title>
  <style>
    :root {
      /* Lightning Class brand colors — matches the app theme in resources/css/app.css:
         primary oklch(38% 0.09 245deg) = #2a588c, secondary is the lighter tooltip blue. */
      --lightning-blue: #2a588c;
      --lightning-blue-light: #3d74ac;
    }
    * { box-sizing: border-box; }
    body {
      margin: 0;
      min-height: 100vh;
      display: grid;
      place-items: center;
      font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
      color: #fff;
      background: linear-gradient(160deg, var(--lightning-blue) 0%, var(--lightning-blue-light) 100%);
      padding: 1.5rem;
    }
    .card { max-width: 32rem; text-align: center; }
    .glyph {
      width: 6rem;
      height: 6rem;
      margin: 0 auto 1.25rem;
      display: grid;
      place-items: center;
      background: #fff;
      border-radius: 50%;
      box-shadow: 0 4px 16px rgb(0 0 0 / 25%);
    }
    .glyph img { height: 3.5rem; }
    h1 { font-size: 1.9rem; margin: 0 0 0.75rem; }
    p { font-size: 1.05rem; line-height: 1.6; opacity: 0.92; margin: 0.5rem 0; }
    .small { font-size: 0.9rem; opacity: 0.7; margin-top: 1.5rem; }
    .small a { color: #fff; font-weight: 600; text-underline-offset: 3px; }
  </style>
</head>
<body>
  <main class="card">
    <div class="glyph"><img src="data:image/png;base64,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" alt="Lightning Class logo"></div>
    <h1>Careening the Hull</h1>
    <p>We've hauled <strong>G.O.T. Flashes</strong> out of the water to scrub the hull and
       tighten the rigging.</p>
    <p>We'll be back off the dock and on the water in just a few minutes. Trim your sheets and
       check back shortly.</p>
    <p class="small">If this hangs around longer than a fair-weather race, hail the crew:
       <a href="mailto:admin@gotflashes.com">admin@gotflashes.com</a></p>
  </main>
</body>
</html>`;
