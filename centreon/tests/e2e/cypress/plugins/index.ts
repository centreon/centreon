/* eslint-disable default-param-last */
/* eslint-disable global-require */
/* eslint-disable @typescript-eslint/no-var-requires */
/* eslint-disable no-param-reassign */

const webpackPreprocessor = require('@cypress/webpack-preprocessor');

module.exports = (on): void => {
  const options = {
    webpackOptions: require('../webpack.config'),
  };
  on('file:preprocessor', webpackPreprocessor(options));

  on('before:browser:launch', (browser = {}, launchOptions) => {
    if ((browser as { name }).name === 'chrome') {
      launchOptions.args.push('--disable-gpu');

      /*
      if (browser.isHeadless) {
        launchOptions.args = launchOptions.args.map((arg) => {
          if (arg === '--headless') {
            return '--headless=new';
          }

          return arg;
        });
      }
      */
    }

    console.log(launchOptions.args);

    return launchOptions;
  });
};
