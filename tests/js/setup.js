// Test setup file for Vitest
import { expect, afterEach } from 'vitest';

// Cleanup DOM after each test
afterEach(() => {
    document.body.innerHTML = '';
});

// Add custom matchers if needed
expect.extend({
    // Custom matchers can be added here
});