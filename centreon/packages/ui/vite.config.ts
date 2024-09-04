import path from 'path';

import react from '@vitejs/plugin-react-swc';
import smvp from 'speed-measure-vite-plugin';
import { defineConfig } from 'vite';
import istanbul from 'vite-plugin-istanbul';
import svgr from 'vite-plugin-svgr';

export default defineConfig({
  base: '/',
  build: {
    lib: {
      entry: path.resolve(__dirname, 'src/index.ts'),
      fileName: (format) => `index.${format}.js`,
      formats: ['es']
    },
    rollupOptions: {
      external: ['react', 'react-dom', '@emotion/react', '@emotion/styled'],
      output: {
        globals: {
          react: 'React'
        }
      }
    },
    sourcemap: true
  },
  define: {
    'process.env.DEBUG_PRINT_LIMIT': 10000
  },
  esbuild: {},
  plugins: smvp([
    svgr({
      svgrOptions: {
        icon: true,
        memo: true,
        svgo: true,
        svgoConfig: {
          plugins: [
            {
              prefixIds: true
            }
          ]
        }
      }
    }),
    react(),
    istanbul({
      cypress: true,
      exclude: ['node_modules', 'cypress/**/*.*', '**/*.js'],
      extension: ['.ts', '.tsx'],
      include: 'src/*',
      requireEnv: false
    })
  ]),
  root: '.'
});
