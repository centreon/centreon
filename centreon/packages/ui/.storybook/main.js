const { equals } = require("ramda");

const excludeNodeModulesExceptCentreonUi =
  /node_modules(\\|\/)(?!(centreon-frontend(\\|\/)packages(\\|\/)(ui-context|centreon-ui)))/;
  
module.exports = {
  core: {
    builder: 'webpack5',
  },
  features: {
    previewMdx2: true,
  },
  stories: ['../src/*.stories.mdx', '../src/**/*.stories.@(jsx|tsx)'],
  typescript: {
    reactDocgen: 'react-docgen-typescript'
  },
  addons: [
    'storybook-addon-mock/register',
    'storybook-dark-mode',
    '@storybook/addon-controls', 
    {
      name: '@storybook/addon-docs',
      options: {
        configureJSX: true,
      },
    },
  ],
  webpackFinal: (config) => {
    delete config.resolve.alias['emotion-theming'];
    delete config.resolve.alias['@emotion/styled'];
    delete config.resolve.alias['@emotion/core'];

    const configWithoutEmotionAliases = {
      ...config,
      resolve: {
        ...config.resolve,
        extensions: ['.js', '.ts', '.tsx'],
      },
      module: {
        ...config.module,
        rules: [
          {
            test: /\.tsx?$/,
            exclude: /node_modules(\\|\/)(?!(@centreon))/,
            use: {
              loader: 'swc-loader',
              options: {
                jsc: {
                  transform: {
                    react: {
                      runtime: 'automatic',
                    },
                  },
                  parser: {
                    syntax: 'typescript',
                  },
                },
                parseMap: true,
              },
            }
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
          ...(config.module.rules.filter(({ type }) => !equals(type, 'asset/resource'))),
        ],
      },
    }

    return configWithoutEmotionAliases;
  }
};
