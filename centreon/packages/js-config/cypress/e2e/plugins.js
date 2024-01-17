"use strict";
/* eslint-disable default-param-last */
/* eslint-disable global-require */
/* eslint-disable @typescript-eslint/no-var-requires */
/* eslint-disable no-param-reassign */
Object.defineProperty(exports, "__esModule", { value: true });
exports.default = (function (on, config) {
    on('before:browser:launch', function (browser, launchOptions) {
        var width = 1920;
        var height = 1080;
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
            launchOptions.args.push("--window-size=".concat(width, ",").concat(height));
            // force screen to be non-retina and just use our given resolution
            launchOptions.args.push('--force-device-scale-factor=1');
        }
        return launchOptions;
    });
    return config;
});
