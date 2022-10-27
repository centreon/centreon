const { CleanWebpackPlugin } = require('clean-webpack-plugin');
const path = require('path');
const { ModuleFederationPlugin } = require('webpack').container;

const excludeNodeModulesExceptCentreonUi =
  /node_modules(\\|\/)(?!(centreon-frontend(\\|\/)packages(\\|\/)(ui-context|centreon-ui)))/;

const getBaseConfiguration = ({
  moduleName,
  moduleFederationConfig,
  jscTransformConfiguration,
}) => ({
  cache: false,
  module: {
    rules: [
      {
        parser: { system: false },
        test: /\.[cm]?(j|t)sx?$/,
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
                tsx: true,
              },
              transform: jscTransformConfiguration,
            },
          },
        },
      },
      {
        test: /\.icon.svg$/,
        use: ['@svgr/webpack'],
      },
      {
        exclude: excludeNodeModulesExceptCentreonUi,
        test: /\.(bmp|png|jpg|jpeg|gif|svg)$/,
        use: [
          {
            loader: 'url-loader',
            options: {
              limit: 10000,
              name: '[name].[hash:8].[ext]',
            },
          },
        ],
      },
    ],
  },
  optimization: {
    splitChunks: {
      chunks: 'all',
      maxSize: 400 * 1024,
    },
  },
  output: {
    chunkFilename: '[name].[chunkhash:8].chunk.js',
    filename: '[name].[chunkhash:8].js',
    libraryTarget: 'umd',
    umdNamedDefine: true,
  },
  plugins: [
    new CleanWebpackPlugin(),
    new ModuleFederationPlugin({
      filename: 'remoteEntry.[chunkhash:8].js',
      library: { name: moduleName, type: 'var' },
      name: moduleName,
      shared: [
        {
          '@centreon/ui-context': {
            requiredVersion: '22.10.0',
            singleton: true,
          },
        },
        {
          jotai: {
            requiredVersion: '1.x',
            singleton: true,
          },
        },
        {
          'jotai-suspense': {
            requiredVersion: '0.1.x',
            singleton: true,
          },
        },
        {
          react: {
            requiredVersion: '18.x',
            singleton: true,
          },
        },
        {
          'react-dom': {
            requiredVersion: '18.x',
            singleton: true,
          },
        },
        {
          'react-i18next': {
            requiredVersion: '11.x',
            singleton: true,
          },
        },
        {
          'react-router-dom': {
            requiredVersion: '6.x',
            singleton: true,
          },
        },
      ],
      ...moduleFederationConfig,
    }),
  ],
  resolve: {
    alias: {
      '@centreon/ui': path.resolve(
        './node_modules/centreon-frontend/packages/centreon-ui',
      ),
      '@centreon/ui-context': path.resolve(
        './node_modules/centreon-frontend/packages/ui-context',
      ),
      react: path.resolve('./node_modules/react'),
    },
    extensions: ['.js', '.jsx', '.ts', '.tsx'],
  },
});

module.exports = getBaseConfiguration;
