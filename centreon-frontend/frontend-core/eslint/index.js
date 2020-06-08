module.exports = {
  env: {
    browser: true,
    es6: true,
    jest: true,
  },
  extends: ['airbnb', 'plugin:prettier/recommended'],
  globals: {
    Atomics: 'readonly',
    SharedArrayBuffer: 'readonly',
  },
  parser: 'babel-eslint',
  parserOptions: {
    ecmaFeatures: {
      jsx: true,
    },
    ecmaVersion: 2018,
    sourceType: 'module',
  },
  plugins: ['react', 'react-hooks', 'babel', 'prefer-arrow-functions'],
  settings: {
    'import/resolver': {
      alias: {
        extensions: ['.ts', '.tsx', '.js', '.jsx']
      }
    }
  },
  rules: {
    'prettier/prettier': [
      'error',
      { singleQuote: true, arrowParens: 'always', trailingComma: 'all', endOfLine: 'auto' },
    ],
    'import/no-extraneous-dependencies': [
      'error',
      { devDependencies: true }
    ],
    'react-hooks/rules-of-hooks': 'error',
    'react-hooks/exhaustive-deps': 'off',
    'react/jsx-filename-extension': ['error', { extensions: ['.jsx'] }],
    'react/jsx-key': 'error',
    'react/jsx-no-duplicate-props': [
      'error',
      { 'ignoreCase': false }
    ],
    'react/jsx-props-no-spreading': 'off',
    'react/jsx-wrap-multilines': 'off',
    'react/state-in-constructor': 'off',
    'import/prefer-default-export': 'off',
    'no-use-before-define': 'off',
    'import/extensions': [
      'error',
      'ignorePackages',
      {
        'js': 'never',
        'jsx': 'never',
        'ts': 'never',
        'tsx': 'never'
      }
    ],
    'prefer-arrow-functions/prefer-arrow-functions': ['error'],
    camelcase: ['error', { properties: 'never', ignoreDestructuring: true }],
  },
};
