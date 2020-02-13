module.exports = {
  devtool: 'cheap-module-eval-source-map',

  optimization: {
    splitChunks: false,
  },  
  output: {
    filename: '[name].js',
  },
};
