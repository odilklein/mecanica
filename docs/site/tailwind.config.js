/** @type {import('tailwindcss').Config} */
export default {
  darkMode: 'class',
  content: ['./index.html', './src/**/*.{ts,tsx}'],
  theme: {
    extend: {
      colors: {
        sand: {
          50: 'rgb(var(--sand-50) / <alpha-value>)',
          100: 'rgb(var(--sand-100) / <alpha-value>)',
          200: 'rgb(var(--sand-200) / <alpha-value>)',
          300: 'rgb(var(--sand-300) / <alpha-value>)',
        },
        ink: {
          500: 'rgb(var(--ink-500) / <alpha-value>)',
          600: 'rgb(var(--ink-600) / <alpha-value>)',
          700: 'rgb(var(--ink-700) / <alpha-value>)',
          800: 'rgb(var(--ink-800) / <alpha-value>)',
          900: 'rgb(var(--ink-900) / <alpha-value>)',
        },
        surface: 'rgb(var(--surface) / <alpha-value>)',
        'surface-2': 'rgb(var(--surface-2) / <alpha-value>)',
        line: 'rgb(var(--line) / <alpha-value>)',
        wine: {
          50: 'rgb(var(--wine-50) / <alpha-value>)',
          100: 'rgb(var(--wine-100) / <alpha-value>)',
          200: 'rgb(var(--wine-200) / <alpha-value>)',
          300: 'rgb(var(--wine-300) / <alpha-value>)',
          400: 'rgb(var(--wine-400) / <alpha-value>)',
          500: 'rgb(var(--wine-500) / <alpha-value>)',
          600: 'rgb(var(--wine-600) / <alpha-value>)',
          700: 'rgb(var(--wine-700) / <alpha-value>)',
          800: 'rgb(var(--wine-800) / <alpha-value>)',
          900: 'rgb(var(--wine-900) / <alpha-value>)',
        },
      },
      fontFamily: {
        sans: [
          'ui-sans-serif',
          'system-ui',
          '-apple-system',
          'Segoe UI',
          'Roboto',
          'Helvetica Neue',
          'Arial',
          'sans-serif',
        ],
        mono: ['ui-monospace', 'SFMono-Regular', 'Menlo', 'Consolas', 'monospace'],
      },
      boxShadow: {
        card: '0 1px 2px rgba(43, 36, 31, 0.04), 0 10px 28px -14px rgba(43, 36, 31, 0.22)',
        lift: '0 2px 4px rgba(43, 36, 31, 0.05), 0 22px 48px -20px rgba(142, 31, 12, 0.35)',
        glow: '0 0 0 1px rgba(184, 39, 15, 0.12), 0 18px 50px -18px rgba(184, 39, 15, 0.45)',
      },
      keyframes: {
        'fade-up': {
          '0%': { opacity: '0', transform: 'translateY(8px)' },
          '100%': { opacity: '1', transform: 'translateY(0)' },
        },
        marquee: {
          '0%': { transform: 'translateX(0)' },
          '100%': { transform: 'translateX(-50%)' },
        },
        'node-in': {
          '0%': { opacity: '0', transform: 'translateY(5px) scale(0.985)' },
          '100%': { opacity: '1', transform: 'translateY(0) scale(1)' },
        },
      },
      animation: {
        'fade-up': 'fade-up 460ms cubic-bezier(.2,.7,.3,1) both',
        marquee: 'marquee 32s linear infinite',
      },
    },
  },
  plugins: [],
}
