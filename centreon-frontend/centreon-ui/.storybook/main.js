const merge = require('lodash/merge');
const webpackMerge = require('webpack-merge');
const baseConfig = require('@centreon/frontend-core').webpackTypeScript;

const storiesPath = '../src/**/*.stories';

module.exports = {
  stories: [`${storiesPath}.jsx`, `${storiesPath}.tsx`],
  addons: [],
  webpackFinal: (config) =>
    merge(
      config,
      webpackMerge(baseConfig, {
        module: {
          rules: [
            {
              test: /\.s?[ac]ss$/i,
              use: [
                'style-loader',
                {
                  loader: 'css-loader',
                  options: {
                    modules: {
                      localIdentName: '[local]__[hash:base64:5]',
                    },
                  },
                },
                'sass-loader',
              ],
            },
            {
              test: /\.(?:woff|woff2|eot|ttf|otf)$/,
              loader: 'file-loader',
            },
            {
              test: /\.(?:png|jpg|svg)$/,
              loader: 'url-loader',
              query: {
                limit: 10000,
              },
            },
          ],
        },
      })
    ),
};
