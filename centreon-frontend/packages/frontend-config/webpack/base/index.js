const { CleanWebpackPlugin } = require('clean-webpack-plugin');
const ForkTsCheckerWebpackPlugin = require('fork-ts-checker-webpack-plugin');
const path = require('path');

module.exports = {
  resolve: {
    extensions: ['.js', '.jsx', '.ts', '.tsx'],
    alias: {
      '@centreon/ui': path.resolve(
        './node_modules/@centreon/centreon-frontend/packages/centreon-ui',
      ),
      '@centreon/ui-context': path.resolve(
        './node_modules/@centreon/centreon-frontend/packages/ui-context',
      ),
      react: path.resolve('./node_modules/react'),
    },
  },
  output: {
    libraryTarget: 'umd',
    umdNamedDefine: true,
    filename: '[name].[chunkhash:8].js',
    chunkFilename: '[name].[chunkhash:8].chunk.js',
  },
  optimization: {
    splitChunks: {
      chunks: 'all',
      cacheGroups: {
        commons: {
          name: 'commons',
          filename: '[name].[chunkhash:8].js',
          chunks: 'initial',
          minChunks: 2,
        },
        vendor: {
          test: /node_modules/,
          chunks: 'initial',
          name: 'vendor',
          filename: '[name].[chunkhash:8].js',
          priority: 10,
          enforce: true,
        },
      },
    },
  },
  plugins: [
    new CleanWebpackPlugin(),
    new ForkTsCheckerWebpackPlugin(),
  ],
  module: {
    rules: [
      {
        test: /\.(j|t)sx?$/,
        exclude: /node_modules(\\|\/)(?!(@centreon(\\|\/)centreon-frontend(\\|\/)packages(\\|\/)(ui-context|centreon-ui)))/,
        use: [
          { loader: 'cache-loader' },
          'babel-loader',
          {
            loader: 'ts-loader',
            options: {
              allowTsInNodeModules: true,
              transpileOnly: true,
            }
          },
        ],
      },
    ],
  },
};