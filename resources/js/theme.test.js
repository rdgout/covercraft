import { describe, expect, it } from 'vite-plus/test';
import { resolveTheme } from './theme';

describe('theme preference', () => {
    it('honors an explicit choice regardless of the system setting', () => {
        expect(resolveTheme('light', true)).toBe('light');
        expect(resolveTheme('dark', false)).toBe('dark');
    });
    it('follows the system when no valid preference exists', () => {
        expect(resolveTheme(null, true)).toBe('dark');
        expect(resolveTheme('invalid', false)).toBe('light');
    });
});
