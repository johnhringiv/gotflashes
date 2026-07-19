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
    single: '#2a78d6', // slot 1 blue — every single-series chart
    previous: '#86b6ef', // lighter step of the same hue: prior-year context
    mix: {
        regatta: '#2a78d6',
        club_race: '#008300',
        practice: '#e87ba4',
        leisure: '#eda100',
    },
    ordinal: ['#6da7ec', '#2a78d6', '#1c5cab', '#0d366b'], // funnel, light→dark
    heat: ['#9ec5f4', '#6da7ec', '#3987e5', '#256abf', '#0d366b'],
    heatZero: '#e2e5e8', // base-200: a day with no flashes
    surface: '#f0f2f4', // base-100: gaps and rings are this color
    grid: '#dde2e7',
    axis: '#c6ccd2',
    text: '#5a6570',
};

const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
const EVENT_TYPES = [
    { key: 'regatta', label: 'Regatta' },
    { key: 'club_race', label: 'Club Race' },
    { key: 'practice', label: 'Practice' },
    { key: 'leisure', label: 'Day Sailing' },
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

    renderMonthly();
    renderHeatmap();
    renderEventMix();
    renderSignups();
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

function roundedRightBar(x, y, width, height, radius) {
    const r = Math.min(radius, height / 2, width);
    if (width <= 0) {
        return '';
    }
    return [
        `M${x},${y}`,
        `L${x + width - r},${y}`,
        `Q${x + width},${y} ${x + width},${y + r}`,
        `L${x + width},${y + height - r}`,
        `Q${x + width},${y + height} ${x + width - r},${y + height}`,
        `L${x},${y + height}`,
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

// ---------------------------------------------------------------------------
// Column chart core (monthly YoY, signups, ages)
// ---------------------------------------------------------------------------

function drawColumnChart(el, { labels, series, height = 240, ariaLabel }) {
    const width = el.clientWidth || 600;
    const margin = { top: 8, right: 8, bottom: 24, left: 34 };
    const plotWidth = width - margin.left - margin.right;
    const plotHeight = height - margin.top - margin.bottom;

    const svg = makeSvg(el, width, height, ariaLabel);
    const tooltip = makeTooltip(el);

    const x = scaleBand().domain(labels).range([margin.left, margin.left + plotWidth]).paddingInner(0.3).paddingOuter(0.1);
    const maxValue = max(series, (s) => max(s.values)) || 0;
    const y = scaleLinear().domain([0, Math.max(1, maxValue)]).nice().range([margin.top + plotHeight, margin.top]);

    drawYAxis(svg, y, y.ticks(4), margin.left, margin.left + plotWidth);

    // X labels
    labels.forEach((label) => {
        svg.append('text')
            .attr('x', x(label) + x.bandwidth() / 2)
            .attr('y', margin.top + plotHeight + 16)
            .attr('text-anchor', 'middle')
            .attr('font-size', 10)
            .attr('fill', COLORS.text)
            .text(label);
    });

    // Bars: ≤24px thick, 2px surface gap between grouped neighbors
    const groupCount = series.length;
    const gap = 2;
    const rawBarWidth = (x.bandwidth() - gap * (groupCount - 1)) / groupCount;
    const barWidth = Math.min(24, rawBarWidth);
    const groupWidth = barWidth * groupCount + gap * (groupCount - 1);

    const bandGroups = new Map();

    labels.forEach((label, i) => {
        const groupStart = x(label) + (x.bandwidth() - groupWidth) / 2;
        const bars = [];
        series.forEach((s, si) => {
            const value = s.values[i];
            if (value > 0) {
                const barX = groupStart + si * (barWidth + gap);
                const bar = svg.append('path')
                    .attr('d', roundedTopBar(barX, y(value), barWidth, y(0) - y(value), 4))
                    .attr('fill', s.color);
                bars.push(bar);
            }
        });
        bandGroups.set(label, bars);

        // Full-height transparent hit target per band — bigger than the marks
        svg.append('rect')
            .attr('x', x(label))
            .attr('y', margin.top)
            .attr('width', x.bandwidth())
            .attr('height', plotHeight)
            .attr('fill', 'transparent')
            .on('pointerenter pointermove', (event) => {
                bars.forEach((bar) => bar.attr('opacity', 0.75));
                tooltip.show(event, [
                    { value: label, label: '' },
                    ...series.map((s) => ({
                        swatch: groupCount > 1 ? s.color : null,
                        value: s.values[i].toLocaleString(),
                        label: s.name,
                    })),
                ]);
            })
            .on('pointerleave', () => {
                bars.forEach((bar) => bar.attr('opacity', 1));
                tooltip.hide();
            });
    });
}

// ---------------------------------------------------------------------------
// C. Flashes by month, year over year
// ---------------------------------------------------------------------------

function renderMonthly() {
    const el = chartContainer('chart-monthly');
    if (!el) {
        return;
    }

    const { monthly, year, previousYear } = currentPayload;
    const hasCurrent = monthly.current.some((v) => v > 0);
    const hasPrevious = monthly.previous.some((v) => v > 0);

    if (!hasCurrent && !hasPrevious) {
        emptyMessage(el, `No flashes logged in ${year} yet.`);
        return;
    }

    const series = [];
    if (hasPrevious) {
        series.push({ name: `days in ${previousYear}`, color: COLORS.previous, values: monthly.previous });
    }
    series.push({ name: `days in ${year}`, color: COLORS.single, values: monthly.current });

    if (series.length > 1) {
        makeLegend(el, series.map((s) => ({ color: s.color, label: s.name.replace('days in ', '') })));
    } else {
        const note = document.createElement('div');
        note.className = 'chart-legend';
        note.textContent = `No ${previousYear} activity to compare against.`;
        el.appendChild(note);
    }

    drawColumnChart(el, {
        labels: MONTHS,
        series,
        height: 260,
        ariaLabel: `Flashes by month, ${year}${hasPrevious ? ` versus ${previousYear}` : ''}`,
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

    const cell = 15;
    const pitch = cell + 3; // 3px surface gap between cells
    const labelLeft = 30;
    const labelTop = 16;

    const jan1 = new Date(Date.UTC(year, 0, 1));
    const dec31 = new Date(Date.UTC(year, 11, 31));
    const firstDayOffset = jan1.getUTCDay();
    const totalWeeks = Math.ceil((firstDayOffset + 365 + (isLeapYear(year) ? 1 : 0)) / 7);

    const width = labelLeft + totalWeeks * pitch + 4;
    const height = labelTop + 7 * pitch + 26;

    const scroll = document.createElement('div');
    scroll.className = 'chart-scroll';
    el.appendChild(scroll);

    const svg = makeSvg(scroll, width, height, `Daily activity heatmap for ${year}`);
    const tooltip = makeTooltip(el);

    const today = new Date();
    const todayISO = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`;

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

    // Less → More legend
    const legendY = labelTop + 7 * pitch + 14;
    let legendX = labelLeft;
    svg.append('text')
        .attr('x', legendX)
        .attr('y', legendY + cell / 2)
        .attr('dy', '0.32em')
        .attr('font-size', 9)
        .attr('fill', COLORS.text)
        .text('Less');
    legendX += 30;
    [COLORS.heatZero, ...COLORS.heat].forEach((color) => {
        svg.append('rect')
            .attr('x', legendX)
            .attr('y', legendY)
            .attr('width', cell)
            .attr('height', cell)
            .attr('rx', 2)
            .attr('fill', color);
        legendX += pitch;
    });
    svg.append('text')
        .attr('x', legendX + 2)
        .attr('y', legendY + cell / 2)
        .attr('dy', '0.32em')
        .attr('font-size', 9)
        .attr('fill', COLORS.text)
        .text('More');
}

function isLeapYear(year) {
    return (year % 4 === 0 && year % 100 !== 0) || year % 400 === 0;
}

// ---------------------------------------------------------------------------
// E. Event type mix over time (100% stacked area)
// ---------------------------------------------------------------------------

function renderEventMix() {
    const el = chartContainer('chart-event-mix');
    if (!el) {
        return;
    }

    const { eventMix, year, monthsToShow } = currentPayload;

    // Months (1-12) that actually have sailing activity, in the visible window
    const points = [];
    for (let month = 1; month <= monthsToShow; month++) {
        const types = eventMix[month] || eventMix[String(month)];
        if (!types) {
            continue;
        }
        const total = EVENT_TYPES.reduce((sum, t) => sum + (types[t.key] || 0), 0);
        if (total > 0) {
            points.push({ month, total, ...types });
        }
    }

    if (points.length === 0) {
        emptyMessage(el, `No sailing days logged in ${year} yet.`);
        return;
    }

    makeLegend(el, EVENT_TYPES.map((t) => ({ color: COLORS.mix[t.key], label: t.label })));

    if (points.length < 2) {
        emptyMessage(el, 'Season mix needs at least two months of sailing — check the data table below.');
        return;
    }

    const width = el.clientWidth || 600;
    const height = 260;
    const margin = { top: 8, right: 8, bottom: 24, left: 38 };
    const plotWidth = width - margin.left - margin.right;
    const plotHeight = height - margin.top - margin.bottom;

    const svg = makeSvg(el, width, height, `Sailing event type mix by month, ${year}`);
    const tooltip = makeTooltip(el);

    const x = scaleLinear()
        .domain([points[0].month, points[points.length - 1].month])
        .range([margin.left, margin.left + plotWidth]);
    const y = scaleLinear().domain([0, 1]).range([margin.top + plotHeight, margin.top]);

    drawYAxis(svg, y, [0, 0.5, 1], margin.left, margin.left + plotWidth, (v) => `${Math.round(v * 100)}%`);

    const stacked = stack()
        .keys(EVENT_TYPES.map((t) => t.key))
        .offset(stackOffsetExpand)(points);

    const areaGen = area()
        .x((d) => x(d.data.month))
        .y0((d) => y(d[0]))
        .y1((d) => y(d[1]))
        .curve(curveMonotoneX);

    stacked.forEach((layer) => {
        svg.append('path')
            .attr('d', areaGen(layer))
            .attr('fill', COLORS.mix[layer.key])
            .attr('stroke', COLORS.surface)
            .attr('stroke-width', 2)
            .attr('stroke-linejoin', 'round');
    });

    // X labels at each month with data
    points.forEach((p) => {
        svg.append('text')
            .attr('x', x(p.month))
            .attr('y', margin.top + plotHeight + 16)
            .attr('text-anchor', 'middle')
            .attr('font-size', 10)
            .attr('fill', COLORS.text)
            .text(MONTHS[p.month - 1]);
    });

    // Crosshair + all-series tooltip
    const crosshair = svg.append('line')
        .attr('y1', margin.top)
        .attr('y2', margin.top + plotHeight)
        .attr('stroke', COLORS.text)
        .attr('stroke-width', 1)
        .attr('opacity', 0);

    svg.append('rect')
        .attr('x', margin.left)
        .attr('y', margin.top)
        .attr('width', plotWidth)
        .attr('height', plotHeight)
        .attr('fill', 'transparent')
        .on('pointerenter pointermove', (event) => {
            const bounds = el.getBoundingClientRect();
            const pointerX = event.clientX - bounds.left;
            const monthValue = x.invert(pointerX);
            const nearest = points.reduce((a, b) => (Math.abs(b.month - monthValue) < Math.abs(a.month - monthValue) ? b : a));

            crosshair.attr('x1', x(nearest.month)).attr('x2', x(nearest.month)).attr('opacity', 1);

            tooltip.show(event, [
                { value: MONTHS[nearest.month - 1], label: `— ${nearest.total} sailing days` },
                ...EVENT_TYPES.map((t) => ({
                    swatch: COLORS.mix[t.key],
                    value: `${Math.round(((nearest[t.key] || 0) / nearest.total) * 100)}%`,
                    label: `${t.label} (${nearest[t.key] || 0})`,
                })),
            ]);
        })
        .on('pointerleave', () => {
            crosshair.attr('opacity', 0);
            tooltip.hide();
        });
}

// ---------------------------------------------------------------------------
// F. New accounts by month
// ---------------------------------------------------------------------------

function renderSignups() {
    const el = chartContainer('chart-signups');
    if (!el) {
        return;
    }

    const { signups, year } = currentPayload;

    if (!signups.some((v) => v > 0)) {
        emptyMessage(el, `No new accounts in ${year}.`);
        return;
    }

    drawColumnChart(el, {
        labels: MONTHS,
        series: [{ name: 'new sailors', color: COLORS.single, values: signups }],
        height: 220,
        ariaLabel: `New accounts by month, ${year}`,
    });
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

    if (!ages.counts.some((v) => v > 0)) {
        emptyMessage(el, `No sailor ages to show for ${year}.`);
        return;
    }

    drawColumnChart(el, {
        labels: ages.labels,
        series: [{ name: 'sailors', color: COLORS.single, values: ages.counts }],
        height: 220,
        ariaLabel: `Sailor age distribution, ${year}`,
    });
}

// ---------------------------------------------------------------------------
// H. Award tier funnel (horizontal bars, ordinal ramp)
// ---------------------------------------------------------------------------

function renderFunnel() {
    const el = chartContainer('chart-funnel');
    if (!el) {
        return;
    }

    const { funnel, year } = currentPayload;
    const maxCount = max(funnel, (d) => d.count) || 0;

    if (maxCount === 0) {
        emptyMessage(el, `No active sailors in ${year} yet.`);
        return;
    }

    const width = el.clientWidth || 600;
    const rowPitch = 48;
    const barHeight = 16;
    const margin = { top: 4, right: 40, left: 4 };
    const height = funnel.length * rowPitch + margin.top;

    const svg = makeSvg(el, width, height, `Sailors by award progress band, ${year}`);
    const tooltip = makeTooltip(el);

    const x = scaleLinear()
        .domain([0, Math.max(1, maxCount)])
        .range([0, width - margin.left - margin.right]);

    funnel.forEach((band, i) => {
        const rowY = margin.top + i * rowPitch;
        const barY = rowY + 22;
        const barWidth = x(band.count);
        const color = COLORS.ordinal[Math.min(i, COLORS.ordinal.length - 1)];

        svg.append('text')
            .attr('x', margin.left)
            .attr('y', rowY + 12)
            .attr('font-size', 11)
            .attr('fill', COLORS.text)
            .text(band.label);

        if (band.count > 0) {
            svg.append('path')
                .attr('d', roundedRightBar(margin.left, barY, barWidth, barHeight, 4))
                .attr('fill', color);
        }

        // Direct value label at the bar end
        svg.append('text')
            .attr('x', margin.left + barWidth + 8)
            .attr('y', barY + barHeight / 2)
            .attr('dy', '0.32em')
            .attr('font-size', 11)
            .attr('font-weight', 600)
            .attr('fill', COLORS.text)
            .text(band.count.toLocaleString());

        svg.append('rect')
            .attr('x', 0)
            .attr('y', rowY)
            .attr('width', width)
            .attr('height', rowPitch)
            .attr('fill', 'transparent')
            .on('pointerenter pointermove', (event) => {
                tooltip.show(event, [{
                    value: `${band.count.toLocaleString()} ${band.count === 1 ? 'sailor' : 'sailors'}`,
                    label: band.label,
                }]);
            })
            .on('pointerleave', () => tooltip.hide());
    });
}
