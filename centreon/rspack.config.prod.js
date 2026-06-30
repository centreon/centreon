const { merge } = require('webpack-merge');
const { RsdoctorRspackPlugin } = require('@rsdoctor/rspack-plugin');

const getBaseConfiguration = require('./rspack.config');

const analyzePlugins =
  process.env.RSDOCTOR === 'true' ? [new RsdoctorRspackPlugin()] : [];

module.exports = merge(getBaseConfiguration(), {
  devtool: 'source-map',
  optimization: {
    runtimeChunk: true
  },
  plugins: analyzePlugins
});
