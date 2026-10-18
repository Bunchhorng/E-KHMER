/** @type {import('tailwindcss').Config} */
export default {
  content: ['./index.html', './src/**/*.{vue,js,ts,jsx,tsx}'],
  darkMode: 'class',
  theme: {
    extend: {
      colors: {
        primary: 'rgb(var(--color-primary) / <alpha-value>)',
        'primary-dark': 'rgb(var(--color-primary-dark) / <alpha-value>)',
        canvas: 'rgb(var(--color-canvas) / <alpha-value>)',
        surface: 'rgb(var(--color-surface) / <alpha-value>)',
        'surface-hover': 'rgb(var(--color-surface-hover) / <alpha-value>)',
        ink: 'rgb(var(--color-ink) / <alpha-value>)',
        muted: 'rgb(var(--color-muted) / <alpha-value>)',
        'border-gray': 'rgb(var(--color-border) / <alpha-value>)',
        accent: 'rgb(var(--color-accent) / <alpha-value>)',
        success: 'rgb(var(--color-success) / <alpha-value>)'
      },
      fontFamily: {
        sans: ['Inter', 'ui-sans-serif', 'system-ui', '-apple-system', 'Segoe UI', 'Roboto', 'sans-serif'],
        khmer: ['"Kantumruy Pro"', 'Inter', 'ui-sans-serif', 'system-ui', 'sans-serif']
      },
      fontSize: {
        xs: ['0.875rem', { lineHeight: '1.25rem' }],
        sm: ['1rem', { lineHeight: '1.5rem' }],
        base: ['1.125rem', { lineHeight: '1.75rem' }],
        lg: ['1.25rem', { lineHeight: '1.75rem' }],
        xl: ['1.375rem', { lineHeight: '2rem' }],
        '2xl': ['1.75rem', { lineHeight: '2.25rem' }],
        '3xl': ['2.125rem', { lineHeight: '2.5rem' }],
        '4xl': ['2.5rem', { lineHeight: '2.75rem' }],
        '5xl': ['3.25rem', { lineHeight: '1.1' }],
        '6xl': ['4rem', { lineHeight: '1.1' }],
        '7xl': ['4.75rem', { lineHeight: '1.1' }],
        '8xl': ['6.25rem', { lineHeight: '1.1' }],
        '9xl': ['8.25rem', { lineHeight: '1.1' }]
      },
      boxShadow: {
        card: '0 1px 3px rgba(17, 24, 39, 0.08), 0 1px 2px rgba(17, 24, 39, 0.04)',
        popover: '0 10px 40px rgba(17, 24, 39, 0.16)'
      },
      keyframes: {
        'slide-in-right': {
          from: { transform: 'translateX(100%)' },
          to: { transform: 'translateX(0)' }
        },
        'fade-in': {
          from: { opacity: '0' },
          to: { opacity: '1' }
        }
      },
      animation: {
        'slide-in-right': 'slide-in-right 0.25s ease-out',
        'fade-in': 'fade-in 0.2s ease-out'
      }
    }
  },
  plugins: []
}
