import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],

    darkMode: 'class',

    theme: {
        extend: {
            colors: {
                primary: '#38BDF8',      // Sky-400 — calm, professional accent
                secondary: '#64748B',    // Slate-500 — neutral secondary accent
                // Reversible tokens: value flips between :root (light) and .dark (dark)
                // via CSS custom properties in resources/css/app.css, so existing
                // markup (bg-terminal, bg-surface, text-ink) adapts automatically.
                terminal: 'rgb(var(--color-canvas) / <alpha-value>)',
                surface: 'rgb(var(--color-surface) / <alpha-value>)',
                ink: 'rgb(var(--color-ink) / <alpha-value>)',
            },
            fontFamily: {
                sans: ['Figtree', 'ui-sans-serif', 'system-ui', ...defaultTheme.fontFamily.sans],
                mono: ['Figtree', 'ui-sans-serif', 'system-ui', ...defaultTheme.fontFamily.sans],
            },
        },
    },

    plugins: [forms],
};
