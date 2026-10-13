import { defineConfig } from 'vite-plus';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        tailwindcss(),
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
    ],
    lint: {
        ignorePatterns: ['vendor/**', 'node_modules/**', 'public/**', '_design/**'],
        rules: { curly: ['error', 'all'] },
    },
    fmt: {
        tabWidth: 4,
        singleQuote: true,
        ignorePatterns: [
            '**/*.php',
            '**/*.md',
            'vendor/**',
            'public/**',
            '_design/**',
            'composer.json',
            '.claude/**',
            '.mcp.json',
            'boost.json',
            'opencode.json',
        ],
        overrides: [{ files: ['*.yml', '*.yaml'], options: { tabWidth: 2 } }],
    },
});
