const rspack = require('@rspack/core');

/**
 * @type {import('@rspack/cli').Configuration}
 */
module.exports = {
  context: __dirname,
  entry: {
    main: './src/index.ts'
  },
  module: {
    rules: [
      {
        test: /\.[jt]sx?$/,
        use: {
          loader: 'swc-loader',
          options: {
            jsc: {
              externalHelpers: true,
              parser: {
                jsx: true,
                syntax: 'typescript'
              },
              preserveAllComments: false,
              transform: {
                react: {
                  runtime: 'automatic',
                  throwIfNamespace: true,
                  useBuiltins: false
                }
              }
            },
            sourceMap: true
          }
        }
      },
      {
        test: /\.svg$/,
        type: 'asset'
      }
    ]
  },
  optimization: {
    minimize: false // Disabling minification because it takes too long on CI
  },
  plugins: [
    new rspack.HtmlRspackPlugin({
      template: './index.html'
    })
  ],
  resolve: {
    extensions: ['...', '.ts', '.tsx', '.jsx']
  }
};
