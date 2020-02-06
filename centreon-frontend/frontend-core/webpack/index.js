module.exports = {
  context: __dirname,
  resolve: {
    extensions: ['.js', '.jsx'],
  },
  output: {
    libraryTarget: 'umd',
    umdNamedDefined: true,
  },
  optimization: {
    splitChunks: {
      chunks: 'all',
    },
  },
  plugins: [],
  module: {
    rules: [
      {
        test: /\.jsx?$/,
        exclude: /node_modules/,
        use: [{ loader: 'babel-loader' }],
      },
    ],
  },
};