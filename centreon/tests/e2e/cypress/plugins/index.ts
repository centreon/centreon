/* eslint-disable no-param-reassign */
/* eslint-disable default-param-last */
/* eslint-disable global-require */
/* eslint-disable @typescript-eslint/no-var-requires */

const webpackPreprocessor = require('@cypress/webpack-preprocessor');

module.exports = (on): void => {
  const options = {
    webpackOptions: require('../webpack.config'),
  };
  on('file:preprocessor', webpackPreprocessor(options));

  on('before:browser:launch', (browser = {}, launchOptions) => {
    if ((browser as { name }).name === 'chrome') {
      launchOptions.args.push('--disable-gpu');
      launchOptions.args.push('--disable-software-rasterizer');
      launchOptions.args = launchOptions.args.filter(
        (element) => element !== '--disable-dev-shm-usage',
      );
    }

    return launchOptions;
  });
};
