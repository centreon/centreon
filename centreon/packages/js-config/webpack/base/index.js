const path = require('path');

const { CleanWebpackPlugin } = require('clean-webpack-plugin');
const { ModuleFederationPlugin } = require('webpack').container;

const excludeNodeModulesExceptCentreonUi =
  /node_modules(\\|\/)\.pnpm(\\|\/)(?!(@centreon))/;

const getBaseConfiguration = ({
  moduleName,
  moduleFederationConfig,
  jscTransformConfiguration
}) => ({
  cache: false,
  module: {
    rules: [
      {
        parser: { system: false },
        test: /\.[cm]?(j|t)sx?$/
      },
      {
        exclude: excludeNodeModulesExceptCentreonUi,
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
  },
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
  },
  plugins: [
    new CleanWebpackPlugin(),
    moduleName &&
      new ModuleFederationPlugin({
        filename: 'remoteEntry.[chunkhash:8].js',
        library: { name: moduleName, type: 'var' },
        name: moduleName,
        shared: [
          {
            '@centreon/ui-context': {
              requiredVersion: '23.04.x',
              singleton: true
            }
          },
          {
            jotai: {
              requiredVersion: '2.x',
              singleton: true
            }
          },
          {
            'jotai-suspense': {
              requiredVersion: '0.2.x',
              singleton: true
            }
          },
          {
            react: {
              requiredVersion: '18.x',
              singleton: true
            }
          },
          {
            'react-dom': {
              requiredVersion: '18.x',
              singleton: true
            }
          },
          {
            'react-i18next': {
              requiredVersion: '11.x',
              singleton: true
            }
          },
          {
            'react-router-dom': {
              requiredVersion: '6.x',
              singleton: true
            }
          }
        ],
        ...moduleFederationConfig
      })
  ].filter(Boolean),
  resolve: {
    alias: {
      react: path.resolve('./node_modules/react')
    },
    extensions: ['.js', '.jsx', '.ts', '.tsx']
  }
});

module.exports = getBaseConfiguration;
