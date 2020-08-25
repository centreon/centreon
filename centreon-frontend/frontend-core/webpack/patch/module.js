module.exports = assetPublicPath => ({
  output: {
    library: '[chunkhash:8]',
    jsonpFunction: `wpJsonp-${assetPublicPath}`,
  },
  externals: {
    react: 'React',
    'react-dom': 'ReactDOM',
    'react-redux-i18n': 'ReactReduxI18n',
    'react-router-dom': 'ReactRouterDom',
    'react-redux': 'ReactRedux',
    'react-i18next': 'ReactI18Next',
    '@centreon/ui-context': 'CentreonUiContext',
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
});
