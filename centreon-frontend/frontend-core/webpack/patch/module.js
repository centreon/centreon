module.exports = assetPublicPath => ({
  externals: {
    react: 'React',
    'react-dom': 'ReactDOM',
    'react-redux-i18n': 'ReactReduxI18n',
    'react-router-dom': 'ReactRouterDom',
    'react-redux': 'ReactRedux',
  },
  optimization: {
    splitChunks: {
      cacheGroups: {
        commons: {
          name: 'commons',
          filename: '[name].[chunkhash:8].js',
          chunks: 'initial',
          minChunks: 2,
        },
        vendor: {
          test: /node_modules/,
          chunks: 'initial',
          name: 'vendor',
          filename: '[name].[chunkhash:8].js',
          priority: 10,
          enforce: true,
        },
      },
    },
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
