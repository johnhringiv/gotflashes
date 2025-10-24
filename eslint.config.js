import js from '@eslint/js';
import globals from 'globals';

export default [
    js.configs.recommended,
    {
        languageOptions: {
            ecmaVersion: 'latest',
            sourceType: 'module',
            globals: {
                ...globals.browser,
                ...globals.node,
                Livewire: 'readonly',
            },
        },
        rules: {
            'no-console': 'warn',
            'no-unused-vars': ['error', { argsIgnorePattern: '^_' }],
            'prefer-const': 'error',
            'no-var': 'error',
        },
    },
    {
        ignores: [
            'public/build/*',
            'vendor/*',
            'node_modules/*',
            'storage/*',
            'bootstrap/cache/*',
        ],
    },
];