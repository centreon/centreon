/* eslint-disable default-param-last */
/* eslint-disable global-require */
/* eslint-disable @typescript-eslint/no-var-requires */
/* eslint-disable no-param-reassign */

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
    }

    launchOptions.args.push(`--window-size=${width},${height}`);

    return launchOptions;
  });

  return config;
};
