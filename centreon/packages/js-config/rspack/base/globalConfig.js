const isDev = process.env.NODE_ENV !== 'production';

const excludeNodeModulesExceptCentreonUi =
  /node_modules(\\|\/)\.pnpm(\\|\/)(?!(@centreon|file\+packages\+ui-context))/;

module.exports = {
  cache: false,
  excludeNodeModulesExceptCentreonUi,
  getModuleConfiguration: (enableCoverage, postCssBase = './') => ({
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
        test: /\.css$/,
        type: 'css/auto',
        use: [
          {
            loader: 'postcss-loader',
            options: {
              postcssOptions: {
                plugins: {
                  '@tailwindcss/postcss': {
                    base: postCssBase
                  }
                }
              }
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
    chunkFilename: isDev
      ? '[name].[contenthash:8].[chunkhash:8].chunk.js'
      : '[name].[contenthash].[chunkhash].[hash].js',
    filename: isDev
      ? '[name].[contenthash:8].js'
      : '[name].[contenthash].[hash].js',
    libraryTarget: 'umd',
    umdNamedDefine: true
  }
};
