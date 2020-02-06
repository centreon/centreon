const webpackConfig = require('.');

webpackConfig.devtool = 'inline-source-map';
webpackConfig.output.filename = '[name].js';

modules.exports = webpackConfig;