const webpackConfig = require('.');

webpackConfig.optimization = {
  splitChunks: {
    cacheGroups: {
      commons: {
        name: 'commons',
        filename: '[name].[chunkhash:8].js',
        chunks: 'initial',
        minChunks: 2
      },
      vendor: {
        test: /node_modules/,
        chunks: 'initial',
        name: 'vendor',
        filename: '[name].[chunkhash:8].js',
        priority: 10,
        enforce: true
      }
    }
  }
};

module.exports = webpackConfig;