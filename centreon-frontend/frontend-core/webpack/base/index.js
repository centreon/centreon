const { CleanWebpackPlugin } = require('clean-webpack-plugin');
const ForkTsCheckerWebpackPlugin = require('fork-ts-checker-webpack-plugin');

module.exports = {
  resolve: {
    extensions: ['.js', '.jsx', '.ts', '.tsx'],
  },
  output: {
    libraryTarget: 'umd',
    umdNamedDefine: true,
    filename: '[name].[chunkhash:8].js',
    chunkFilename: '[name].[chunkhash:8].chunk.js',
  },
  optimization: {
    splitChunks: {
      chunks: 'all',
    },
  },
  plugins: [
    new CleanWebpackPlugin(),
    new ForkTsCheckerWebpackPlugin({
      useTypescriptIncrementalApi: true,
      checkSyntacticErrors: true,
    }),
  ],
  module: {
    rules: [
      {
        test: /\.(j|t)sx?$/,
        exclude: /node_modules(\\|\/)(?!(@centreon(\\|\/)ui))/,
        use: [
          { loader: 'cache-loader' },
          {
              loader: 'thread-loader',
              options: {
                  workers: require('os').cpus().length - 1,
              },
          },
          'babel-loader',
          {
            loader: 'ts-loader',
            options: {
              allowTsInNodeModules: true,
              transpileOnly: true,
              experimentalWatchApi: true,
              happyPackMode: true,
            }
          },
        ],
      },
    ],
  },
};