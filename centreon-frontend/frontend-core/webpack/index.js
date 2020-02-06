module.exports = {
  resolve: {
    extensions: ['.js', '.jsx'],
  },
  output: {
    libraryTarget: 'umd',
    umdNamedDefine: true,
  },
  optimization: {
    splitChunks: {
      chunks: 'all',
    },
  },
  module: {
    rules: [
      {
        test: /\.jsx?$/,
        exclude: /node_modules/,
        use: ['babel-loader'],
      },
    ],
  },
};