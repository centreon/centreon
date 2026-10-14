const path = require('path');

const rspack = require('@rspack/core');

const {
  getModuleConfiguration
} = require('./packages/js-config/rspack/base/globalConfig');
const {
  getDevConfiguration
} = require('./packages/js-config/rspack/patch/dev');
const {
  devServerPlugins
} = require('./packages/js-config/rspack/patch/devServer');

const galleryPort = Number(process.env.GALLERY_PORT || 9093);
const galleryPublicPath = '/playwright/gallery/';

// Standalone dev server for the Playwright story gallery. It reuses the loaders
// of the app (swc, svgr, tailwind/postcss) but not its entry, module federation
// or asset budget, so stories render exactly as the app renders them.
module.exports = {
  ...getDevConfiguration(),
  devServer: {
    compress: true,
    hot: true,
    port: galleryPort,
    static: [
      {
        directory: path.resolve(__dirname, 'www/front_src/public'),
        publicPath: '/'
      }
    ]
  },
  devtool: false,
  entry: { gallery: './playwright/gallery/index.tsx' },
  experiments: { css: true },
  infrastructureLogging: { level: 'error' },
  mode: 'development',
  module: {
    ...getModuleConfiguration(false, './www/front_src/src'),
    generator: {
      'css/auto': { exportsOnly: false },
      'css/module': { exportsOnly: false }
    },
    parser: {
      'css/auto': { namedExports: false },
      'css/module': { namedExports: false }
    }
  },
  output: {
    filename: '[name].js',
    path: path.resolve(__dirname, 'www/static/playwright-gallery'),
    publicPath: galleryPublicPath
  },
  plugins: [
    ...devServerPlugins,
    new rspack.ProvidePlugin({
      process: require.resolve('process/browser'),
      React: 'react'
    }),
    new rspack.HtmlRspackPlugin({
      filename: 'index.html',
      publicPath: galleryPublicPath,
      template: './playwright/gallery/index.html'
    })
  ],
  resolve: {
    alias: {
      '@centreon/ui/fonts': path.resolve(
        './node_modules/@centreon/ui/public/fonts'
      ),
      '@mui/material': path.resolve('./node_modules/@mui/material'),
      dayjs: path.resolve('./node_modules/dayjs'),
      react: path.resolve('./node_modules/react')
    },
    extensions: ['.js', '.jsx', '.ts', '.tsx'],
    modules: [path.resolve(__dirname, '.'), 'node_modules']
  }
};
