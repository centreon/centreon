import path from 'path';

import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react-swc';
import smvp from 'speed-measure-vite-plugin';
import svgr from 'vite-plugin-svgr';

export default defineConfig({
  base: '/',
  // TODO distribute a build / lib instead of source (including font assets)
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
    react()
  ]),
  resolve: {
    alias: {
      '@centreon/ui/fonts': path.resolve(__dirname, '/fonts')
    }
  },
  root: '.'
});
