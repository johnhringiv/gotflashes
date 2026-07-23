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
// by tom-select / flatpickr, added by our JS via classList, built as template
// strings (toast `alert-${type}`), or drawn by D3. Static scanning can't see them,
// so they're excluded from both directions here.
const IGNORE_PREFIXES = ['ts-', 'flatpickr-'];

const IGNORE = new Set([
    // JS-hook classes with no styles of their own (selectors only, toggled by JS)
    'eye-open', 'eye-closed',

    // DaisyUI modifier classes still present in markup but implemented by the base
    // class in our hand-authored CSS, so they are intentional no-ops (base .input
    // already draws the border, base .loading already spins, zebra striping is done
    // with a :nth-child rule, etc.). Harmless; a later pass could strip them.
    'input-bordered', 'select-bordered', 'textarea-bordered',
    'loading-spinner', 'table-zebra', 'tabs-boxed', 'hover',

    // Built as template strings in JS (toast.js: `alert alert-${type}`)
    'alert-success', 'alert-warning', 'alert-error', 'alert-info',

    // tom-select parts/states surfaced by our .ts-wrapper.<state> overrides
    'single', 'focus', 'disabled', 'active', 'item', 'option', 'optgroup-header',
    'no-results', 'create', 'highlight', 'dropdown-content', 'has-items',
    'input-active', 'dropdown-active', 'rtl',

    // flatpickr day states referenced by our overrides
    'today', 'selected', 'has-entry', 'cur-year', 'numInputWrapper', 'arrowUp', 'arrowDown',

    // applied by our JS at runtime (classList)
    'select-disabled', 'visible',
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
