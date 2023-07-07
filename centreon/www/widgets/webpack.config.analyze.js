const { merge } = require('webpack-merge');
const { BundleAnalyzerPlugin } = require('webpack-bundle-analyzer');

const baseConfig = require('./webpack.config');

module.exports = ({ widgetName }) =>
  merge(baseConfig({ widgetName }), {
    plugins: [new BundleAnalyzerPlugin()]
  });
