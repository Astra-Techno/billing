/** @type {import('tailwindcss').Config} */
export default {
  content: ['./index.html', './src/**/*.{vue,js}'],
  darkMode: 'class',
  theme: {
    extend: {
      colors: {
        primary: {
          50:  '#eef4ff',
          100: '#dce8ff',
          200: '#b9d0ff',
          400: '#5b8def',
          500: '#3b7ded',
          600: '#1a5fd4',
          700: '#154fa8',
          800: '#123f85',
          900: '#0f3269',
        },
        accent: {
          gold: '#c9a227',
          'gold-light': '#f5ecd4',
          teal: '#0d9488',
          'teal-light': '#ccfbf1',
        },
        pay: {
          green: '#059669',
          'green-light': '#d1fae5',
        },
        ink: {
          DEFAULT: '#0c1222',
          soft: '#1a2238',
          muted: '#64748b',
        },
        surface: {
          DEFAULT: '#ffffff',
          dim: '#f4f6fb',
          muted: '#e8ecf4',
          elevated: '#ffffff',
        },
        google: {
          text: '#0f172a',
          muted: '#64748b',
          border: '#e2e8f0',
          divider: '#eef2f7',
        },
        success: { 50: '#ecfdf5', 100: '#d1fae5', 500: '#10b981', 700: '#047857' },
        warning: { 50: '#fffbeb', 100: '#fef3c7', 500: '#f59e0b', 700: '#b45309' },
        danger:  { 50: '#fef2f2', 100: '#fee2e2', 500: '#ef4444', 700: '#b91c1c' },
      },
      fontFamily: {
        sans: ['"Plus Jakarta Sans"', 'Roboto', 'system-ui', 'sans-serif'],
        display: ['"Plus Jakarta Sans"', 'system-ui', 'sans-serif'],
      },
      borderRadius: {
        gpay: '1rem',
        'gpay-lg': '1.25rem',
        'gpay-xl': '1.5rem',
        'gpay-2xl': '1.75rem',
      },
      boxShadow: {
        soft: '0 1px 2px rgba(15, 23, 42, 0.04), 0 2px 8px rgba(15, 23, 42, 0.04)',
        gpay: '0 2px 8px rgba(15, 23, 42, 0.06), 0 8px 24px rgba(15, 23, 42, 0.06)',
        'gpay-lg': '0 8px 32px rgba(15, 23, 42, 0.1), 0 2px 8px rgba(15, 23, 42, 0.04)',
        premium: '0 4px 24px rgba(26, 95, 212, 0.12), 0 12px 48px rgba(15, 23, 42, 0.08)',
        'premium-lg': '0 20px 60px rgba(15, 23, 42, 0.14), 0 8px 24px rgba(26, 95, 212, 0.1)',
        elevated: '0 12px 40px rgba(15, 23, 42, 0.12)',
        'inner-glow': 'inset 0 1px 0 rgba(255, 255, 255, 0.15)',
        'ring-soft': '0 0 0 3px rgba(26, 95, 212, 0.2)',
      },
      backgroundImage: {
        'mesh-app': 'radial-gradient(at 0% 0%, rgba(26, 95, 212, 0.08) 0px, transparent 50%), radial-gradient(at 100% 0%, rgba(13, 148, 136, 0.06) 0px, transparent 50%), radial-gradient(at 50% 100%, rgba(99, 102, 241, 0.05) 0px, transparent 50%)',
        'hero-premium': 'linear-gradient(135deg, #1a5fd4 0%, #154fa8 40%, #0f3269 100%)',
        'fab-premium': 'linear-gradient(135deg, #3b7ded 0%, #1a5fd4 50%, #154fa8 100%)',
      },
    },
  },
  plugins: [
    function ({ addUtilities }) {
      addUtilities({
        '.safe-area-pb': { 'padding-bottom': 'env(safe-area-inset-bottom, 0px)' },
        '.safe-area-pt': { 'padding-top': 'env(safe-area-inset-top, 0px)' },
        '.glass': {
          'background': 'rgba(255, 255, 255, 0.82)',
          'backdrop-filter': 'blur(20px) saturate(180%)',
          '-webkit-backdrop-filter': 'blur(20px) saturate(180%)',
        },
        '.glass-dark': {
          'background': 'rgba(15, 23, 42, 0.75)',
          'backdrop-filter': 'blur(20px) saturate(180%)',
          '-webkit-backdrop-filter': 'blur(20px) saturate(180%)',
        },
      })
    },
  ],
}
