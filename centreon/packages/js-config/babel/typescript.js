const merge = require('babel-merge');

const baseConfiguration = require('.');

const presets = ['@babel/preset-typescript'];

module.exports = merge(baseConfiguration, {
  env: {
    production: {
      presets,
    },
    development: {
      presets,
    },
    test: {
      presets,
    },
  },
});
