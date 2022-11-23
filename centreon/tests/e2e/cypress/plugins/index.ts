/* eslint-disable default-param-last */
/* eslint-disable global-require */
/* eslint-disable @typescript-eslint/no-var-requires */
/* eslint-disable no-param-reassign */

import { addCucumberPreprocessorPlugin } from '@badeball/cypress-cucumber-preprocessor';
import webpackPreprocessor from '@cypress/webpack-preprocessor';

const getWebpackOptions = (config): object => {
  return {
    module: {
      rules: [
        {
          exclude: [/node_modules/],
          test: /\.ts?$/,
          use: [
            {
              loader: 'swc-loader',
            },
          ],
        },
        {
          test: /\.feature$/,
          use: [
            {
              loader: '@badeball/cypress-cucumber-preprocessor/webpack',
              options: config,
            },
          ],
        },
      ],
    },
    resolve: {
      extensions: ['.ts', '.js'],
    },
  };
};

export default async (on, config): Promise<void> => {
  await addCucumberPreprocessorPlugin(on, config);

  const webpackOptions = await getWebpackOptions(config);
  const options = {
    webpackOptions,
  };

  on('file:preprocessor', webpackPreprocessor(options));

  on('before:browser:launch', (browser = {}, launchOptions) => {
    if ((browser as { name }).name === 'chrome') {
      launchOptions.args.push('--disable-gpu');
      launchOptions.args = launchOptions.args.filter(
        (element) => element !== '--disable-dev-shm-usage',
      );
    }

    return launchOptions;
  });

  return config;
};
