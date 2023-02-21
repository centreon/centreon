const path = require('path');

const HtmlWebpackPlugin = require('html-webpack-plugin');
const HtmlWebpackHarddiskPlugin = require('html-webpack-harddisk-plugin');
const webpack = require('webpack');
const { merge } = require('webpack-merge');

const getBaseConfiguration = require('./packages/js-config/webpack/base');

module.exports = (jscTransformConfiguration) =>
  merge(
    getBaseConfiguration({ jscTransformConfiguration, moduleName: 'centreon' }),
    {
      entry: ['./www/front_src/src/index.tsx'],
      output: {
        crossOriginLoading: 'anonymous',
        library: ['name'],
        path: path.resolve(`${__dirname}/www/static`),
        publicPath: './static/'
      },
      plugins: [
        new webpack.ProvidePlugin({
          process: 'process/browser',
          React: 'react',
        }),
        new HtmlWebpackPlugin({
          alwaysWriteToDisk: true,
          filename: path.resolve(`${__dirname}`, 'www', 'index.html'),
          template: './www/front_src/public/index.html'
        }),
        new HtmlWebpackHarddiskPlugin()
      ]
    }
  );
