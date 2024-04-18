const { CleanWebpackPlugin } = require('clean-webpack-plugin');

const WriteRemoteEntryNameToModuleFederation = require('../plugins/WriteRemoteEntryNameToModuleFederation');
const TransformPreloadScript = require('../plugins/TransformPreloadScript');

module.exports = ({ outputPath, federatedComponentConfiguration }) => ({
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
    new WriteRemoteEntryNameToModuleFederation(federatedComponentConfiguration),
    new TransformPreloadScript(federatedComponentConfiguration)
  ]
});
