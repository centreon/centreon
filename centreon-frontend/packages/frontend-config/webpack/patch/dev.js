module.exports = {
  cache: true,
  devtool: 'eval-cheap-module-source-map',
  optimization: {
    splitChunks: false,
  },
  output: {
    filename: '[name].js',
  },
  performance: {
    assetFilter: (assetFilename) => assetFilename.endsWith('.js'),
    hints: 'error',
    maxAssetSize: 2250000,
    maxEntrypointSize: 2500000,
  },
};
