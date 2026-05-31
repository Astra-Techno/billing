/** @type {import('tailwindcss').Config} */
export default {
  content: ['./index.html', './src/**/*.{vue,js}'],
  theme: {
    extend: {
      colors: {
        primary: {
          50:  '#e8f0fe',
          100: '#d2e3fc',
          200: '#aecbfa',
          500: '#4285f4',
          600: '#1a73e8',
          700: '#1967d2',
          800: '#185abc',
        },
        pay: {
          green: '#1e8e3e',
          'green-light': '#e6f4ea',
        },
        surface: {
          DEFAULT: '#ffffff',
          dim: '#f8f9fa',
          muted: '#f1f3f4',
        },
        google: {
          text: '#202124',
          muted: '#5f6368',
          border: '#dadce0',
          divider: '#e8eaed',
        },
        success: { 50: '#e6f4ea', 100: '#ceead6', 500: '#34a853', 700: '#1e8e3e' },
        warning: { 50: '#fef7e0', 100: '#feefc3', 500: '#f9ab00', 700: '#e37400' },
        danger:  { 50: '#fce8e6', 100: '#f8d7da', 500: '#ea4335', 700: '#c5221f' },
      },
      fontFamily: {
        sans: ['Roboto', 'Google Sans', 'system-ui', 'sans-serif'],
      },
      borderRadius: {
        gpay: '1rem',
        'gpay-lg': '1.5rem',
        'gpay-xl': '1.75rem',
      },
      boxShadow: {
        soft: '0 1px 2px rgba(60,64,67,0.06), 0 1px 3px rgba(60,64,67,0.1)',
        gpay: '0 1px 3px rgba(60,64,67,0.12), 0 4px 8px rgba(60,64,67,0.08)',
        'gpay-lg': '0 4px 16px rgba(60,64,67,0.12)',
        elevated: '0 8px 24px rgba(60,64,67,0.15)',
        'ring-soft': '0 0 0 3px rgba(26, 115, 232, 0.15)',
      },
    },
  },
  plugins: [
    function ({ addUtilities }) {
      addUtilities({
        '.safe-area-pb': {
          'padding-bottom': 'env(safe-area-inset-bottom, 0px)',
        },
        '.safe-area-pt': {
          'padding-top': 'env(safe-area-inset-top, 0px)',
        },
      })
    },
  ],
}
