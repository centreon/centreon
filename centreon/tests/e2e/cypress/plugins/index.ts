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

  on('before:browser:launch', (launchOptions) => {
    launchOptions.args = launchOptions.args.filter(
      (item) => item !== '--disable-dev-shm-usage',
    );

    return launchOptions;
  });
};
