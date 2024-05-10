const { merge } = require('webpack-merge');

const { getDevConfiguration } = require('@centreon/js-config/rspack/patch/dev');

const baseConfig = require('./rspack.config');

module.exports = ({ widgetName }) =>
  merge(baseConfig({ widgetName }), getDevConfiguration());
