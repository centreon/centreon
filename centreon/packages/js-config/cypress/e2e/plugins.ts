/* eslint-disable default-param-last */
/* eslint-disable global-require */
/* eslint-disable @typescript-eslint/no-var-requires */
/* eslint-disable no-param-reassign */

import Docker from 'dockerode';
import { addCucumberPreprocessorPlugin } from '@badeball/cypress-cucumber-preprocessor';
import webpackPreprocessor from '@cypress/webpack-preprocessor';
import cypressFailFast from 'cypress-fail-fast/plugin';

const docker = new Docker();

const getWebpackOptions = (config): object => {
  return {
    module: {
      rules: [
        {
          exclude: [/node_modules/],
          test: /\.ts?$/,
          use: [
            {
              loader: 'swc-loader'
            }
          ]
        },
        {
          test: /\.feature$/,
          use: [
            {
              loader: '@badeball/cypress-cucumber-preprocessor/webpack',
              options: config
            }
          ]
        }
      ]
    },
    resolve: {
      extensions: ['.ts', '.js']
    }
  };
};

export default async (on, config): Promise<void> => {
  await addCucumberPreprocessorPlugin(on, config);

  await cypressFailFast(on, config);

  const webpackOptions = await getWebpackOptions(config);
  const options = {
    webpackOptions
  };

  on('file:preprocessor', webpackPreprocessor(options));

  on('before:browser:launch', (browser = {}, launchOptions) => {
    if ((browser as { name }).name === 'chrome') {
      launchOptions.args.push('--disable-gpu');
      launchOptions.args = launchOptions.args.filter(
        (element) => element !== '--disable-dev-shm-usage'
      );
    }

    return launchOptions;
  });

  interface PortBinding {
    destination: number;
    source: number;
  }

  interface StartContainerProps {
    image: string;
    name: string;
    portBindings: Array<PortBinding>;
  }

  interface StopContainerProps {
    name: string;
  }

  on('task', {
    startContainer: async ({
      image,
      name,
      portBindings = []
    }: StartContainerProps) => {
      const webContainers = await docker.listContainers({
        all: true,
        filters: { name: [name] }
      });
      if (webContainers.length) {
        return webContainers[0];
      }

      const container = await docker.createContainer({
        AttachStderr: true,
        AttachStdin: false,
        AttachStdout: true,
        ExposedPorts: portBindings.reduce((accumulator, currentValue) => {
          accumulator[`${currentValue.source}/tcp`] = {};

          return accumulator;
        }, {}),
        HostConfig: {
          PortBindings: portBindings.reduce((accumulator, currentValue) => {
            accumulator[`${currentValue.source}/tcp`] = [
              {
                HostIP: '0.0.0.0',
                HostPort: `${currentValue.destination}`
              }
            ];

            return accumulator;
          }, {})
        },
        Image: image,
        OpenStdin: false,
        StdinOnce: false,
        Tty: true,
        name
      });

      await container.start();

      return container;
    },
    stopContainer: async ({ name }: StopContainerProps) => {
      const container = await docker.getContainer(name);
      await container.kill();
      await container.remove();

      return null;
    }
  });

  return config;
};
