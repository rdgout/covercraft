import { beforeAll, describe, expect, it, vi } from 'vite-plus/test';

const alpine = vi.hoisted(() => ({ data: vi.fn(), start: vi.fn() }));
vi.mock('alpinejs', () => ({ default: alpine }));
vi.mock('./bootstrap', () => ({}));
vi.mock('./theme', () => ({ initializeTheme: vi.fn() }));

let lineViewerFactory;

beforeAll(async () => {
    vi.stubGlobal('window', {});
    await import('./app');
    lineViewerFactory = alpine.data.mock.calls.find(([name]) => name === 'lineViewer')[1];
});

function line(changed) {
    const classes = new Set();
    const focus = vi.fn();
    return {
        dataset: { changed: changed ? 'true' : 'false' },
        classes,
        classList: {
            add: (value) => classes.add(value),
            remove: (value) => classes.delete(value),
        },
        scrollIntoView: vi.fn(),
        querySelector: () => ({ focus }),
        focus,
    };
}

function viewer(lines) {
    const component = lineViewerFactory();
    component.$root = {
        querySelectorAll: (selector) =>
            selector === 'tr.is-uncovered'
                ? lines
                : lines.filter((row) => row.classes.has('cc-current-line')),
    };
    component.init();
    return component;
}

describe('changed-line navigation', () => {
    it('skips unchanged uncovered lines and wraps through changed lines', () => {
        const old = line(false);
        const first = line(true);
        const second = line(true);
        const component = viewer([old, first, second]);
        component.nextChanged();
        expect(first.focus).toHaveBeenCalledWith({ preventScroll: true });
        expect(old.scrollIntoView).not.toHaveBeenCalled();
        component.nextChanged();
        expect(first.classes.size).toBe(0);
        expect(second.classes.has('cc-current-line')).toBe(true);
        component.nextChanged();
        expect(first.scrollIntoView).toHaveBeenCalledTimes(2);
        component.next();
        expect(first.classes.size).toBe(0);
        expect(old.classes.has('cc-current-line')).toBe(true);
    });

    it('handles reports with no uncovered changed lines', () => {
        const old = line(false);
        const component = viewer([old]);
        component.nextChanged();
        expect(old.scrollIntoView).not.toHaveBeenCalled();
        expect(component.changedLines).toEqual([]);
    });
});
