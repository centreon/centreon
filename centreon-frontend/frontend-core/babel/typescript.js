const commonPresets = [
  '@babel/preset-typescript',
  '@babel/preset-react',
  [
    '@babel/preset-env',
    {
      modules: false,
    },
  ],
];

module.exports = {
  extends: './index.js',
  env: {
    production: {
      presets: commonPresets
    },
    development: {
      presets: commonPresets
    },
    test: {
      presets: commonPresets
    }
  }
}