import { beforeAll, describe, expect, it, vi } from 'vite-plus/test';

const alpine = vi.hoisted(() => ({ data: vi.fn(), start: vi.fn() }));
vi.mock('alpinejs', () => ({ default: alpine }));
vi.mock('./bootstrap', () => ({}));
vi.mock('./theme', () => ({ initializeTheme: vi.fn() }));

let lineViewerFactory;

beforeAll(async () => {
    vi.stubGlobal('window', {});
    vi.stubGlobal(
        'ResizeObserver',
        class {
            constructor(callback) {
                this.callback = callback;
                this.observe = vi.fn();
                this.disconnect = vi.fn();
            }
        },
    );
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

function viewer(lines, navigation = null) {
    const component = lineViewerFactory();
    component.$root = {
        style: { setProperty: vi.fn() },
        querySelector: () => navigation,
        querySelectorAll: (selector) =>
            selector === 'tr.is-uncovered'
                ? lines
                : lines.filter((row) => row.classes.has('cc-current-line')),
    };
    component.init();
    return component;
}

describe('changed-line navigation', () => {
    it('keeps jump targets below the sticky toolbar when its controls wrap', () => {
        const navigation = { getBoundingClientRect: vi.fn(() => ({ height: 56 })) };
        const component = viewer([line(true)], navigation);

        expect(component.$root.style.setProperty).toHaveBeenLastCalledWith(
            '--uncovered-navigation-height',
            '56px',
        );
        expect(component.navigationObserver.observe).toHaveBeenCalledWith(navigation);

        navigation.getBoundingClientRect.mockReturnValue({ height: 176 });
        component.navigationObserver.callback();
        expect(component.$root.style.setProperty).toHaveBeenLastCalledWith(
            '--uncovered-navigation-height',
            '176px',
        );

        component.destroy();
        expect(component.navigationObserver.disconnect).toHaveBeenCalledOnce();
    });

    it('handles fully covered files without a toolbar or observer', () => {
        const component = viewer([]);
        expect(component.navigationObserver).toBeNull();
        expect(component.$root.style.setProperty).not.toHaveBeenCalled();
        expect(() => component.destroy()).not.toThrow();
    });

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
