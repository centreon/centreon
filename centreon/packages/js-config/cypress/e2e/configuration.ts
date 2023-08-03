/* eslint-disable import/extensions */
/* eslint-disable import/no-unresolved */

import { execSync } from 'child_process';

import { defineConfig } from 'cypress';
import createBundler from '@bahmutov/cypress-esbuild-preprocessor';
import Docker from 'dockerode';
import { addCucumberPreprocessorPlugin } from '@badeball/cypress-cucumber-preprocessor';
import webpackPreprocessor from '@cypress/webpack-preprocessor';
import createEsbuildPlugin from '@badeball/cypress-cucumber-preprocessor/esbuild';
import cypressOnFix from 'cypress-on-fix';

const docker = new Docker();

export const setupNodeEvents = async (
  cypressOn: Cypress.PluginEvents,
  config: Cypress.PluginConfigOptions
): Promise<Cypress.PluginConfigOptions> => {
  const on = cypressOnFix(cypressOn);

  await addCucumberPreprocessorPlugin(on, config);

  on(
    'file:preprocessor',
    createBundler({
      plugins: [createEsbuildPlugin(config)]
    })
  );

  // on(
  //   'file:preprocessor',
  //   webpackPreprocessor({
  //     webpackOptions: {
  //       module: {
  //         rules: [
  //           {
  //             exclude: [/node_modules/],
  //             test: /\.ts?$/,
  //             use: [
  //               {
  //                 loader: 'swc-loader'
  //               }
  //             ]
  //           },
  //           {
  //             test: /\.feature$/,
  //             use: [
  //               {
  //                 loader: '@badeball/cypress-cucumber-preprocessor/webpack',
  //                 options: config
  //               }
  //             ]
  //           }
  //         ]
  //       },
  //       resolve: {
  //         extensions: ['.ts', '.js']
  //       }
  //     }
  //   })
  // );

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
      console.log(`Starting container ${image}`);

      const webContainers = await docker.listContainers({
        all: true,
        filters: { name: [name] }
      });
      if (webContainers.length) {
        console.log(
          `Container ${image} already started : baseUrl has probably changed and reloaded the test`
        );

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

      console.log(`Container ${image} started`);

      return container;
    },
    stopContainer: async ({ name }: StopContainerProps) => {
      const container = await docker.getContainer(name);

      console.log(`Stopping container ${name}`);

      await container.kill();
      await container.remove();

      console.log(`Container ${name} killed and removed`);

      return null;
    }
  });

  return config;
};

interface ConfigurationOptions {
  cypressFolder?: string;
  dockerName?: string;
  env?: Record<string, unknown>;
  isDevelopment?: boolean;
  specPattern: string;
}

export default ({
  specPattern,
  cypressFolder,
  isDevelopment,
  dockerName,
  env
}: ConfigurationOptions): Cypress.ConfigOptions => {
  const resultsFolder = `${cypressFolder || 'cypress'}/results${
    isDevelopment ? '/dev' : ''
  }`;

  const webImageVersion = execSync('git rev-parse --abbrev-ref HEAD')
    .toString('utf8')
    .replace(/[\n\r\s]+$/, '');

  return defineConfig({
    chromeWebSecurity: false,
    defaultCommandTimeout: 6000,
    e2e: {
      excludeSpecPattern: ['*.js', '*.ts', '*.md'],
      reporter: require.resolve(
        '@badeball/cypress-cucumber-preprocessor/pretty-reporter'
      ),
      setupNodeEvents,
      specPattern
    },
    env: {
      ...env,
      OPENID_IMAGE_VERSION: '23.04',
      WEB_IMAGE_OS: 'alma9',
      WEB_IMAGE_VERSION: webImageVersion,
      dockerName: dockerName || 'centreon-dev'
    },
    execTimeout: 60000,
    reporter: require.resolve('cypress-multi-reporters'),
    /*
    reporter: 'mochawesome',
    reporterOptions: {
      html: false,
      json: true,
      overwrite: true,
      reportDir: `${resultsFolder}/reports`,
      reportFilename: '[name]-report.json'
    },
    */
    requestTimeout: 10000,
    retries: 0,
    screenshotsFolder: `${resultsFolder}/screenshots`,
    video: true,
    videosFolder: `${resultsFolder}/videos`
  });
};
