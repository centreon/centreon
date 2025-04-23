const { merge } = require('webpack-merge');

const getBaseConfiguration = require('./rspack.config');

module.exports = merge(getBaseConfiguration(), {
  optimization: {
    runtimeChunk: true
  }
});
