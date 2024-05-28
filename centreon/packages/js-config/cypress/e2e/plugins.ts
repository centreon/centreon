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

      // Flags description: https://github.com/GoogleChrome/chrome-launcher/blob/main/docs/chrome-flags-for-tools.md
      launchOptions.args.push('--disable-gpu');
      launchOptions.args.push('--auto-open-devtools-for-tabs');
      launchOptions.args.push('--disable-extensions');
      launchOptions.args.push('--hide-scrollbars');
      launchOptions.args.push('--mute-audio');

      // Force screen to be non-retina and just use our given resolution
      launchOptions.args.push('--force-device-scale-factor=1');
      launchOptions.args.push(`--window-size=${width},${height}`);

      // Open DevTools automatically
      launchOptions.preferences = launchOptions.preferences || {};
      launchOptions.preferences.devTools = true;
    } else if (browser.name === 'electron') {
      // Electron specific settings
      launchOptions.preferences = launchOptions.preferences || {};
      launchOptions.preferences.width = width;
      launchOptions.preferences.height = height;
      launchOptions.preferences.devTools = true;
    }

    console.log('Launch options:', launchOptions);

    return launchOptions;
  });

  return config;
};
