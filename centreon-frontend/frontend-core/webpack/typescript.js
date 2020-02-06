const merge = require('webpack-merge');

const webpackConfig = require('.');

module.exports = merge(webpackConfig, {
  resolve: {
    extensions: ['.js', '.jsx', '.ts', '.tsx'],
  },
  module: {
    rules: [
      {
        test: /\.tsx?$/,
        exclude: /node_modules/,
        use: ['babel-loader', 'awesome-typescript-loader'],
      },
    ],
  },
});
