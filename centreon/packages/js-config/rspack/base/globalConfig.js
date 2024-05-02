const isDev = process.env.NODE_ENV !== 'production';

const excludeNodeModulesExceptCentreonUi =
  /node_modules(\\|\/)\.pnpm(\\|\/)(?!(@centreon|file\+packages\+ui-context))/;

module.exports = {
  cache: false,
  excludeNodeModulesExceptCentreonUi,
  getModuleConfiguration: (enableCoverage) => ({
    rules: [
      {
        exclude: [excludeNodeModulesExceptCentreonUi],
        test: /\.[jt]sx?$/,
        use: {
          loader: 'swc-loader',
          options: {
            jsc: {
              experimental: {
                plugins: [
                  enableCoverage && ['swc-plugin-coverage-instrument', {}]
                ].filter(Boolean)
              },
              parser: {
                syntax: 'typescript',
                tsx: true
              },
              transform: {
                react: {
                  development: isDev,
                  refresh: isDev
                }
              }
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
        type: 'asset/inline'
      },
      {
        generator: {
          filename: '[name][ext]'
        },
        test: /\.(woff|woff2|eot|ttf|otf)$/i,
        type: 'asset/resource'
      },
      {
        test: /\.css$/i,
        use: ['style-loader', 'css-loader']
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
