module.exports = {
  devJscTransformConfiguration: {
    react: {
      development: true,
      refresh: false,
    },
  },
  devRefreshJscTransformConfiguration: {
    react: {
      development: true,
      refresh: true,
    },
  },
  getDevConfiguration: () => ({
    cache: true,
    devtool: 'eval-cheap-module-source-map',
    optimization: {
      splitChunks: false,
    },
    output: {
      filename: '[name].js',
    },
  }),
};
