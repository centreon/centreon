const isDev = process.env.NODE_ENV !== 'production';

const excludeNodeModulesExceptCentreonUi =
  /node_modules(\\|\/)\.pnpm(\\|\/)(?!(@centreon|file\+packages\+ui-context))/;

module.exports = {
  cache: false,
  excludeNodeModulesExceptCentreonUi,
  getModuleConfiguration: (enableCoverage, postCssBase = './') => {
    // Only a literal `true` enables coverage: the rspack CLI passes its env
    // object as the first argument of a config exported as a function, and
    // that object must not turn instrumentation on in production builds.
    const coverage = enableCoverage === true;

    return {
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
                    coverage && ['swc-plugin-coverage-instrument', {}]
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
          test: /\.module\.css$/,
          type: 'css/module'
        },
        {
          test: /\.css$/,
          exclude: /\.module\.css$/,
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
    };
  },
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
