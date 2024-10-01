const path = require('path');

const rspack = require('@rspack/core');
const { merge } = require('webpack-merge');

const getBaseConfiguration = require('./packages/js-config/rspack/base');
const {
  publicPath,
  isDevelopmentMode
} = require('./packages/js-config/rspack/patch/devServer');

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
      performance: {
        maxAssetSize: 300000,
        maxEntrypointSize: 300000
      },
      plugins: [
        new rspack.ProvidePlugin({
          React: 'react',
          process: require.resolve('process/browser')
        }),
        new rspack.HtmlRspackPlugin({
          filename: path.resolve(`${__dirname}`, 'www', 'index.html'),
          publicPath: isDevelopmentMode ? publicPath : './static/',
          template: './www/front_src/public/index.html'
        }),
        new rspack.IgnorePlugin({
          resourceRegExp: enableCoverage
            ? /.(js.map|chunk.css|chunk.js)/
            : /www\/widgets/
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
