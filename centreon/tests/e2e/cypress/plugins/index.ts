/* eslint-disable no-param-reassign */
/* eslint-disable @typescript-eslint/ban-ts-comment */
/* eslint-disable default-param-last */
/* eslint-disable global-require */
/* eslint-disable @typescript-eslint/no-var-requires */
// @ts-nocheck

const webpackPreprocessor = require('@cypress/webpack-preprocessor');

module.exports = (on): void => {
  const options = {
    webpackOptions: require('../webpack.config'),
  };
  on('file:preprocessor', webpackPreprocessor(options));

  on('before:browser:launch', (browser = {}, launchOptions) => {
    if (browser.name === 'chrome') {
      launchOptions.args.push('--disable-gpu');
      launchOptions.args = launchOptions.args.filter(
        (Element) => Element !== '--disable-dev-shm-usage',
      );
    }

    return launchOptions;
  });
};
