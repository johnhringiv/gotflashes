import { describe, it, expect } from 'vitest';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { validateCSSUsage } from './utils/css-validator.js';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const css = fs.readFileSync(path.join(root, 'resources/css/app.css'), 'utf-8');

const sourcePatterns = [
    'resources/views/**/*.blade.php',
    'resources/js/**/*.js',
    // Email templates ship their own inline <style>; their classes aren't app.css.
    '!resources/views/emails/**',
];

// Classes that are real but applied at RUNTIME (never in static markup): generated
// by tom-select, added by our JS via classList, built as template strings (toast
// `alert-${type}`), or drawn by D3. Static scanning can't see them, so they're
// excluded from both directions here.
const IGNORE_PREFIXES = [];

const IGNORE = new Set([
    // JS-hook classes with no styles of their own (selectors only, toggled by JS)
    'eye-open', 'eye-closed',

    // Built as template strings in JS (toast.js: `alert alert-${type}`)
    'alert-success', 'alert-warning', 'alert-error', 'alert-info',

    // combobox.js runtime states (classList toggles)
    'active',

    // date-picker.js day states, composed into a template string at render time
    'today', 'selected', 'has-entry', 'other-month',

    // applied by our JS at runtime (classList)
    'select-disabled', 'visible',

    // Defined intentionally but not applied in current markup — NOT dead rules:
    // guard/hook/completeness selectors that would change behaviour if removed.
    'btn-active', // `.btn:active:not(.btn-active)` escape hatch
    'menu-item', // `.menu :where(... .menu-item)` extensibility hook
    'textarea-error', // rides the shared .input-error/.select-error/.textarea-error rule
    'tooltip-open', // programmatic reveal: `.tooltip.tooltip-open::before`
    'tooltip-bottom', // completes the tooltip direction set (top/right/left/bottom)
]);

function run() {
    return validateCSSUsage({ css, baseDir: root, sourcePatterns, ignore: IGNORE, ignorePrefixes: IGNORE_PREFIXES });
}

describe('CSS class validation', () => {
    it('every class used in Blade/JS is defined in app.css', () => {
        const { usedButUndefined } = run();
        expect(
            usedButUndefined,
            `\nClasses used in Blade/JS but not defined in app.css:\n${usedButUndefined
                .map((c) => `  .${c}`)
                .join('\n')}\n\nFix: implement the rule in resources/css/app.css, correct the ` +
                `markup, or add it to IGNORE in this test if it is applied purely at runtime.\n`,
        ).toEqual([]);
    });

    it('reports dead CSS (defined in app.css but never used)', () => {
        const { definedButUnused } = run();
        if (definedButUnused.length > 0) {
            // Informational: surface likely-dead rules without failing the build.
            console.warn(
                `\n⚠️  ${definedButUnused.length} class(es) defined in app.css but not referenced ` +
                    `in Blade/JS (possible dead CSS):\n${definedButUnused.map((c) => `  .${c}`).join('\n')}\n`,
            );
        }
        expect(Array.isArray(definedButUnused)).toBe(true);
    });
});
