const { merge } = require('webpack-merge');

const {
  getDevConfiguration
} = require('@centreon/js-config/webpack/patch/dev');

const baseConfig = require('./webpack.config');

module.exports = ({ widgetName }) =>
  merge(baseConfig({ widgetName }), getDevConfiguration());
