const merge = require('babel-merge');

const configuration = {
  plugins: [
    '@babel/proposal-class-properties',
    '@babel/plugin-proposal-optional-chaining',
    '@babel/plugin-proposal-nullish-coalescing-operator',
  ],
  presets: [
    [
      '@babel/preset-react',
      '@babel/preset-env',
      {
        modules: false,
      },
    ],
  ],
};

module.exports = {
  env: {
    development: configuration,
    production: configuration,
    test: merge(configuration, {
      presets: [
        [
          '@babel/preset-env',
          {
            targets: {
              node: 'current',
            },
          },
        ],
      ],
    }),
  },
};
