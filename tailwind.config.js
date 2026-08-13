export default {
  content: [
    "./*.html",
    "./public/**/*.html",
    "./src/**/*.{js,ts,jsx,tsx}",
  ],
  theme: {
    extend: {
      colors: {
        primary: {
          50: '#f0fdf4',
          100: '#dcfce7',
          200: '#bbf7d0',
          300: '#86efac',
          400: '#4ade80',
          500: '#16a34a',
          600: '#15803d',
          700: '#166534',
          800: '#14532d',
          900: '#0f3e23',
        },
        secondary: {
          50: '#f3f7f5',
          100: '#e2ede8',
          200: '#c5dbd1',
          300: '#9fc0b2',
          400: '#709d89',
          500: '#578672',
          600: '#4D7C68',
          700: '#3D6353',
          800: '#314f42',
          900: '#253c32',
        },
      },
    },
  },
  plugins: [],
}
