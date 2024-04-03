const fs = require('fs');

module.exports = class WriteRemoEntryNameToModuleFederation {
  constructor(federatedComponentConfiguration) {
    this.federatedComponentConfiguration = federatedComponentConfiguration;
  }

  apply(compiler) {
    compiler.hooks.done.tap('CentreonModulePlugin', (stats) => {
      const newFederatedComponentConfiguration = {
        ...this.federatedComponentConfiguration,
        remoteEntry: Object.keys(stats.compilation.assets).find((assetName) =>
          assetName.match(/(^remoteEntry)\S+.js$/)
        )
      };

      if (!fs.existsSync(compiler.options.output.path)) {
        fs.mkdirSync(compiler.options.output.path, { recursive: true });
      }

      fs.writeFileSync(
        `${compiler.options.output.path}/moduleFederation.json`,
        JSON.stringify(newFederatedComponentConfiguration, null, 2)
      );
    });
  }
};
