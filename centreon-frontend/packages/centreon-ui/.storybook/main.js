module.exports = {
  stories: ['../src/**/*.stories.@(jsx|tsx)'],
  typescript: {
    reactDocgen: 'none'
  },
  addons: [],
  webpackFinal: (config) => ({
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
  }),
};
