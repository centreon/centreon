module.exports = {
  core: {
    builder: 'webpack5',
  },
  stories: ['../src/**/*.stories.@(jsx|tsx)'],
  typescript: {
    reactDocgen: 'none'
  },
  addons: ['storybook-dark-mode'],
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
              },
            }
          }
        ],
      },
    }

    return configWithoutEmotionAliases;
  }
};
