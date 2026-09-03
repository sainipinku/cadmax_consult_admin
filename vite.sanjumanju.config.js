import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import laravel from 'laravel-vite-plugin'
import path from 'node:path'
import { fileURLToPath } from 'node:url'

const __dirname = path.dirname(fileURLToPath(import.meta.url))
const PROJECT_ROOT = '/Applications/XAMPP/xamppfiles/htdocs/SANJUMANJU_INERTIA'

export default defineConfig({
  root: PROJECT_ROOT,
  plugins: [
    laravel({
      input: ['resources/js/app.tsx', 'resources/css/app.css'],
      refresh: true,
    }),
    react(),
  ],
  resolve: {
    alias: {
      '@': path.resolve(PROJECT_ROOT, 'resources/js'),
    },
  },
  build: {
    outDir: path.resolve(PROJECT_ROOT, 'public/build'),
    emptyOutDir: true,
  },
})
