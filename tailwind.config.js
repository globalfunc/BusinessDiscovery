import defaultTheme from 'tailwindcss/defaultTheme';
import tailwindcssAnimate from 'tailwindcss-animate';

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: ['class'],
    content: [
        './resources/**/*.blade.php',
        './resources/**/*.tsx',
        './resources/**/*.ts',
    ],
    theme: {
        extend: {
            colors: {
                bg: 'var(--lb-bg)',
                'bg-elevated': 'var(--lb-bg-elevated)',
                surface: 'var(--lb-surface)',
                'surface-2': 'var(--lb-surface-2)',
                'surface-glass': 'var(--lb-surface-glass)',
                line: 'var(--lb-line)',
                'line-strong': 'var(--lb-line-strong)',
                'line-accent': 'var(--lb-line-accent)',
                accent: {
                    DEFAULT: 'var(--lb-accent)',
                    2: 'var(--lb-accent-2)',
                    deep: 'var(--lb-accent-deep)',
                    glow: 'var(--lb-accent-glow)',
                },
                blue: 'var(--lb-blue)',
                teal: 'var(--lb-teal)',
                red: 'var(--lb-red)',
                text: {
                    DEFAULT: 'var(--lb-text)',
                    muted: 'var(--lb-text-muted)',
                    faint: 'var(--lb-text-faint)',
                },
            },
            fontFamily: {
                display: ['Fraunces', 'Georgia', 'serif'],
                ui: ['"Space Grotesk"', ...defaultTheme.fontFamily.sans],
                body: ['Inter', ...defaultTheme.fontFamily.sans],
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
            },
            borderRadius: {
                admin: '16px',
                bo: '20px',
                section: '24px',
            },
            boxShadow: {
                glass: '0 8px 30px rgba(0, 0, 0, 0.35)',
            },
            keyframes: {
                rise: {
                    '0%': { opacity: '0', transform: 'translateY(10px)' },
                    '100%': { opacity: '1', transform: 'translateY(0)' },
                },
            },
            animation: {
                rise: 'rise 450ms ease',
            },
        },
    },
    plugins: [tailwindcssAnimate],
};
