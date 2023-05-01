import type { StorybookConfig } from '@storybook/react-vite';

const SpeedMeasurePlugin = require('speed-measure-webpack-plugin');

const { equals } = require("ramda");
const excludeNodeModulesExceptCentreonUi = /node_modules(\\|\/)(?!(centreon-frontend(\\|\/)packages(\\|\/)(ui-context|centreon-ui)))/;



const config: StorybookConfig = {
  stories: [
    '../src/*.stories.mdx',
    '../src/**/*.stories.@(js|jsx|ts|tsx)'
  ],
  addons: [
    // 'storybook-addon-mock/register',
    'storybook-dark-mode',
    '@storybook/addon-controls',
    {
      name: '@storybook/addon-docs',
      options: {
        configureJSX: true
      }
    },
    '@storybook/addon-mdx-gfm',
    // {
    //   name: 'storybook-addon-swc',
    //   options: {
    //     enable: true,
    //     enableSwcLoader: true,
    //     enableSwcMinify: true,
    //     swcLoaderOptions: {},
    //     swcMinifyOptions: {},
    //   },
    // },
  ],
  features: {
    // previewMdx2: true
  },
  framework: {
    name: '@storybook/react-vite',
    options: {}
  },
  // core: {
  //   builder: {
  //     name: '@storybook/builder-webpack5',
  //     options: {
  //       fsCache: true,
  //       lazyCompilation: true,
  //     },
  //   },
  // },
  // typescript: {
  //   reactDocgen: 'react-docgen-typescript'
  // },
  docs: {
    autodocs: true
  },
  // webpackFinal: async (config) => {
  //   config.plugins.push(new SpeedMeasurePlugin());
  //   return config;
  // }
  // TODO
  // webpackFinal: async (config, { configType }) => {
  //   delete config.resolve.alias['emotion-theming'];
  //   delete config.resolve.alias['@emotion/styled'];
  //   delete config.resolve.alias['@emotion/core'];
  //   const configWithoutEmotionAliases = {
  //     ...config,
  //     resolve: {
  //       ...config.resolve,
  //       extensions: ['.js', '.ts', '.tsx']
  //     },
  //     module: {
  //       ...config.module,
  //       rules: [{
  //         test: /\.tsx?$/,
  //         exclude: /node_modules(\\|\/)(?!(@centreon))/,
  //         use: {
  //           loader: 'swc-loader',
  //           options: {
  //             jsc: {
  //               transform: {
  //                 react: {
  //                   runtime: 'automatic'
  //                 }
  //               },
  //               parser: {
  //                 syntax: 'typescript'
  //               }
  //             },
  //             parseMap: true
  //           }
  //         }
  //       }, {
  //         exclude: excludeNodeModulesExceptCentreonUi,
  //         test: /\.(bmp|png|jpg|jpeg|gif|svg)$/,
  //         use: [{
  //           loader: 'url-loader',
  //           options: {
  //             limit: 10000,
  //             name: '[name].[hash:8].[ext]'
  //           }
  //         }]
  //       }, ...config.module.rules.filter(({
  //         type
  //       }) => !equals(type, 'asset/resource'))]
  //     }
  //   };
  //
  //   return configWithoutEmotionAliases;
  // }
};

export default config;