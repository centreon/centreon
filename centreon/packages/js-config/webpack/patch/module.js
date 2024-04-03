const { CleanWebpackPlugin } = require('clean-webpack-plugin');

const {
  ModuleFederationIgnoreEntries
} = require('../plugins/ModuleFederationIgnoreEntries');
const {
  WriteRemoEntryNameToModuleFederation
} = require('../plugins/WriteRemoEntryNameToModuleFederation');

module.exports = ({
  outputPath,
  federatedComponentConfiguration,
  ignoreEntriesToModuleFederation,
  preloadScriptFileName
}) => ({
  output: {
    library: '[chunkhash:8]',
    path: outputPath
  },
  plugins: [
    new CleanWebpackPlugin({
      cleanOnceBeforeBuildPatterns: [`${outputPath}/**/*.js`],
      dangerouslyAllowCleanPatternsOutsideProject: true,
      dry: false
    }),
    new WriteRemoEntryNameToModuleFederation(federatedComponentConfiguration),
    ignoreEntriesToModuleFederation &&
      new ModuleFederationIgnoreEntries(
        ignoreEntriesToModuleFederation,
        preloadScriptFileName
      )
  ].filter(Boolean)
});
