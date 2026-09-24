/** @type {import('tailwindcss').Config} */
export default {
  content: [
    './resources/**/*.blade.php',
    './resources/**/*.js',
    './resources/**/*.vue',
  ],
  theme: {
    extend: {
      colors: {
        // iruali brand (see docs/brand/ and the iruali design system)
        // Lagoon: the main brand colour. 600 / DEFAULT is the button fill (white text 5.2:1).
        primary: {
          50: '#EAF6F4',
          100: '#CDEBE6',
          200: '#9DD8CE',
          300: '#63C0B2',
          400: '#2FA697',
          500: '#129184',
          600: '#0B7A70',
          700: '#09655D',
          800: '#0A514B',
          900: '#0B433F',
          950: '#042825',
          DEFAULT: '#0B7A70',
          hover: '#09655D',
          ring: '#0B7A70',
        },
        // Sun: the accent. Fills only (text on it: accent-900 / on-sun); never as text on light grounds.
        accent: {
          50: '#FEF8EC',
          100: '#FEF1D8',
          200: '#FCDFA6',
          300: '#FACB6E',
          400: '#F7B546',
          500: '#F5A524',
          600: '#D9860B',
          700: '#B46808',
          800: '#8A5300',
          900: '#6B4104',
          950: '#2B1B00',
          DEFAULT: '#F5A524',
          hover: '#F7B546',
          ring: '#F5A524',
        },
        sun: {
          DEFAULT: '#F5A524',
          soft: '#FEF1D8',
          ink: '#8A5300',
          on: '#2B1B00',
        },
        // Coral: deals only (sale prices, discount badges, flash-sale buttons).
        coral: {
          DEFAULT: '#B83C1B',
          soft: '#FEEFEA',
          hover: '#9E3216',
        },
        // Reef: ink and the footer.
        reef: {
          DEFAULT: '#0F2A3A',
          night: '#0A1820',
        },
        // Neutrals tinted toward reef so text and borders sit with the brand.
        gray: {
          50: '#F5F8F7',
          100: '#EAF0EF',
          200: '#D5E1DF',
          300: '#B6C6C6',
          400: '#8FA3A7',
          500: '#5F7680',
          600: '#4A6270',
          700: '#36505D',
          800: '#213C4A',
          900: '#0F2A3A',
          950: '#081A25',
        },
        success: {
          50: '#EDF7F0',
          100: '#D3EDDB',
          500: '#2E9950',
          600: '#1E7B3C',
          700: '#186331',
          DEFAULT: '#1E7B3C',
          hover: '#186331',
          ring: '#1E7B3C',
        },
        warning: {
          50: '#FEF8EC',
          100: '#FEF1D8',
          500: '#F5A524',
          600: '#D9860B',
          700: '#8A5300',
          DEFAULT: '#F5A524',
          hover: '#D9860B',
          ring: '#F5A524',
        },
        danger: {
          50: '#FDEDEE',
          100: '#FAD6D9',
          500: '#D0313D',
          600: '#B42330',
          700: '#951B27',
          DEFAULT: '#B42330',
          hover: '#951B27',
          ring: '#B42330',
        },

        // Surfaces
        surface: {
          background: '#FFFFFF',
          secondary: '#F5F8F7',
          muted: '#EAF0EF',
          border: '#D5E1DF',
          text: {
            primary: '#0F2A3A',
            secondary: '#4A6270',
            muted: '#5F7680',
            inverse: '#FFFFFF',
          },
        },

        // Legacy names used across the views
        background: '#F5F8F7',
        dark: '#0F2A3A',
        footer: '#0F2A3A',
      },

      fontFamily: {
        sans: ['Figtree', 'ui-sans-serif', 'system-ui', '-apple-system', 'Segoe UI', 'sans-serif'],
        display: ['"Bricolage Grotesque"', 'Figtree', 'ui-sans-serif', 'system-ui', 'sans-serif'],
        thaana: ['"Noto Sans Thaana"', '"MV Faseyha"', 'Faruma', 'sans-serif'],
      },
      
      animation: {
        'fade-in': 'fadeIn 0.5s ease-in-out',
        'slide-up': 'slideUp 0.3s ease-out',
        'bounce-gentle': 'bounceGentle 2s infinite',
      },
      
      keyframes: {
        fadeIn: {
          '0%': { opacity: '0' },
          '100%': { opacity: '1' },
        },
        slideUp: {
          '0%': { transform: 'translateY(10px)', opacity: '0' },
          '100%': { transform: 'translateY(0)', opacity: '1' },
        },
        bounceGentle: {
          '0%, 100%': { transform: 'translateY(0)' },
          '50%': { transform: 'translateY(-5px)' },
        },
      },
    },
  },
  plugins: [],
} 