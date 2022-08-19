const { merge } = require('webpack-merge');

const baseConfig = require('./webpack.config');

module.exports = merge(baseConfig, {
  optimization: {
    runtimeChunk: true,
  },
  performance: {
    assetFilter: (assetFilename) => assetFilename.endsWith('.js'),
    hints: 'error',
    maxAssetSize: 2250000,
    maxEntrypointSize: 2500000,
  },
});
