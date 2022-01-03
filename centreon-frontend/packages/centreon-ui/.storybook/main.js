module.exports = {
  core: {
    builder: 'webpack5',
  },
  stories: ['../src/**/*.stories.@(jsx|tsx)'],
  typescript: {
    reactDocgen: 'none'
  },
  addons: [],
  webpackFinal: (config) => {

    delete config.resolve.alias['emotion-theming'];
    delete config.resolve.alias['@emotion/styled'];
    delete config.resolve.alias['@emotion/core'];
  
    const configWithoutEmotionAliases = {
      ...config,
      resolve: {
        ...config.resolve,
        extensions: ['.js', '.jsx', '.ts', '.tsx'],
      },
      module: {
        ...config.module,
        rules: [
          {
            test: /\.jsx?$/,
            exclude: /node_modules/,
            use: ['babel-loader'],
          },
          {
            test: /\.tsx?$/,
            exclude: /node_modules(\\|\/)(?!(@centreon))/,
            use: [
              'babel-loader',
              {
                loader: 'ts-loader',
                options: {
                  allowTsInNodeModules: true,
                },
              },
            ],
          },
        ],
      },
    }

    return configWithoutEmotionAliases;
  }
};
