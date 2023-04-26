/* eslint-disable default-param-last */
/* eslint-disable global-require */
/* eslint-disable @typescript-eslint/no-var-requires */
/* eslint-disable no-param-reassign */

import fs from 'fs';
import path from 'path';

import Docker from 'dockerode';
import { addCucumberPreprocessorPlugin } from '@badeball/cypress-cucumber-preprocessor';
import webpackPreprocessor from '@cypress/webpack-preprocessor';

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

  interface CopyContainerLogFileContentProps {
    destination: string;
    name: string;
    source: string;
  }

  interface ExecInContainerProps {
    command: string;
    name: string;
  }

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
    copyContainerLogFileContent: async ({
      destination,
      name,
      source
    }: CopyContainerLogFileContentProps) => {
      const container = await docker.getContainer(name);

      const exec = await container.exec({
        AttachStderr: true,
        AttachStdout: true,
        Cmd: ['bash', '-c', `tail -n +1 ${source}`]
      });

      await new Promise((resolve, reject) => {
        exec.start({}, (err, stream) => {
          if (err) {
            reject(err);
          }

          if (stream) {
            stream.setEncoding('utf-8');
            stream.on('data', (data) => {
              fs.mkdirSync(path.dirname(destination), { recursive: true });
              fs.writeFileSync(destination, data);
              resolve(data);
            });
          }
        });
      });

      return null;
    },
    execInContainer: async ({ command, name }: ExecInContainerProps) => {
      const container = await docker.getContainer(name);

      const exec = await container.exec({
        AttachStderr: true,
        AttachStdout: true,
        Cmd: ['bash', '-c', command]
      });

      await new Promise((resolve, reject) => {
        exec.start({}, (err, stream) => {
          if (err) {
            reject(err);
          }

          if (stream) {
            stream.setEncoding('utf-8');
            stream.on('end', resolve);
          }
        });
      });

      return null;
    },
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
