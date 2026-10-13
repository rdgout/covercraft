import './bootstrap';
import Alpine from 'alpinejs';
import { initializeTheme } from './theme';

initializeTheme();

Alpine.data('repositoryForm', (initial, branchesUrl) => ({
    ...initial,
    branches: [],
    loading: false,
    error: '',
    selection: 0,
    async select(value) {
        const selection = ++this.selection;
        this.branches = [];
        this.loading = false;
        this.error = '';
        if (!value) {
            return;
        }
        const repo = JSON.parse(value);
        this.owner = repo.owner;
        this.name = repo.name;
        this.branch = repo.branch;
        this.loading = true;
        try {
            const response = await fetch(branchesUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                },
                body: JSON.stringify({ owner: repo.owner, name: repo.name }),
            });
            if (!response.ok) {
                throw new Error('Branches unavailable');
            }
            const data = await response.json();
            if (selection === this.selection) {
                this.branches = data.branches;
            }
        } catch {
            if (selection === this.selection) {
                this.error =
                    'Branches could not be loaded. You can enter the branch name manually.';
            }
        } finally {
            if (selection === this.selection) {
                this.loading = false;
            }
        }
    },
}));

Alpine.data('copyText', (value) => ({
    label: 'Copy',
    async copy() {
        try {
            await navigator.clipboard.writeText(value);
            this.label = 'Copied';
        } catch {
            this.label = 'Select and copy';
        }
        setTimeout(() => {
            this.label = 'Copy';
        }, 2500);
    },
}));

Alpine.data('fileTree', () => ({
    query: '',
    matches(path) {
        return path.toLowerCase().includes(this.query.trim().toLowerCase());
    },
    directoryMatches(path) {
        return [...this.$root.querySelectorAll('[data-file-path]')].some(
            (element) =>
                element.dataset.filePath.startsWith(`${path}/`) &&
                this.matches(element.dataset.filePath),
        );
    },
    expand(open) {
        this.$root.querySelectorAll('details').forEach((element) => {
            element.open = open;
        });
    },
}));

Alpine.data('lineViewer', () => ({
    index: -1,
    lines: [],
    init() {
        this.lines = [...this.$root.querySelectorAll('tr.is-uncovered')];
    },
    next() {
        if (!this.lines.length) {
            return;
        }
        this.lines[this.index]?.classList.remove('cc-current-line');
        this.index = (this.index + 1) % this.lines.length;
        const line = this.lines[this.index];
        line.classList.add('cc-current-line');
        line.scrollIntoView({ block: 'center' });
        line.querySelector('a')?.focus({ preventScroll: true });
    },
}));

window.Alpine = Alpine;
Alpine.start();
