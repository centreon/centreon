/* eslint-disable default-param-last */
/* eslint-disable global-require */
/* eslint-disable @typescript-eslint/no-var-requires */
/* eslint-disable no-param-reassign */

import Docker from 'dockerode';
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

  on('task', {
    async startContainer({ name, os = 'alma9', useSlim = true, version }) {
      const slimSuffix = useSlim ? '-slim' : '';

      const docker = new Docker();

      const webContainers = await docker.listContainers({all: true, filters: {name: [name]}});
      if (webContainers.length) {
        return webContainers[0];
      }

      const container = await docker.createContainer({
        AttachStderr: true,
        AttachStdin: false,
        AttachStdout: true,
        //Cmd: [`ls`],
        ExposedPorts: {
          '80/tcp': {},
        },
        HostConfig: {
          PortBindings: {
            '80/tcp': [
              {
                HostIP: '0.0.0.0',
                HostPort: '4000',
              },
            ]
          },
        },
        Image: `docker.centreon.com/centreon/centreon-web${slimSuffix}-${os}:${version}`,
        OpenStdin: false,
        StdinOnce: false,
        Tty: true,
        name: name,
      });

      await container.start();

      return container;
    }
  });

  return config;
};
