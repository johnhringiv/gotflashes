// D3 charts for the public Community Stats page (/stats).
//
// Data flow: App\Livewire\CommunityStats embeds the initial payload in a JSON
// script tag (#community-stats-data) and dispatches the browser event
// 'community-stats-updated' with a fresh payload when the year changes.
// Chart containers live inside wire:ignore blocks, so Livewire never touches
// the D3-owned DOM. Every chart has a server-rendered table twin in the Blade
// view, so no value is reachable only by hovering.

import { select } from 'd3-selection';
import { scaleBand, scaleLinear } from 'd3-scale';
import { max } from 'd3-array';
import { area, curveMonotoneX, stack, stackOffsetExpand } from 'd3-shape';

// Palette validated with the dataviz six-checks validator against the
// base-100 card surface (#f0f2f4). Magenta/yellow sit below 3:1 contrast;
// the relief channel is the legend + tooltips + table views.
const COLORS = {
    ordinal: ['#6da7ec', '#2a78d6', '#1c5cab', '#0d366b'], // funnel, light→dark
    heat: ['#9ec5f4', '#6da7ec', '#3987e5', '#256abf', '#0d366b'],
    heatZero: '#e2e5e8', // base-200: a day with no flashes
    // Gender: conventional blue/pink split; non-binary gets a distinct green,
    // undisclosed a neutral gray (omitted from the public chart anyway).
    gender: {
        male: '#2a78d6',
        female: '#e87ba4',
        non_binary: '#008300',
        prefer_not_to_say: '#898781',
    },
    surface: '#f0f2f4', // base-100: gaps and rings are this color
    grid: '#dde2e7',
    axis: '#c6ccd2',
    text: '#5a6570',
};

const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
// Trailing window for the cumulative-chart tooltips' "growth vs N days ago".
const GROWTH_WINDOW_DAYS = 30;
// Finest-grained flash categories for the cumulative area (stack order + hues).
const FLASH_CATEGORIES = [
    { key: 'regatta', label: 'Regatta', color: '#2a78d6' },
    { key: 'club_race', label: 'Club Race', color: '#008300' },
    { key: 'practice', label: 'Practice', color: '#e87ba4' },
    { key: 'leisure', label: 'Day Sailing', color: '#eda100' },
    { key: 'maintenance', label: 'Maintenance', color: '#1baf7a' },
    { key: 'race_committee', label: 'Race Committee', color: '#4a3aa7' },
];

let currentPayload = null;

// ---------------------------------------------------------------------------
// Bootstrapping
// ---------------------------------------------------------------------------

function initStatsCharts() {
    const dataEl = document.getElementById('community-stats-data');
    if (!dataEl) {
        return;
    }

    try {
        currentPayload = JSON.parse(dataEl.textContent);
    } catch {
        return;
    }

    renderAll();
}

document.addEventListener('DOMContentLoaded', initStatsCharts);
document.addEventListener('livewire:navigated', initStatsCharts);

document.addEventListener('livewire:init', () => {
    window.Livewire.on('community-stats-updated', (event) => {
        const data = Array.isArray(event) ? event[0] : event;
        if (data && data.payload) {
            currentPayload = data.payload;
            renderAll();
        }
    });
});

let resizeTimer;
window.addEventListener('resize', () => {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(() => renderAll(), 150);
});

function renderAll() {
    if (!currentPayload) {
        return;
    }

    renderHeatmap();
    renderSailorGrowth();
    renderFlashFilter();
    renderAges();
    renderFunnel();
}

// ---------------------------------------------------------------------------
// Shared helpers
// ---------------------------------------------------------------------------

function chartContainer(id) {
    const el = document.getElementById(id);
    if (el) {
        el.replaceChildren();
    }
    return el;
}

function emptyMessage(el, text) {
    const div = document.createElement('div');
    div.className = 'chart-empty';
    div.textContent = text;
    el.appendChild(div);
}

function makeTooltip(el) {
    const tip = document.createElement('div');
    tip.className = 'chart-tooltip';
    el.appendChild(tip);

    return {
        show(event, rows) {
            tip.replaceChildren();
            rows.forEach((row) => {
                const line = document.createElement('div');
                if (row.swatch) {
                    const key = document.createElement('span');
                    key.style.display = 'inline-block';
                    key.style.width = '10px';
                    key.style.height = '3px';
                    key.style.borderRadius = '1px';
                    key.style.verticalAlign = 'middle';
                    key.style.marginRight = '5px';
                    key.style.background = row.swatch;
                    line.appendChild(key);
                }
                const value = document.createElement('strong');
                value.textContent = row.value;
                line.appendChild(value);
                if (row.label) {
                    line.appendChild(document.createTextNode(` ${row.label}`));
                }
                if (row.sub) {
                    const sub = document.createElement('span');
                    sub.textContent = `  ${row.sub}`;
                    sub.style.opacity = '0.6';
                    line.appendChild(sub);
                }
                tip.appendChild(line);
            });
            tip.classList.add('visible');
            this.move(event);
        },
        move(event) {
            const bounds = el.getBoundingClientRect();
            const x = event.clientX - bounds.left;
            const y = event.clientY - bounds.top;
            const tipWidth = tip.offsetWidth;
            const left = Math.max(0, Math.min(x + 12, el.clientWidth - tipWidth - 4));
            const top = y - tip.offsetHeight - 10;
            tip.style.left = `${left}px`;
            tip.style.top = `${top < 0 ? y + 14 : top}px`;
        },
        hide() {
            tip.classList.remove('visible');
        },
    };
}

function makeLegend(el, items) {
    const legend = document.createElement('div');
    legend.className = 'chart-legend';
    items.forEach((item) => {
        const entry = document.createElement('span');
        entry.className = 'legend-item';
        const swatch = document.createElement('span');
        swatch.className = 'legend-swatch';
        swatch.style.background = item.color;
        entry.appendChild(swatch);
        entry.appendChild(document.createTextNode(item.label));
        legend.appendChild(entry);
    });
    el.appendChild(legend);
    return legend;
}

// Pick-one control rendered as a connected segmented control (label + pill of
// mutually-exclusive segments; the active one is filled). onSelect(key) fires
// on change. Distinct from filterChip(), which is a multi-select on/off toggle.
function segmentedRadio(title, options, current, onSelect) {
    const wrap = document.createElement('div');
    wrap.className = 'flash-filter-group';
    const label = document.createElement('span');
    label.className = 'ff-group-label';
    label.textContent = title;
    wrap.appendChild(label);
    const seg = document.createElement('div');
    seg.className = 'ff-segmented';
    seg.setAttribute('role', 'radiogroup');
    seg.setAttribute('aria-label', title);
    options.forEach((opt) => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'ff-seg';
        btn.setAttribute('role', 'radio');
        btn.dataset.on = current === opt.key ? '1' : '0';
        btn.setAttribute('aria-checked', current === opt.key ? 'true' : 'false');
        btn.textContent = opt.label;
        btn.addEventListener('click', () => {
            [...seg.querySelectorAll('.ff-seg')].forEach((c, i) => {
                const on = options[i].key === opt.key;
                c.dataset.on = on ? '1' : '0';
                c.setAttribute('aria-checked', on ? 'true' : 'false');
            });
            onSelect(opt.key);
        });
        seg.appendChild(btn);
    });
    wrap.appendChild(seg);
    return wrap;
}

// Multi-select on/off toggle pill with an optional colour swatch. Starts on;
// clicking toggles state.set membership and calls onChange(). Distinct from
// segmentedRadio() (pick-one).
function filterChip(label, swatchColor, initiallyOn, onToggle) {
    const chip = document.createElement('button');
    chip.type = 'button';
    chip.className = 'ff-chip';
    chip.dataset.on = initiallyOn ? '1' : '0';
    chip.setAttribute('aria-pressed', initiallyOn ? 'true' : 'false');
    if (swatchColor) {
        const sw = document.createElement('span');
        sw.className = 'ff-swatch';
        sw.style.background = swatchColor;
        chip.appendChild(sw);
    }
    chip.appendChild(document.createTextNode(label));
    chip.addEventListener('click', () => {
        const on = chip.dataset.on === '1' ? false : true;
        chip.dataset.on = on ? '1' : '0';
        chip.setAttribute('aria-pressed', on ? 'true' : 'false');
        onToggle(on);
    });
    return chip;
}

// Mount a single "Reset" control in the top-right of the chart's card. Clicking
// re-invokes the chart's render fn, which rebuilds it with default (all-on)
// state — the filter state is local to that fn, so re-running is the reset.
// Re-render-safe: a prior Reset (from a year change) is removed first.
function mountReset(el, rerender) {
    const card = el.closest('.card');
    if (!card) {
        return;
    }
    card.style.position = 'relative';
    card.querySelector(':scope > .stats-reset')?.remove();
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'stats-reset';
    btn.dataset.active = '0';
    btn.textContent = 'Reset';
    btn.title = 'Reset filters to default';
    btn.addEventListener('click', rerender);
    card.appendChild(btn);
    return btn;
}

// Bar with a 4px rounded data-end and a square baseline
function roundedTopBar(x, y, width, height, radius) {
    const r = Math.min(radius, width / 2, height);
    if (height <= 0) {
        return '';
    }
    return [
        `M${x},${y + height}`,
        `L${x},${y + r}`,
        `Q${x},${y} ${x + r},${y}`,
        `L${x + width - r},${y}`,
        `Q${x + width},${y} ${x + width},${y + r}`,
        `L${x + width},${y + height}`,
        'Z',
    ].join(' ');
}

function makeSvg(el, width, height, label) {
    return select(el)
        .append('svg')
        .attr('width', width)
        .attr('height', height)
        .attr('role', 'img')
        .attr('aria-label', label);
}

// Horizontal hairline gridlines + tick labels for a linear y scale
function drawYAxis(svg, y, ticks, left, right, format = (v) => v.toLocaleString()) {
    ticks.forEach((tick) => {
        svg.append('line')
            .attr('x1', left)
            .attr('x2', right)
            .attr('y1', y(tick))
            .attr('y2', y(tick))
            .attr('stroke', tick === 0 ? COLORS.axis : COLORS.grid)
            .attr('stroke-width', 1);
        svg.append('text')
            .attr('x', left - 6)
            .attr('y', y(tick))
            .attr('dy', '0.32em')
            .attr('text-anchor', 'end')
            .attr('font-size', 10)
            .attr('fill', COLORS.text)
            .text(format(tick));
    });
}

// Stacked column chart (monthly by activity type, ages by gender). series is
// bottom→top: [{key, label, color, values: [n per label]}]. Adds its own
// legend; the per-band hit target covers the x-label too, and headerFor(i)
// supplies the tooltip's leading row(s).
function drawStackedColumn(el, { labels, series, height = 240, ariaLabel, headerFor, totalUnit = 'item' }) {
    if (series.length > 0) {
        makeLegend(el, series.map((s) => ({ color: s.color, label: s.label })));
    }

    const width = el.clientWidth || 340;
    const margin = { top: 8, right: 8, bottom: 24, left: 30 };
    const plotWidth = width - margin.left - margin.right;
    const plotHeight = height - margin.top - margin.bottom;

    const svg = makeSvg(el, width, height, ariaLabel);
    const tooltip = makeTooltip(el);

    const totals = labels.map((_, i) => series.reduce((sum, s) => sum + (s.values[i] || 0), 0));

    const x = scaleBand().domain(labels).range([margin.left, margin.left + plotWidth]).paddingInner(0.35).paddingOuter(0.12);
    const y = scaleLinear().domain([0, Math.max(1, max(totals))]).nice().range([margin.top + plotHeight, margin.top]);

    drawYAxis(svg, y, y.ticks(4), margin.left, margin.left + plotWidth);

    labels.forEach((lab) => {
        svg.append('text')
            .attr('x', x(lab) + x.bandwidth() / 2)
            .attr('y', margin.top + plotHeight + 16)
            .attr('text-anchor', 'middle')
            .attr('font-size', 10)
            .attr('fill', COLORS.text)
            .text(lab);
    });

    const barWidth = Math.min(28, x.bandwidth());
    const gap = 0; // segments touch — no separator lines between categories

    labels.forEach((lab, i) => {
        const barX = x(lab) + (x.bandwidth() - barWidth) / 2;
        const segs = series.map((s) => ({ ...s, v: s.values[i] || 0 })).filter((s) => s.v > 0);
        let cumulative = 0;
        const bars = [];
        segs.forEach((s, si) => {
            const bottomY = y(cumulative);
            const isTop = si === segs.length - 1;
            const topY = y(cumulative + s.v) + (isTop ? 0 : gap);
            const h = bottomY - topY;
            let mark;
            if (isTop && h > 0) {
                mark = svg.append('path').attr('d', roundedTopBar(barX, topY, barWidth, h, 4)).attr('fill', s.color);
            } else if (h > 0) {
                mark = svg.append('rect').attr('x', barX).attr('y', topY).attr('width', barWidth).attr('height', h).attr('fill', s.color);
            }
            if (mark) {
                bars.push(mark);
            }
            cumulative += s.v;
        });

        svg.append('rect')
            .attr('x', x(lab)).attr('y', margin.top)
            .attr('width', x.bandwidth()).attr('height', plotHeight + margin.bottom)
            .attr('fill', 'transparent')
            .on('pointerenter pointermove', (event) => {
                bars.forEach((b) => b.attr('opacity', 0.8));
                tooltip.show(event, [
                    ...(headerFor ? headerFor(i) : [{ value: lab, label: '' }]),
                    ...segs.map((s) => ({ swatch: s.color, value: s.v.toLocaleString(), label: s.label })),
                    { value: totals[i].toLocaleString(), label: `${totalUnit}${totals[i] === 1 ? '' : 's'} total` },
                ]);
            })
            .on('pointerleave', () => {
                bars.forEach((b) => b.attr('opacity', 1));
                tooltip.hide();
            });
    });
}

// ---------------------------------------------------------------------------
// D. Activity heatmap (GitHub contribution style)
// ---------------------------------------------------------------------------

function renderHeatmap() {
    const el = chartContainer('chart-heatmap');
    if (!el) {
        return;
    }

    const { heatmap, year } = currentPayload;
    const counts = heatmap || {};
    const maxCount = max(Object.values(counts)) || 0;

    if (maxCount === 0) {
        emptyMessage(el, `No flashes logged in ${year} yet.`);
        return;
    }

    const labelLeft = 28;
    const labelTop = 16;
    const rightPad = 6;

    const jan1 = new Date(Date.UTC(year, 0, 1));
    const dec31 = new Date(Date.UTC(year, 11, 31));
    const firstDayOffset = jan1.getUTCDay();

    // Only weeks up to today are painted (a mid-season year stops in July), so
    // size the grid to what's actually shown rather than the full 53 weeks —
    // otherwise the right half is blank and the grid looks left-shoved.
    // Use UTC throughout so the last painted day matches the UTC day loop below.
    // (Mixing local getDate() here with the loop's UTC dates clipped the heatmap a
    // day short around timezone boundaries — US offsets are negative, so local
    // lags UTC for several evening hours.)
    const today = new Date();
    const todayISO = today.toISOString().slice(0, 10);
    const lastDay = year < today.getUTCFullYear() ? dec31 : new Date(Date.UTC(today.getUTCFullYear(), today.getUTCMonth(), today.getUTCDate()));
    const lastDayOfYear = Math.round((lastDay - jan1) / 86400000);
    const weeksShown = Math.floor((lastDayOfYear + firstDayOffset) / 7) + 1;

    // Size cells to fit the container width (so the grid fits without a
    // scrollbar on normal screens); clamp for looks and fall back to scroll
    // only on very narrow viewports.
    const avail = el.clientWidth || 680;
    const cell = Math.max(9, Math.min(15, Math.floor((avail - labelLeft - rightPad) / weeksShown) - 3));
    const pitch = cell + 3;

    const width = labelLeft + weeksShown * pitch + rightPad;
    const height = labelTop + 7 * pitch + 8;

    const scroll = document.createElement('div');
    scroll.className = 'chart-scroll';
    // Centre when the grid fits; only scroll when it genuinely overflows.
    if (width <= avail) {
        scroll.style.textAlign = 'center';
        scroll.style.overflowX = 'visible';
    }
    el.appendChild(scroll);

    const svg = makeSvg(scroll, width, height, `Daily activity heatmap for ${year}`).style('display', 'inline-block');
    const tooltip = makeTooltip(el);

    // Day-of-week labels
    [['Mon', 1], ['Wed', 3], ['Fri', 5]].forEach(([label, row]) => {
        svg.append('text')
            .attr('x', labelLeft - 6)
            .attr('y', labelTop + row * pitch + cell / 2)
            .attr('dy', '0.32em')
            .attr('text-anchor', 'end')
            .attr('font-size', 9)
            .attr('fill', COLORS.text)
            .text(label);
    });

    let lastMonthLabelled = -1;

    for (let date = new Date(jan1); date <= dec31; date.setUTCDate(date.getUTCDate() + 1)) {
        const iso = date.toISOString().slice(0, 10);
        if (iso > todayISO) {
            break;
        }

        const dayOfYear = Math.round((date - jan1) / 86400000);
        const week = Math.floor((dayOfYear + firstDayOffset) / 7);
        const dow = date.getUTCDay();
        const count = counts[iso] || 0;

        // Month label above the first week of each month
        const month = date.getUTCMonth();
        if (month !== lastMonthLabelled && date.getUTCDate() <= 7 && dow === 0 || (month !== lastMonthLabelled && dayOfYear === 0)) {
            svg.append('text')
                .attr('x', labelLeft + week * pitch)
                .attr('y', labelTop - 5)
                .attr('font-size', 9)
                .attr('fill', COLORS.text)
                .text(MONTHS[month]);
            lastMonthLabelled = month;
        }

        const fill = count === 0
            ? COLORS.heatZero
            : COLORS.heat[Math.min(COLORS.heat.length - 1, Math.floor(((count - 1) / maxCount) * COLORS.heat.length))];

        const displayDate = `${MONTHS[month]} ${date.getUTCDate()}`;

        svg.append('rect')
            .attr('x', labelLeft + week * pitch)
            .attr('y', labelTop + dow * pitch)
            .attr('width', cell)
            .attr('height', cell)
            .attr('rx', 2)
            .attr('fill', fill)
            .on('pointerenter pointermove', function (event) {
                select(this).attr('stroke', COLORS.text).attr('stroke-width', 1);
                tooltip.show(event, [{
                    value: `${count} ${count === 1 ? 'flash' : 'flashes'}`,
                    label: `on ${displayDate}`,
                }]);
            })
            .on('pointerleave', function () {
                select(this).attr('stroke', null);
                tooltip.hide();
            });
    }

    // "Less → More" scale, as a clean centred row below the grid
    const legend = document.createElement('div');
    legend.className = 'chart-heat-legend';
    const less = document.createElement('span');
    less.textContent = 'Less';
    legend.appendChild(less);
    [COLORS.heatZero, ...COLORS.heat].forEach((color) => {
        const sw = document.createElement('span');
        sw.className = 'heat-swatch';
        sw.style.background = color;
        legend.appendChild(sw);
    });
    const more = document.createElement('span');
    more.textContent = 'More';
    legend.appendChild(more);
    el.appendChild(legend);
}

// ---------------------------------------------------------------------------
// F2. Community growth — cumulative sailors, stackable by gender or age
// ---------------------------------------------------------------------------

function dayOfYear(dateStr, year) {
    return Math.round((Date.parse(`${dateStr}T00:00:00Z`) - Date.UTC(year, 0, 1)) / 86400000);
}

// Age groups use an ordinal blue ramp (young → old); unknown a neutral gray.
const GROWTH_AGE_COLORS = { youth: '#9ec5f4', u32: '#6da7ec', mid: '#3987e5', masters: '#0d366b', unknown: '#898781' };

function renderSailorGrowth() {
    const el = chartContainer('chart-cumulative');
    if (!el) {
        return;
    }

    const { year, sailorGrowth } = currentPayload;
    if (!sailorGrowth || !sailorGrowth.rows || sailorGrowth.rows.length === 0) {
        emptyMessage(el, `Not enough signups yet to chart ${year} growth.`);
        return;
    }

    const dims = {
        gender: { label: 'Gender', field: 'gender', values: sailorGrowth.genders, color: (k) => COLORS.gender[k] || COLORS.text },
        age: { label: 'Age', field: 'ageGroup', values: sailorGrowth.ageGroups, color: (k) => GROWTH_AGE_COLORS[k] || COLORS.text },
    };

    const state = {
        // Default to age: it maps to the class's youth/U32 growth goal, gives
        // four meaningful bands (vs a male-skewed gender split), and pairs with
        // the age×gender snapshot in the Sailor ages chart. Gender is a toggle.
        stackBy: 'age',
        mode: 'count',
        active: {
            gender: new Set(sailorGrowth.genders.map((g) => g.key)),
            age: new Set(sailorGrowth.ageGroups.map((a) => a.key)),
        },
    };

    const controls = document.createElement('div');
    controls.className = 'flash-filter-controls';

    controls.appendChild(segmentedRadio('Stack by', [{ key: 'age', label: 'Age' }, { key: 'gender', label: 'Gender' }], state.stackBy, (k) => {
        state.stackBy = k;
        buildValueChips();
        draw();
    }));
    controls.appendChild(segmentedRadio('Show', [{ key: 'count', label: 'Count' }, { key: 'percent', label: 'Share %' }], state.mode, (k) => {
        state.mode = k;
        draw();
    }));

    // Value chips for the current stack dimension (rebuilt when it changes)
    const valueWrap = document.createElement('div');
    valueWrap.className = 'flash-filter-group';
    controls.appendChild(valueWrap);

    function buildValueChips() {
        valueWrap.replaceChildren();
        const dim = dims[state.stackBy];
        const label = document.createElement('span');
        label.className = 'ff-group-label';
        label.textContent = dim.label;
        valueWrap.appendChild(label);
        dim.values.forEach((item) => {
            const set = state.active[state.stackBy];
            valueWrap.appendChild(filterChip(item.label, dim.color(item.key), set.has(item.key), (on) => {
                if (on) {
                    set.add(item.key);
                } else {
                    set.delete(item.key);
                }
                draw();
            }));
        });
    }
    buildValueChips();

    el.appendChild(controls);
    const chartDiv = document.createElement('div');
    chartDiv.className = 'stats-chart';
    el.appendChild(chartDiv);

    // "Filters active" = a band toggled off in either stack dimension.
    let resetBtn = null;
    const isFiltered = () => state.active.gender.size !== sailorGrowth.genders.length
        || state.active.age.size !== sailorGrowth.ageGroups.length;

    function draw() {
        const dim = dims[state.stackBy];
        const activeSet = state.active[state.stackBy];
        const cats = dim.values.filter((v) => activeSet.has(v.key)).map((v) => ({ key: v.key, label: v.label, color: dim.color(v.key) }));
        const rows = sailorGrowth.rows
            .filter((r) => activeSet.has(r[dim.field]))
            .map((r) => ({ date: r.date, category: r[dim.field], count: r.count }));
        const points = cumulateByCategory(rows, cats, year);
        drawStackedArea(chartDiv, points, cats, year, `Community growth by ${state.stackBy}, ${year}`, state.mode === 'percent');
        if (resetBtn) {
            resetBtn.dataset.active = isFiltered() ? '1' : '0';
        }
    }
    draw();
    resetBtn = mountReset(el, renderSailorGrowth);
}

// ---------------------------------------------------------------------------
// F3. Cumulative flashes by category — stacked area, running totals
// ---------------------------------------------------------------------------

// Cumulate daily category rows [{date, category, count}] into stacked points
// [{date, day, <catKey>: runningTotal}], seeded with a year-start baseline.
function cumulateByCategory(rows, cats, year) {
    const byDate = new Map();
    rows.forEach((r) => {
        if (!byDate.has(r.date)) {
            byDate.set(r.date, {});
        }
        const d = byDate.get(r.date);
        d[r.category] = (d[r.category] || 0) + r.count;
    });

    const dates = [...byDate.keys()].sort();
    const running = {};
    cats.forEach((c) => { running[c.key] = 0; });

    const points = [];
    if (dates.length === 0 || dates[0] !== `${year}-01-01`) {
        points.push({ date: `${year}-01-01`, day: dayOfYear(`${year}-01-01`, year), ...running });
    }
    dates.forEach((date) => {
        const d = byDate.get(date);
        cats.forEach((c) => { running[c.key] += d[c.key] || 0; });
        points.push({ date, day: dayOfYear(date, year), ...running });
    });
    return points;
}

// Trailing-window momentum text for a cumulative value: how much it gained over
// the last GROWTH_WINDOW_DAYS, plus a % vs then. Guards the pitfalls of a %-change
// on a cumulative series — a zero base reads "new" (not ∞), and a tiny base drops
// the % (avoids "+2 → 200%" whiplash early in the season). Returns null when there
// is nothing to say. Compact by design; the tooltip's caption row states the window.
function growthText(current, base) {
    if (current === 0) {
        return null;
    }
    const delta = current - base;
    if (delta === 0) {
        return 'flat';
    }
    const gain = `+${delta.toLocaleString()}`;
    // base 0 → brand new; base < 5 → too small for a meaningful %
    const rate = base === 0 ? 'new' : base < 5 ? null : `▲${Math.round((delta / base) * 100)}%`;
    return rate ? `${gain} ${rate}` : gain;
}

// Draw a cumulative stacked area into `el` from pre-cumulated points and the
// active category list [{key, label, color}]. Shared by the plain and the
// filterable cumulative-flashes charts.
function drawStackedArea(el, points, cats, year, ariaLabel, normalize = false) {
    el.replaceChildren();

    // In % mode the expand offset divides by the per-date total, so drop any
    // leading zero-total points (the year-start baseline) to avoid NaN.
    const totalAt = (p) => cats.reduce((sum, c) => sum + (p[c.key] || 0), 0);
    const pts = normalize ? points.filter((p) => totalAt(p) > 0) : points;

    if (pts.length < 2 || cats.length === 0) {
        emptyMessage(el, 'Nothing to show — turn a filter back on.');
        return;
    }

    const last = pts[pts.length - 1];
    const width = el.clientWidth || 600;
    const height = 260;
    const margin = { top: 12, right: 48, bottom: 24, left: 38 };
    const plotWidth = width - margin.left - margin.right;
    const plotHeight = height - margin.top - margin.bottom;

    const svg = makeSvg(el, width, height, ariaLabel);
    const tooltip = makeTooltip(el);

    const lastDay = last.day;
    const totalMax = totalAt(last);
    const x = scaleLinear().domain([0, Math.max(1, lastDay)]).range([margin.left, margin.left + plotWidth]);
    const y = normalize
        ? scaleLinear().domain([0, 1]).range([margin.top + plotHeight, margin.top])
        : scaleLinear().domain([0, Math.max(1, totalMax)]).nice().range([margin.top + plotHeight, margin.top]);

    drawYAxis(svg, y, normalize ? [0, 0.5, 1] : y.ticks(4), margin.left, margin.left + plotWidth, normalize ? (v) => `${Math.round(v * 100)}%` : undefined);

    const lastMonth = new Date(Date.UTC(year, 0, 1) + lastDay * 86400000).getUTCMonth();
    for (let m = 0; m <= lastMonth; m++) {
        const monthDay = dayOfYear(`${year}-${String(m + 1).padStart(2, '0')}-01`, year);
        svg.append('text')
            .attr('x', x(monthDay)).attr('y', margin.top + plotHeight + 16)
            .attr('text-anchor', 'middle').attr('font-size', 10).attr('fill', COLORS.text)
            .text(MONTHS[m]);
    }

    const series = stack().keys(cats.map((c) => c.key));
    if (normalize) {
        series.offset(stackOffsetExpand);
    }
    const stacked = series(pts);
    const areaGen = area().x((d) => x(d.data.day)).y0((d) => y(d[0])).y1((d) => y(d[1])).curve(curveMonotoneX);
    stacked.forEach((layer, i) => {
        svg.append('path')
            .attr('d', areaGen(layer))
            .attr('fill', cats[i].color)
            .attr('stroke', COLORS.surface)
            .attr('stroke-width', 1)
            .attr('stroke-linejoin', 'round');
    });

    if (!normalize) {
        svg.append('text')
            .attr('x', x(lastDay) + 8).attr('y', y(totalMax)).attr('dy', '0.32em')
            .attr('font-size', 11).attr('font-weight', 600).attr('fill', COLORS.text)
            .text(totalMax.toLocaleString());
    }

    const crosshair = svg.append('line')
        .attr('y1', margin.top).attr('y2', margin.top + plotHeight)
        .attr('stroke', COLORS.text).attr('stroke-width', 1).attr('opacity', 0);

    svg.append('rect')
        .attr('x', margin.left).attr('y', margin.top)
        .attr('width', plotWidth).attr('height', plotHeight)
        .attr('fill', 'transparent')
        .on('pointerenter pointermove', (event) => {
            const bounds = el.getBoundingClientRect();
            const hoverDay = Math.max(0, Math.min(lastDay, Math.round(x.invert(event.clientX - bounds.left))));
            const pointAt = (day) => pts.filter((p) => p.day <= day).pop();
            const asOf = pointAt(hoverDay) || pts[0];
            const prior = pointAt(hoverDay - GROWTH_WINDOW_DAYS); // 30-day-trailing baseline (undefined before it exists)
            const date = new Date(Date.UTC(year, 0, 1) + hoverDay * 86400000);
            const total = totalAt(asOf);
            crosshair.attr('x1', x(hoverDay)).attr('x2', x(hoverDay)).attr('opacity', 1);
            tooltip.show(event, [
                // Caption row: the date, plus what the +/▲ figures below mean.
                { value: `${MONTHS[date.getUTCMonth()]} ${date.getUTCDate()}`, sub: `change vs ${GROWTH_WINDOW_DAYS} days ago` },
                // Total on its own row, aligned with the bands.
                { value: total.toLocaleString(), label: 'Total', sub: growthText(total, prior ? totalAt(prior) : 0) },
                ...cats.map((c) => {
                    const n = asOf[c.key] || 0;
                    const val = normalize ? `${total ? Math.round((n / total) * 100) : 0}%` : n.toLocaleString();
                    return { swatch: c.color, value: val, label: c.label, sub: growthText(n, prior ? prior[c.key] || 0 : 0) };
                }),
            ]);
        })
        .on('pointerleave', () => {
            crosshair.attr('opacity', 0);
            tooltip.hide();
        });
}

// F4. Filterable cumulative flashes — toggle genders / age groups / activity
function renderFlashFilter() {
    const el = chartContainer('chart-flash-filter');
    if (!el) {
        return;
    }

    const { year, flashFilter } = currentPayload;
    if (!flashFilter || !flashFilter.rows || flashFilter.rows.length === 0) {
        emptyMessage(el, `No flashes to filter for ${year}.`);
        return;
    }

    const catColor = Object.fromEntries(FLASH_CATEGORIES.map((c) => [c.key, c.color]));
    const categories = flashFilter.categories.map((c) => ({ ...c, color: catColor[c.key] || COLORS.text }));

    const state = {
        gender: new Set(flashFilter.genders.map((g) => g.key)),
        ageGroup: new Set(flashFilter.ageGroups.map((a) => a.key)),
        category: new Set(categories.map((c) => c.key)),
        mode: 'count',
    };

    const controls = document.createElement('div');
    controls.className = 'flash-filter-controls';

    // Count / Share (%) — pick-one segmented control
    controls.appendChild(segmentedRadio('Show', [{ key: 'count', label: 'Count' }, { key: 'percent', label: 'Share %' }], state.mode, (k) => {
        state.mode = k;
        draw();
    }));

    const groups = [
        { title: 'Activity', dim: 'category', items: categories, swatch: (k) => catColor[k] },
        { title: 'Gender', dim: 'gender', items: flashFilter.genders },
        { title: 'Age', dim: 'ageGroup', items: flashFilter.ageGroups },
    ];

    groups.forEach((group) => {
        if (group.items.length < 2 && group.dim !== 'category') {
            return; // a lone gender/age value isn't worth a toggle
        }
        const wrap = document.createElement('div');
        wrap.className = 'flash-filter-group';
        const label = document.createElement('span');
        label.className = 'ff-group-label';
        label.textContent = group.title;
        wrap.appendChild(label);

        group.items.forEach((item) => {
            const set = state[group.dim];
            wrap.appendChild(filterChip(item.label, group.swatch ? group.swatch(item.key) : null, set.has(item.key), (on) => {
                if (on) {
                    set.add(item.key);
                } else {
                    set.delete(item.key);
                }
                draw();
            }));
        });
        controls.appendChild(wrap);
    });
    el.appendChild(controls);

    const chartDiv = document.createElement('div');
    chartDiv.className = 'stats-chart';
    el.appendChild(chartDiv);

    // "Filters active" = any activity / gender / age band toggled off.
    let resetBtn = null;
    const isFiltered = () => state.category.size !== categories.length
        || state.gender.size !== flashFilter.genders.length
        || state.ageGroup.size !== flashFilter.ageGroups.length;

    function draw() {
        const activeCats = categories.filter((c) => state.category.has(c.key));
        const rows = flashFilter.rows.filter((r) => state.gender.has(r.gender) && state.ageGroup.has(r.ageGroup) && state.category.has(r.category));
        const points = cumulateByCategory(rows, activeCats, year);
        drawStackedArea(chartDiv, points, activeCats, year, `Cumulative flashes, filtered, ${year}`, state.mode === 'percent');
        if (resetBtn) {
            resetBtn.dataset.active = isFiltered() ? '1' : '0';
        }
    }
    draw();
    resetBtn = mountReset(el, renderFlashFilter);
}

// ---------------------------------------------------------------------------
// G. Sailor age distribution
// ---------------------------------------------------------------------------

function renderAges() {
    const el = chartContainer('chart-ages');
    if (!el) {
        return;
    }

    const { ages, year } = currentPayload;
    const genders = ages.genders || [];
    const labels = ages.labels || [];

    const series = genders.map((g) => ({
        key: g.key,
        label: g.label,
        color: COLORS.gender[g.key] || COLORS.text,
        values: labels.map((lab) => (ages.counts[lab] || {})[g.key] || 0),
    }));
    const anyData = series.some((s) => s.values.some((v) => v > 0));

    if (!anyData) {
        emptyMessage(el, `No sailor ages to show for ${year}.`);
        return;
    }

    drawStackedColumn(el, {
        labels,
        series,
        height: 240,
        ariaLabel: `Sailor ages by Lightning Class division and gender, ${year}`,
        headerFor: (i) => [{ value: labels[i], label: `· ages ${ages.ranges[i]}` }],
        totalUnit: 'sailor',
    });
}

// ---------------------------------------------------------------------------
// H. Award funnel — centred conversion funnel (registered → tiers)
// ---------------------------------------------------------------------------

function renderFunnel() {
    const el = chartContainer('chart-funnel');
    if (!el) {
        return;
    }

    const { funnel, year } = currentPayload;
    const top = funnel[0]?.count || 0; // registered = widest stage

    if (top === 0) {
        emptyMessage(el, `No registered sailors for ${year} yet.`);
        return;
    }

    const width = el.clientWidth || 600;
    const rowPitch = 52;
    const barHeight = 26;
    const topPad = 18;
    const height = funnel.length * rowPitch + topPad;
    const maxBar = width - 16;
    const cx = width / 2;

    const svg = makeSvg(el, width, height, `Award-progress funnel for ${year}`);
    const tooltip = makeTooltip(el);

    funnel.forEach((stage, i) => {
        const rowY = topPad + i * rowPitch;
        const barY = rowY + 14;
        const w = Math.max(2, (stage.count / top) * maxBar);
        const color = COLORS.heat[Math.min(i, COLORS.heat.length - 1)];
        const pct = Math.round((stage.count / top) * 100);
        const prev = i > 0 ? funnel[i - 1].count : null;
        const stepPct = prev ? Math.round((stage.count / prev) * 100) : null;

        // Stage label + count above the bar
        svg.append('text')
            .attr('x', cx)
            .attr('y', rowY + 8)
            .attr('text-anchor', 'middle')
            .attr('font-size', 11)
            .attr('fill', COLORS.text)
            .text(`${stage.label} · ${stage.count.toLocaleString()}${i > 0 ? ` (${pct}% of registered)` : ''}`);

        // Centred narrowing bar
        if (stage.count > 0) {
            svg.append('rect')
                .attr('x', cx - w / 2).attr('y', barY)
                .attr('width', w).attr('height', barHeight)
                .attr('rx', 3).attr('fill', color);
        }

        // Full-row hit target
        svg.append('rect')
            .attr('x', 0).attr('y', rowY)
            .attr('width', width).attr('height', rowPitch)
            .attr('fill', 'transparent')
            .on('pointerenter pointermove', (event) => {
                const rows = [{ value: `${stage.count.toLocaleString()} ${stage.count === 1 ? 'sailor' : 'sailors'}`, label: stage.label }];
                if (stepPct !== null) {
                    rows.push({ value: `${stepPct}%`, label: `from ${prev.toLocaleString()} at "${funnel[i - 1].label}"` });
                }
                tooltip.show(event, rows);
            })
            .on('pointerleave', () => tooltip.hide());
    });
}
