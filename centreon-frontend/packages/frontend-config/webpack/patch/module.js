const fs = require('fs');
const { CleanWebpackPlugin } = require('clean-webpack-plugin');

class CentreonModulePlugin {
  constructor(federatedComponentConfiguration) {
    this.federatedComponentConfiguration = federatedComponentConfiguration;
  }

  apply(compiler) {
    compiler.hooks.done.tap('CentreonModulePlugin', (stats) => {
      const newFederatedComponentConfiguration = {
        ...this.federatedComponentConfiguration,
        remoteEntry: Object.keys(stats.compilation.assets).find((assetName) =>
          assetName.match(/(^remoteEntry)\S+.js$/),
        ),
      };

      if (!fs.existsSync(compiler.options.output.path)) {
        fs.mkdirSync(compiler.options.output.path, { recursive: true });
      }

      fs.writeFileSync(
        `${compiler.options.output.path}/moduleFederation.json`,
        JSON.stringify(newFederatedComponentConfiguration, null, 2),
      );
    });
  }
}

module.exports = ({
  outputPath,
  federatedComponentConfiguration,
}) => ({
  output: {
    library: '[chunkhash:8]',
    path: outputPath,
  },
  plugins: [
    new CleanWebpackPlugin({
      cleanOnceBeforeBuildPatterns: [`${outputPath}/**/*.js`],
      dangerouslyAllowCleanPatternsOutsideProject: true,
      dry: false,
    }),
    new CentreonModulePlugin(federatedComponentConfiguration),
  ],
});
