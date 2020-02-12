module.exports = {
  devtool: 'inline-source-map',

  optimization: {
    splitChunks: false,
  },  
  output: {
    filename: '[name].js',
  },
};
