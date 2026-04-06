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
          300: '#94ad5e',
          400: '#4ade80',
          500: '#22c55e',
          600: '#314d2a',
          700: '#1a2a18',
          800: '#166534',
          900: '#14532d',
        },
      },
    },
  },
  plugins: [],
}
