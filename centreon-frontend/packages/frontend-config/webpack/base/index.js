const { CleanWebpackPlugin } = require('clean-webpack-plugin');
const path = require('path');

const excludeNodeModulesExceptCentreonUi =
  /node_modules(\\|\/)(?!(centreon-frontend(\\|\/)packages(\\|\/)(ui-context|centreon-ui)))/;

module.exports = (jscTransformConfiguration) => ({
  cache: false,
  module: {
    rules: [
      {
        parser: { system: false },
        test: /\.[cm]?(j|t)sx?$/,
      },
      {
        exclude: excludeNodeModulesExceptCentreonUi,
        test: /\.[jt]sx?$/,
        use: {
          loader: 'swc-loader',
          options: {
            jsc: {
              parser: {
                syntax: 'typescript',
                tsx: true,
              },
              transform: jscTransformConfiguration,
            },
          },
        },
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
  plugins: [new CleanWebpackPlugin()],
  resolve: {
    alias: {
      '@centreon/ui': path.resolve(
        './node_modules/centreon-frontend/packages/centreon-ui',
      ),
      '@centreon/ui-context': path.resolve(
        './node_modules/centreon-frontend/packages/ui-context',
      ),
      react: path.resolve('./node_modules/react'),
    },
    extensions: ['.js', '.jsx', '.ts', '.tsx'],
  },
});
