/*
 * app.css is a hand-authored, framework-free stylesheet using a deliberate
 * compact-utility idiom (one dense rule per line, Tailwind-derived escaped
 * class names like `.md\:max-w-xs`). All error-catching rules stay on; the
 * rules nulled below are cosmetic opinions that fight that idiom or the
 * file's already-valid notation. (A .cjs config instead of JSON so this
 * rationale can live next to the rules it explains.)
 */
module.exports = {
    extends: ['stylelint-config-standard'],
    rules: {
        // Blank-line rhythm: the file groups one-line rules tightly on purpose.
        'custom-property-empty-line-before': null,
        'rule-empty-line-before': null,
        'at-rule-empty-line-before': null,
        'comment-empty-line-before': null,

        // Compact utilities: many one-line rules with 2-3 declarations.
        'declaration-block-single-line-max-declarations': null,
        'declaration-block-no-redundant-longhand-properties': null,

        // Tailwind-escaped names (.md\:max-w-xs, .text-base-content\/70) and
        // vendor-derived keywords don't fit the standard patterns.
        'selector-class-pattern': null,
        'value-keyword-case': null,
        'property-no-vendor-prefix': null,

        // Precedence comes from @layer order, not specificity ordering — this
        // rule's warnings are noise under the layer model. The CSS class
        // validator + visual regression checks are the correctness net here.
        'no-descending-specificity': null,

        // Notation preferences that match the file's existing style.
        'import-notation': 'string',
        'hue-degree-notation': 'angle',
        'media-feature-range-notation': 'prefix',
        'alpha-value-notation': null,
    },
};
