module.exports = {
  env: {
    browser: true,
    es6: true,
    jest: true,
  },
  root: true,
  extends: ['airbnb', '../base.eslintrc.js'],

  parserOptions: {
    ecmaFeatures: {
      jsx: true,
    },
    ecmaVersion: 2018,
    sourceType: 'module',
  },
  plugins: ['react', 'react-hooks', 'babel'],
  settings: {
    'import/resolver': {
      alias: {
        extensions: ['.ts', '.tsx', '.js', '.jsx']
      }
    }
  },
  rules: {
   
    'import/no-extraneous-dependencies': [
      'error',
      { devDependencies: true }
    ],
    'import/order': ['error', {
        pathGroups: [
          {
            pattern: 'react',
            group: 'builtin',
            position: 'before',
          },
          {
            pattern: '@material-ui/**',
            group: 'external',
            position: 'after',
          },
          {
            pattern: '@centreon/**',
            group: 'parent',
            position: 'before',
          },
        ],
        'pathGroupsExcludedImportTypes': ['builtin'],
        'newlines-between': 'always',
      }
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
    'react/jsx-wrap-multilines': ['error', { prop: false }],
  },
};
