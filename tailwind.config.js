/** @type {import('tailwindcss').Config} */
export default {
  content: ['./index.html', './src/**/*.{vue,js}'],
  darkMode: 'class',
  theme: {
    extend: {
      colors: {
        primary: {
          50:  '#eef2ff',
          100: '#e0e7ff',
          200: '#c7d2fe',
          300: '#a5b4fc',
          400: '#818cf8',
          500: '#6366f1',
          600: '#4f46e5',
          700: '#4338ca',
          800: '#3730a3',
          900: '#312e81',
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
          DEFAULT: '#111827',
          soft: '#374151',
          muted: '#6b7280',
        },
        surface: {
          DEFAULT: '#ffffff',
          dim: '#f9fafb',
          muted: '#f3f4f6',
          elevated: '#ffffff',
        },
        google: {
          text: '#111827',
          muted: '#6b7280',
          border: '#e5e7eb',
          divider: '#f3f4f6',
        },
        sidebar: {
          bg:           '#111827',
          hover:        '#1f2937',
          active:       '#1e1b4b',
          border:       '#1f2937',
          text:         '#9ca3af',
          'text-active':'#e0e7ff',
        },
        success: { 50: '#ecfdf5', 100: '#d1fae5', 500: '#10b981', 700: '#047857' },
        warning: { 50: '#fffbeb', 100: '#fef3c7', 500: '#f59e0b', 700: '#b45309' },
        danger:  { 50: '#fef2f2', 100: '#fee2e2', 500: '#ef4444', 700: '#b91c1c' },
      },
      fontFamily: {
        sans: ['"Plus Jakarta Sans"', 'Inter', 'system-ui', 'sans-serif'],
        display: ['"Plus Jakarta Sans"', 'Inter', 'system-ui', 'sans-serif'],
      },
      borderRadius: {
        gpay:       '0.75rem',
        'gpay-lg':  '0.875rem',
        'gpay-xl':  '1rem',
        'gpay-2xl': '1.25rem',
      },
      boxShadow: {
        soft:         '0 1px 3px rgba(0,0,0,0.06), 0 1px 2px rgba(0,0,0,0.04)',
        gpay:         '0 1px 3px rgba(0,0,0,0.08), 0 4px 12px rgba(0,0,0,0.05)',
        'gpay-lg':    '0 4px 16px rgba(0,0,0,0.08), 0 1px 4px rgba(0,0,0,0.04)',
        premium:      '0 4px 24px rgba(99,102,241,0.18), 0 1px 4px rgba(0,0,0,0.06)',
        'premium-lg': '0 12px 40px rgba(0,0,0,0.1), 0 4px 16px rgba(99,102,241,0.12)',
        elevated:     '0 8px 32px rgba(0,0,0,0.08)',
        'inner-glow': 'inset 0 1px 0 rgba(255,255,255,0.12)',
        'ring-soft':  '0 0 0 3px rgba(99,102,241,0.2)',
      },
      backgroundImage: {
        'mesh-app':     'linear-gradient(135deg, #f9fafb 0%, #f3f4f6 100%)',
        'hero-premium': 'linear-gradient(135deg, #4f46e5 0%, #4338ca 40%, #312e81 100%)',
        'fab-premium':  'linear-gradient(135deg, #6366f1 0%, #4f46e5 50%, #4338ca 100%)',
      },
    },
  },
  plugins: [
    function ({ addUtilities }) {
      addUtilities({
        '.safe-area-pb': { 'padding-bottom': 'env(safe-area-inset-bottom, 0px)' },
        '.safe-area-pt': { 'padding-top':    'env(safe-area-inset-top, 0px)'    },
        '.glass': {
          'background':              'rgba(255,255,255,0.95)',
          'backdrop-filter':         'blur(12px)',
          '-webkit-backdrop-filter': 'blur(12px)',
        },
        '.glass-dark': {
          'background':              'rgba(17,24,39,0.92)',
          'backdrop-filter':         'blur(12px)',
          '-webkit-backdrop-filter': 'blur(12px)',
        },
      })
    },
  ],
}
