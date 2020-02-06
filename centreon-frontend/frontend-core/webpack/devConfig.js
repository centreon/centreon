const webpackConfig = require('.');

webpackConfig.devtool = 'inline-source-map';
webpackConfig.output.filename = '[name].js';

module.exports = webpackConfig;