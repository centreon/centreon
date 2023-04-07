const excludeNodeModulesExceptCentreonUi =
  /node_modules(\\|\/)\.pnpm(\\|\/)(?!(@centreon))/;

module.exports = {
  cache: false,
  excludeNodeModulesExceptCentreonUi,
  getModuleConfiguration: (jscTransformConfiguration) => ({
    rules: [
      {
        parser: { system: false },
        test: /\.[cm]?(j|t)sx?$/
      },
      {
        exclude: [
          excludeNodeModulesExceptCentreonUi,
          /www(\\|\/)widgets(\\|\/)bruh/
        ],
        test: /\.[jt]sx?$/,
        use: {
          loader: 'swc-loader',
          options: {
            jsc: {
              parser: {
                syntax: 'typescript',
                tsx: true
              },
              transform: jscTransformConfiguration
            }
          }
        }
      },
      {
        test: /\.icon.svg$/,
        use: ['@svgr/webpack']
      },
      {
        exclude: excludeNodeModulesExceptCentreonUi,
        test: /\.(bmp|png|jpg|jpeg|gif|svg)$/,
        use: [
          {
            loader: 'url-loader',
            options: {
              limit: 10000,
              name: '[name].[hash:8].[ext]'
            }
          }
        ]
      }
    ]
  }),
  optimization: {
    splitChunks: {
      chunks: 'all',
      maxSize: 400 * 1024
    }
  },
  output: {
    chunkFilename: '[name].[chunkhash:8].chunk.js',
    filename: '[name].[chunkhash:8].js',
    libraryTarget: 'umd',
    umdNamedDefine: true
  }
};
