const fs = require('fs');

export class WriteRemoEntryNameToModuleFederation {
  constructor(federatedComponentConfiguration, preloadScriptFileName) {
    this.federatedComponentConfiguration = federatedComponentConfiguration;
    this.preloadScriptFileName = preloadScriptFileName;
  }

  apply(compiler) {
    compiler.hooks.done.tap('WriteRemoEntryNameToModuleFederation', (stats) => {
      const newFederatedComponentConfiguration = {
        ...this.federatedComponentConfiguration,
        preloadScript: Object.keys(stats.compilation.assets).find((assetName) =>
          assetName.match(new RegExp(`(^${this.preloadScriptFileName})\\S+.js`))
        ),
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
}
