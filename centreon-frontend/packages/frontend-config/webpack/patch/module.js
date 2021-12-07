const { CleanWebpackPlugin } = require('clean-webpack-plugin');

module.exports = ({ assetPublicPath, outputPath }) => ({
  externals: {
    '@centreon/ui-context': 'CentreonUiContext',
    jotai: 'Jotai',
    react: 'React',
    'react-dom': 'ReactDOM',
    'react-i18next': 'ReactI18Next',
    'react-redux': 'ReactRedux',
    'react-redux-i18n': 'ReactReduxI18n',
    'react-router-dom': 'ReactRouterDom',
  },
  module: {
    rules: [
      {
        test: /\.(c|sa|sc)ss$/,
        use: [
          'style-loader',
          { loader: 'css-loader', options: { modules: true } },
          {
            loader: 'sass-loader',
            options: {
              sassOptions: {
                modules: true,
              },
            },
          },
        ],
      },
      {
        test: /\.(png|svg|jpg|gif|off|woff|woff2|eot|ttf|otf)$/,
        use: [
          {
            loader: 'file-loader',
            options: {
              publicPath: assetPublicPath,
            },
          },
        ],
      },
    ],
  },
  output: {
    library: '[chunkhash:8]',
    path: outputPath,
    uniqueName: `wpJsonp-${assetPublicPath}`,
  },
  plugins: [
    new CleanWebpackPlugin({
      cleanOnceBeforeBuildPatterns: [
        `${outputPath}/**/*.js`,
        `${outputPath}/**/*.css`,
      ],
      dangerouslyAllowCleanPatternsOutsideProject: true,
      dry: false,
    }),
  ],
});
