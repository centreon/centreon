const merge = require('webpack-merge');
const ForkTsCheckerWebpackPlugin = require('fork-ts-checker-webpack-plugin');

const webpackConfig = require('.');

module.exports = merge(webpackConfig, {
  resolve: {
    extensions: ['.ts', '.tsx'],
  },
  module: {
    rules: [
      {
        test: /\.tsx?$/,
        exclude: /node_modules(\\|\/)(?!(@centreon(\\|\/)ui))/,
        use: [
          'babel-loader',
          {
            loader: 'ts-loader',
            options: {
              allowTsInNodeModules: true,
              transpileOnly: true,
              experimentalWatchApi: true,
            }
          },
        ],
      },
    ],
  },
  plugins: [
    new ForkTsCheckerWebpackPlugin({
      useTypescriptIncrementalApi: true,
      checkSyntacticErrors: true,
    }),
  ],
});
