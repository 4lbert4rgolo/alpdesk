import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';
import typography from '@tailwindcss/typography';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './vendor/laravel/jetstream/**/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Roboto', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                // Identidade AlpDesk
                brand: {
                    DEFAULT: '#0f7a68', // texto/botões (contraste ~5:1 com branco)
                    dark: '#0b5f51',
                    light: '#1eb39a',   // detalhes e foco
                    soft: '#e8f6f3',
                },
                graphite: '#2d3436',
            },
        },
    },

    plugins: [forms, typography],
};
