import { build } from 'vite'
import react from '@vitejs/plugin-react'
import laravel from 'laravel-vite-plugin'
import path from 'node:path'
import { fileURLToPath } from 'node:url'

const __dirname = path.dirname(fileURLToPath(import.meta.url))
const PROJECT_ROOT = '/Applications/XAMPP/xamppfiles/htdocs/SANJUMANJU_INERTIA'

try {
  await build({
    root: PROJECT_ROOT,
    configFile: false,
    plugins: [
      laravel({
        input: [
          path.resolve(PROJECT_ROOT, 'resources/js/app.tsx'),
          path.resolve(PROJECT_ROOT, 'resources/css/app.css'),
        ],
        refresh: true,
      }),
      react(),
    ],
    resolve: {
      modules: [
        path.resolve('/Applications/XAMPP/xamppfiles/htdocs/SANJUMANJU/frontend/node_modules'),
        path.resolve('/Applications/XAMPP/xamppfiles/htdocs/CadMax_new/node_modules'),
        'node_modules',
      ],
      alias: {
        '@': path.resolve(PROJECT_ROOT, 'resources/js'),
        'react': path.resolve('/Applications/XAMPP/xamppfiles/htdocs/SANJUMANJU/frontend/node_modules/react'),
        'react-dom': path.resolve('/Applications/XAMPP/xamppfiles/htdocs/SANJUMANJU/frontend/node_modules/react-dom'),
        'react/jsx-runtime': path.resolve('/Applications/XAMPP/xamppfiles/htdocs/SANJUMANJU/frontend/node_modules/react/jsx-runtime.js'),
        'react/jsx-dev-runtime': path.resolve('/Applications/XAMPP/xamppfiles/htdocs/SANJUMANJU/frontend/node_modules/react/jsx-dev-runtime.js'),
        '@inertiajs/react': path.resolve('/Applications/XAMPP/xamppfiles/htdocs/CadMax_new/node_modules/@inertiajs/react'),
      },
    },
    css: {
      postcss: {
        plugins: [
          (await import('tailwindcss')).default({
            config: path.resolve(PROJECT_ROOT, 'tailwind.config.js'),
          }),
          (await import('autoprefixer')).default(),
        ],
      },
    },
    build: {
      outDir: '/tmp/sanjumanju-build-output',
      emptyOutDir: true,
    },
  })
  console.log('Build succeeded.')
} catch (e) {
  console.error('Build failed:', e?.message ?? e)
  process.exit(1)
}
