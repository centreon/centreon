const path = require('path');

const { merge } = require('webpack-merge');

const frontendBase = require('@centreon/js-config/webpack/base');
const frontendModulePatch = require('@centreon/js-config/webpack/patch/module');

module.exports = ({ widgetName }) => {
  const baseOutputPath = path.resolve(`${__dirname}/${widgetName}/static`);
  const module = frontendModulePatch({
    assetPublicPath: `./src/${widgetName}/static/`,
    outputPath: baseOutputPath
  });

  return merge(
    frontendBase({
      moduleFederationConfig: {
        exposes: {
          [`./${widgetName}`]: `./src/${widgetName}/src/index`
        }
      },
      moduleName: `widget${widgetName}`
    }),
    module,
    {
      entry: {
        [`./src/${widgetName}/src/index`]: `./src/${widgetName}/src/index.tsx`
      }
    }
  );
};
