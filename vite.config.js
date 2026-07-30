import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import istanbul from 'vite-plugin-istanbul';

export default defineConfig(({ command, mode }) => ({
    plugins: [
        // Only use Laravel plugin when building, not during tests
        ...(mode !== 'test' ? [
            laravel({
                input: ['resources/css/app.css', 'resources/js/app.js'],
                refresh: true,
            }),
        ] : []),
        // Browser-JS coverage: COVERAGE=true builds an Istanbul-instrumented
        // bundle (window.__coverage__), harvested by the /__coverage__ beacon
        // during browser tests. Never enabled for normal/prod builds.
        ...(process.env.COVERAGE === 'true' ? [
            istanbul({
                include: 'resources/js/**',
                extension: ['.js'],
                forceBuildInstrument: true,
            }),
        ] : []),
    ],
    base: command === 'build' ? '/build/' : '/',
    test: {
        globals: true,
        environment: 'happy-dom',
        setupFiles: './tests/js/setup.js',
        include: ['tests/js/**/*.test.js'],
        coverage: {
            provider: 'v8',
            include: ['resources/js/**'],
            reporter: ['text', 'lcov'],
        },
    },
}));
