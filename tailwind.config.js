/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    "./app/Views/**/*.php",        // ไฟล์ PHP ของ CodeIgniter
    "./resources/js/**/*.js",      // ไฟล์ JS
    "./resources/css/**/*.css",    // ไฟล์ CSS
  ],
  theme: {
    extend: {
      fontFamily: {
        sans: ['"Noto Sans Thai"', 'ui-sans-serif', 'system-ui', '-apple-system', 'BlinkMacSystemFont', 'sans-serif'],
      },
    },
  },
  plugins: [],
};
