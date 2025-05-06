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

      launchOptions.args.push(`--window-size=${width},${height}`);

      // force screen to be non-retina and just use our given resolution
      launchOptions.args.push('--force-device-scale-factor=1');
    }

    return launchOptions;
  });

  on('after:run', (results) => {
    const testRetries: { [key: string]: number } = {};
    if ('runs' in results) {
      results.runs.forEach((run) => {
        run.tests.forEach((test) => {
          if (test.attempts && test.attempts.length > 1 && test.state === 'passed') {
            const testTitle = test.title.join(' > '); // Convert the array to a string
            testRetries[testTitle] = test.attempts.length - 1;
          }
        });
      });
    }

    // Save the testRetries object to a file in the e2e/results directory
    const resultFilePath = path.join(
      __dirname,
      'results',
      'retries.json'
    );

    fs.writeFileSync(resultFilePath, JSON.stringify(testRetries, null, 2));
  });

  return config;
};
