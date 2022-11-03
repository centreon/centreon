const { CleanWebpackPlugin } = require('clean-webpack-plugin');

module.exports = ({ assetPublicPath, outputPath }) => ({
  externals: {
    '@centreon/ui-context': 'CentreonUiContext',
    jotai: 'Jotai',
    react: 'React',
    'react-dom': 'ReactDOM',
    'react-i18next': 'ReactI18Next',
    'react-router-dom': 'ReactRouterDom',
  },
  output: {
    library: '[chunkhash:8]',
    path: outputPath,
    uniqueName: `wpJsonp-${assetPublicPath}`,
  },
  plugins: [
    new CleanWebpackPlugin({
      cleanOnceBeforeBuildPatterns: [`${outputPath}/**/*.js`],
      dangerouslyAllowCleanPatternsOutsideProject: true,
      dry: false,
    }),
  ],
});
