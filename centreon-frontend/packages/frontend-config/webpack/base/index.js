const { CleanWebpackPlugin } = require('clean-webpack-plugin');
const ForkTsCheckerWebpackPlugin = require('fork-ts-checker-webpack-plugin');
const path = require('path');

module.exports = {
  cache: false,
  module: {
    rules: [
      {
        parser: { system: false },
        test: /\.[cm]?(j|t)sx?$/,
      },
      {
        exclude:
          /node_modules(\\|\/)(?!(@centreon(\\|\/)centreon-frontend(\\|\/)packages(\\|\/)(ui-context|centreon-ui)))/,
        test: /\.(j|t)sx?$/,
        use: [
          'babel-loader',
          {
            loader: 'ts-loader',
            options: {
              allowTsInNodeModules: true,
              transpileOnly: true,
            },
          },
        ],
      },
    ],
  },
  optimization: {
    splitChunks: {
      cacheGroups: {
        commons: {
          chunks: 'initial',
          filename: '[name].[chunkhash:8].js',
          minChunks: 2,
          name: 'commons',
        },
        vendor: {
          chunks: 'initial',
          enforce: true,
          filename: '[name].[chunkhash:8].js',
          name: 'vendor',
          priority: 10,
          test: /node_modules/,
        },
      },
      chunks: 'all',
    },
  },
  output: {
    chunkFilename: '[name].[chunkhash:8].chunk.js',
    filename: '[name].[chunkhash:8].js',
    libraryTarget: 'umd',
    umdNamedDefine: true,
  },
  plugins: [new CleanWebpackPlugin(), new ForkTsCheckerWebpackPlugin()],
  resolve: {
    alias: {
      '@centreon/ui': path.resolve(
        './node_modules/@centreon/centreon-frontend/packages/centreon-ui',
      ),
      '@centreon/ui-context': path.resolve(
        './node_modules/@centreon/centreon-frontend/packages/ui-context',
      ),
      react: path.resolve('./node_modules/react'),
    },
    extensions: ['.js', '.jsx', '.ts', '.tsx'],
  },
};
