import path from 'path';

export default ({ command }) => ({
  build: {
    emptyOutDir: true,
    manifest: false,
    outDir: './src/web/assets/dist',
    rollupOptions: {
      input: {
        'flux': './src/web/assets/src/js/flux.js'
      },
      output: {
        sourcemap: true,
        entryFileNames: `assets/[name].js`,
        chunkFileNames: `assets/[name].js`,
        assetFileNames: `assets/[name].[ext]`
      }
    }
  },
  publicDir: './src/web/assets/public',
  resolve: {
    alias: {
      '@': path.resolve(__dirname, './src')
    },
    preserveSymlinks: true,
  },
  server: {
    origin: 'http://localhost:4000',
  },
  plugins: []
})