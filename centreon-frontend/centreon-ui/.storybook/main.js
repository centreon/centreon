module.exports = {
  stories: ['../src/**/*.stories.(jsx|tsx)'],
  addons: [],
  webpackFinal: config => ({
    ...config,
    resolve: {
      ...config.resolve,
      extensions: ['.js', '.jsx', '.ts', '.tsx'],
    },
    module: { ...config.module,
      rules: [
        {
          test: /\.jsx?$/,
          exclude: /node_modules/,
          use: ['babel-loader'],
        },
        {
          test: /\.tsx?$/,
          exclude: /node_modules/,
          use: ['babel-loader', 'awesome-typescript-loader'],
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
          loader: 'url-loader',
          query: {
            limit: 10000,
          },
        },
      ],
    },
  }),
};
