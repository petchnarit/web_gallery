import { defineConfig } from 'vite'

export default defineConfig({
  root: 'resources',
  base: '/gallery/build/',
  build: {
    outDir: '../public/build',
    manifest: 'manifest.json',
    minify: 'esbuild',
    cssCodeSplit: true,
    emptyOutDir: true,
    rollupOptions: {
      input: {
        app:'resources/js/app.js',
        style:'resources/css/app.css',
        gallery:'resources/js/gallery.js',
        style1:'resources/css/gallery.css',
        view:'resources/js/view.js',
        style2:'resources/css/view.css'
      }
    }
  }
})


