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

    theme: {
        extend: {
            colors: {
                primary: '#00FF41',      // Classic Matrix Green
                secondary: '#003B00',    // Deep Forest Green
                terminal: '#0D0208',     // True Black-Green background
            },
            fontFamily: {
                sans: ['Fira Code', 'ui-monospace', 'monospace', ...defaultTheme.fontFamily.sans],
            },
        },
    },

    plugins: [forms],
};
