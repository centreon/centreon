const MiniCssExtractPlugin = require('mini-css-extract-plugin');
const { CleanWebpackPlugin } = require('clean-webpack-plugin');

module.exports = {
  resolve: {
    extensions: ['.js', '.jsx'],
  },
  output: {
    libraryTarget: 'umd',
    umdNamedDefine: true,
    filename: '[name].[hash:8].js',
    chunkFilename: '[name].[hash:8].chunk.js',
    library: '[name]',
  },
  optimization: {
    splitChunks: {
      chunks: 'all',
    },
  },
  plugins: [
    new CleanWebpackPlugin(),
  ],
  module: {
    rules: [
      {
        test: /\.jsx?$/,
        exclude: /node_modules(?!\/@centreon\/ui)/,
        use: ['babel-loader'],
      },
    ],
  }
};