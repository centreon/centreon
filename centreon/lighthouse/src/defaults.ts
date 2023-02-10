export const baseUrl = 'http://localhost:4000/centreon/';

export const baseConfig = {
  formFactor: 'desktop',
  screenEmulation: {
    deviceScaleFactor: 1,
    disabled: false,
    height: 720,
    mobile: false,
    width: 1280,
  },
  throttling: {
    cpuSlowdownMultiplier: 4,
    downloadThroughputKbps: 768000,
    requestLatencyMs: 150,
  },
};
