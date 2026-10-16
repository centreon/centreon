const path = require('path');

const gallery = require('./rspack.config.gallery');
const {
  devServerPlugins
} = require('./packages/js-config/rspack/patch/devServer');

// Static, minified build of the story gallery for full runs (CI): a mount
// parses ~7 MB instead of the dev server's 24 MB. NODE_ENV stays development
// so dev-only test hooks, such as the MUI icons' data-testid, remain.
module.exports = {
  ...gallery,
  devServer: undefined,
  mode: 'production',
  optimization: { nodeEnv: 'development' },
  output: {
    ...gallery.output,
    // One directory per port: parallel runs must not rebuild each other's.
    path: path.resolve(
      __dirname,
      `www/static/playwright-gallery-build-${process.env.GALLERY_PORT || 9093}/playwright/gallery`
    )
  },
  plugins: gallery.plugins.filter(
    (plugin) => !devServerPlugins.includes(plugin)
  )
};
