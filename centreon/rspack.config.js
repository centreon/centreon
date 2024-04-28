const path = require('path');

const rspack = require('@rspack/core');
const { merge } = require('webpack-merge');

const getBaseConfiguration = require('./packages/js-config/rspack/base');

module.exports = (enableCoverage = false) =>
  merge(
    getBaseConfiguration({
      enableCoverage,
      moduleName: 'centreon'
    }),
    {
      devServer: {
        devMiddleware: {
          writeToDisk: (filename) => {
            return /index.html$/.test(filename);
          }
        }
      },
      entry: ['./www/front_src/src/index.tsx'],
      output: {
        crossOriginLoading: 'anonymous',
        library: ['name'],
        path: path.resolve(`${__dirname}/www/static`),
        publicPath: './static/'
      },
      plugins: [
        new rspack.ProvidePlugin({
          React: 'react',
          process: 'process/browser'
        }),
        new rspack.HtmlRspackPlugin({
          filename: path.resolve(`${__dirname}`, 'www', 'index.html'),
          template: './www/front_src/public/index.html'
        }),
        new rspack.IgnorePlugin({
          resourceRegExp: /.(js.map|chunk.css)/
        })
      ],
      resolve: {
        alias: {
          'centreon-widgets': path.resolve(__dirname, 'www', 'widgets', 'src')
        },
        modules: [path.resolve(__dirname, '.'), 'node_modules']
      }
    }
  );
