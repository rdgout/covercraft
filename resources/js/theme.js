export function resolveTheme(stored, systemDark) {
    return stored === 'dark' || stored === 'light' ? stored : systemDark ? 'dark' : 'light';
}

export function initializeTheme() {
    const system = window.matchMedia('(prefers-color-scheme: dark)');
    let stored;
    try {
        stored = localStorage.getItem('covercraft-theme');
    } catch {
        stored = null;
    }
    const apply = () => {
        document.documentElement.dataset.theme = resolveTheme(stored, system.matches);
        document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
            button.setAttribute(
                'aria-pressed',
                String(document.documentElement.dataset.theme === 'dark'),
            );
        });
    };
    apply();
    system.addEventListener('change', apply);
    document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            stored = document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark';
            try {
                localStorage.setItem('covercraft-theme', stored);
            } catch {
                /* The selection still applies for this page. */
            }
            apply();
        });
    });
}
