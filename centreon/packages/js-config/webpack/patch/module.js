const { CleanWebpackPlugin } = require('clean-webpack-plugin');

const WriteRemoEntryNameToModuleFederation = require('../plugins/WriteRemoEntryNameToModuleFederation');
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
    new WriteRemoEntryNameToModuleFederation(federatedComponentConfiguration),
    new TransformPreloadScript(federatedComponentConfiguration)
  ]
});
