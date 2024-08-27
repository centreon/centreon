/* eslint-disable import/no-dynamic-require */
/* eslint-disable global-require */
const path = require('path');

const { merge } = require('webpack-merge');
const rspack = require('@rspack/core');

const frontendBase = require('@centreon/js-config/rspack/base');
const frontendModulePatch = require('@centreon/js-config/rspack/patch/module');

module.exports = ({ widgetName }) => {
  const baseOutputPath = path.resolve(`${__dirname}/src/${widgetName}/static`);
  const module = frontendModulePatch({
    assetPublicPath: `./src/${widgetName}/static/`,
    federatedComponentConfiguration: require(
      `./src/${widgetName}/moduleFederation.json`
    ),
    outputPath: baseOutputPath
  });

  const moduleFederationWidgetName = widgetName.split('centreon-widget-')[1];

  return merge(
    frontendBase({
      moduleFederationConfig: {
        exposes: {
          [`./${moduleFederationWidgetName}`]: `./src/${widgetName}/src/index`
        }
      },
      moduleName: `widget${moduleFederationWidgetName}`
    }),
    module,
    {
      entry: {
        [`./src/${widgetName}/src/index`]: `./src/${widgetName}/src/index.tsx`
      },
      optimization: {
        splitChunks: false
      },
      performance: {
        maxAssetSize: 1200000,
        maxEntrypointSize: 1200000
      },
      plugins: [
        new rspack.CopyRspackPlugin({
          patterns: [
            {
              from: `./src/${widgetName}/properties.json`,
              to: `./properties.json`
            }
          ]
        })
      ],
      resolve: {
        alias: {
          '@centreon/ui/fonts': path.resolve(
            '../../node_modules/@centreon/ui/public/fonts'
          )
        }
      }
    }
  );
};
