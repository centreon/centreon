module.exports = {
  cache: true,
  devtool: 'eval-cheap-module-source-map',
  optimization: {
    splitChunks: false,
  },
  output: {
    filename: '[name].js',
  },
};
