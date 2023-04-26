module.exports = {
  extends: ['plugin:prettier/recommended'],
  globals: {
    Atomics: 'readonly',
    SharedArrayBuffer: 'readonly'
  },
  parserOptions: {
    ecmaVersion: 2022,
    sourceType: 'module'
  },
  plugins: ['prefer-arrow-functions', 'sort-keys-fix'],
  root: true,
  rules: {
    camelcase: ['error', { ignoreDestructuring: true, properties: 'never' }],
    'import/extensions': [
      'error',
      'ignorePackages',
      {
        js: 'never'
      }
    ],
    'import/no-extraneous-dependencies': ['error', { devDependencies: true }],
    'import/prefer-default-export': 'off',
    'newline-before-return': ['error'],
    'no-promise-executor-return': 'off',
    'prefer-arrow-functions/prefer-arrow-functions': ['error'],
    'prettier/prettier': [
      'error',
      {
        arrowParens: 'always',
        endOfLine: 'auto',
        singleQuote: true,
        trailingComma: 'none'
      }
    ],
    'sort-keys-fix/sort-keys-fix': ['error', 'asc', { natural: true }]
  }
};
