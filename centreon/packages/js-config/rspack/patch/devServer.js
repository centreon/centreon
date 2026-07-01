const os = require('os');

const ReactRefreshPlugin = require('@rspack/plugin-react-refresh');

const devServerPort = 9090;

const interfaces = os.networkInterfaces();
const externalInterface = Object.keys(interfaces).find(
  (interfaceName) =>
    !interfaceName.includes('docker') &&
    interfaces[interfaceName][0].family === 'IPv4' &&
    interfaces[interfaceName][0].internal === false &&
    !process.env.IS_STATIC_PORT_FORWARDED
);

const devServerAddress = 'localhost';

const publicPath = `http://${devServerAddress}:${devServerPort}/static/`;

const isDevelopmentMode = process.env.NODE_ENV !== 'production';

const devServerPlugins = isDevelopmentMode ? [new ReactRefreshPlugin()] : [];

module.exports = {
  devServer: {
    compress: true,
    hot: true,
    port: devServerPort
  },
  devServerPlugins,
  isDevelopmentMode,
  publicPath
};
