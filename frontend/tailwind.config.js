/** @type {import('tailwindcss').Config} */
export default {
  content: [
    "./index.html",
    "./src/**/*.{js,ts,jsx,tsx}",
  ],
  theme: {
    extend: {
      fontFamily: {
        sans: ['Josefin Sans', 'sans-serif'],
        serif: ['serif'],
      },
      colors: {
        primary: '#1C1A1A',
        accent: '#FF69B4',
        'accent-light': '#fce7f3',
      },
    },
  },
  plugins: [],
}