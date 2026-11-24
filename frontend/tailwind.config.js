/** @type {import('tailwindcss').Config} */
export default {
  content: ['./index.html', './src/**/*.{vue,js,ts,jsx,tsx}'],
  darkMode: 'class',
  theme: {
    borderRadius: {
      none: '0px',
      sm: '0px',
      DEFAULT: '0px',
      md: '0px',
      lg: '0px',
      xl: '0px',
      '2xl': '0px',
      '3xl': '0px',
      full: '0px'
    },
    boxShadow: {
      none: '0 0 #0000',
      sm: '0 1px 1px rgb(15 23 42 / 0.04)',
      DEFAULT: '0 1px 2px rgb(15 23 42 / 0.05)',
      md: '0 2px 4px rgb(15 23 42 / 0.06)',
      lg: '0 3px 8px rgb(15 23 42 / 0.07)',
      xl: '0 5px 14px rgb(15 23 42 / 0.08)',
      '2xl': '0 8px 20px rgb(15 23 42 / 0.10)',
      inner: 'inset 0 1px 2px rgb(15 23 42 / 0.05)'
    },
    extend: {
      colors: {
        primary: 'rgb(var(--color-primary) / <alpha-value>)',
        'primary-dark': 'rgb(var(--color-primary-dark) / <alpha-value>)',
        canvas: 'rgb(var(--color-canvas) / <alpha-value>)',
        surface: 'rgb(var(--color-surface) / <alpha-value>)',
        'surface-hover': 'rgb(var(--color-surface-hover) / <alpha-value>)',
        'surface-raised': 'rgb(var(--color-surface-raised) / <alpha-value>)',
        'surface-muted': 'rgb(var(--color-surface-muted) / <alpha-value>)',
        'surface-active': 'rgb(var(--color-surface-active) / <alpha-value>)',
        ink: 'rgb(var(--color-ink) / <alpha-value>)',
        'text-secondary': 'rgb(var(--color-text-secondary) / <alpha-value>)',
        'text-muted': 'rgb(var(--color-text-muted) / <alpha-value>)',
        muted: 'rgb(var(--color-muted) / <alpha-value>)',
        'border-gray': 'rgb(var(--color-border) / <alpha-value>)',
        'border-strong': 'rgb(var(--color-border-strong) / <alpha-value>)',
        'border-focus': 'rgb(var(--color-border-focus) / <alpha-value>)',
        accent: 'rgb(var(--color-accent) / <alpha-value>)',
        success: 'rgb(var(--color-success) / <alpha-value>)',
        warning: 'rgb(var(--color-warning) / <alpha-value>)',
        danger: 'rgb(var(--color-danger) / <alpha-value>)',
        info: 'rgb(var(--color-info) / <alpha-value>)'
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
        card: 'var(--shadow-card)',
        raised: 'var(--shadow-raised)',
        popover: '0 6px 18px rgba(17, 24, 39, 0.10)'
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
