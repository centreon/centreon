const { merge } = require('webpack-merge');

const baseConfig = require('./webpack.config');

module.exports = ({ widgetName }) =>
  merge(baseConfig({ widgetName }), {
    performance: {
      assetFilter: (assetFilename) => assetFilename.endsWith('.js'),
      hints: 'error',
      maxAssetSize: 900000,
      maxEntrypointSize: 1100000
    }
  });
