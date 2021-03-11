module.exports = {
  root: true,
  extends: ['plugin:prettier/recommended'],
  globals: {
    Atomics: 'readonly',
    SharedArrayBuffer: 'readonly',
  },
  parser: 'babel-eslint',
  parserOptions: {
    ecmaVersion: 2018,
    sourceType: 'module',
  },
  plugins: ['prefer-arrow-functions'],

  rules: {
    'prettier/prettier': [
      'error',
      {
        singleQuote: true,
        arrowParens: 'always',
        trailingComma: 'all',
        endOfLine: 'auto',
      },
    ],
    'import/no-extraneous-dependencies': ['error', { devDependencies: true }],

    'import/prefer-default-export': 'off',
    'import/extensions': [
      'error',
      'ignorePackages',
      {
        js: 'never',
      },
    ],
    'prefer-arrow-functions/prefer-arrow-functions': ['error'],
    camelcase: ['error', { properties: 'never', ignoreDestructuring: true }],
  },
};
