/* eslint-disable default-param-last */
/* eslint-disable global-require */
/* eslint-disable @typescript-eslint/no-var-requires */
/* eslint-disable no-param-reassign */

import fs from 'fs';
import path from 'path';

export default (
  on: Cypress.PluginEvents,
  config: Cypress.PluginConfigOptions
): Cypress.PluginConfigOptions => {
  on('before:browser:launch', (browser, launchOptions) => {
    const width = 1920;
    const height = 1080;

    if (browser.family === 'chromium' && browser.name !== 'electron') {
      if (browser.isHeadless) {
        launchOptions.args.push('--headless=new');
      }

      // flags description : https://github.com/GoogleChrome/chrome-launcher/blob/main/docs/chrome-flags-for-tools.md
      launchOptions.args.push('--disable-gpu');
      launchOptions.args.push('--auto-open-devtools-for-tabs');
      launchOptions.args.push('--disable-extensions');
      launchOptions.args.push('--hide-scrollbars');
      launchOptions.args.push('--mute-audio');

      // force screen to be non-retina and just use our given resolution
      launchOptions.args.push('--force-device-scale-factor=1');

      launchOptions.args.push(`--window-size=${width},${height}`);
    }

    return launchOptions;
  });

  on('after:run', (results) => {
    const testRetries: { [key: string]: boolean } = {};
    if ('runs' in results) {
      results.runs.forEach((run) => {
        run.tests.forEach((test) => {
          if (test.attempts && test.attempts.length > 1) {
            const testTitle = test.title.join(' > '); // Convert the array to a string
            testRetries[testTitle] = true;
          }
        });
      });
    }

    console.log('After run results:', results);
    console.log('Test retries:', testRetries);

    // Save the testRetries object to a file in the e2e/results directory
    const resultFilePath = path.join(
      __dirname,
      '../../../../tests/e2e/results',
      'hasRetries.json'
    );
    if (results.totalFailed > 0) {
      fs.writeFileSync(resultFilePath, '{}');
    } else if (Object.keys(testRetries).length > 0) {
      // If tests succeeded but there were retries, write the retries to the file
      fs.writeFileSync(resultFilePath, JSON.stringify(testRetries, null, 2));
    } else {
      // If no retries, empty the file
      fs.writeFileSync(resultFilePath, '{}');
    }
  });

  return config;
};