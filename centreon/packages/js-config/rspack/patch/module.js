const WriteRemoteEntryNameToModuleFederation = require('../plugins/WriteRemoteEntryNameToModuleFederation');
const TransformPreloadScript = require('../plugins/TransformPreloadScript');

module.exports = ({ outputPath, federatedComponentConfiguration }) => ({
  output: {
    library: '[chunkhash:8]',
    path: outputPath
  },
  plugins: [
    new WriteRemoteEntryNameToModuleFederation(federatedComponentConfiguration),
    new TransformPreloadScript(federatedComponentConfiguration)
  ]
});
