export const baseUrl = 'http://172.19.0.20:80/centreon/';

const screenEmulation = {
  deviceScaleFactor: 1,
  disabled: false,
  height: 720,
  mobile: false,
  width: 1280,
};

export const baseConfigContext = {
  settingsOverrides: {
    formFactor: 'desktop',
    screenEmulation,
  },
};
