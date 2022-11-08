module.exports = {
  env: {
    browser: true,
    es6: true,
    jest: true,
  },
  extends: ['airbnb', '../base.eslintrc.js'],
  parserOptions: {
    ecmaFeatures: {
      jsx: true,
    },
    ecmaVersion: 2018,
    sourceType: 'module',
  },

  plugins: ['react', 'react-hooks', 'babel'],
  root: true,
  rules: {
    'import/no-extraneous-dependencies': 'off',
    'import/order': [
      'error',
      {
        'newlines-between': 'always',
        pathGroups: [
          {
            group: 'builtin',
            pattern: 'react',
            position: 'before',
          },
          {
            group: 'external',
            pattern: '@material-ui/**',
            position: 'after',
          },
          {
            group: 'parent',
            pattern: '@centreon/**',
            position: 'before',
          },
        ],
        pathGroupsExcludedImportTypes: ['builtin'],
      },
    ],
    'no-use-before-define': 'off',
    'import/extensions': [
      'error',
      'ignorePackages',
      {
        js: 'never',
        jsx: 'never',
        ts: 'never',
        tsx: 'never',
      },
    ],
    'react/jsx-filename-extension': ['error', { extensions: ['.jsx'] }],
    camelcase: ['error', { ignoreDestructuring: true, properties: 'never' }],
    'react/jsx-key': 'error',
    'prefer-arrow-functions/prefer-arrow-functions': ['error'],
    'react/jsx-no-duplicate-props': ['error', { ignoreCase: false }],
    'react/jsx-props-no-spreading': 'off',
    'react-hooks/exhaustive-deps': 'off',
    'react/jsx-sort-props': [
      'error',
      {
        callbacksLast: true,
        shorthandFirst: true,
      },
    ],
    'react-hooks/rules-of-hooks': 'error',
    'react/jsx-wrap-multilines': 'off',
    'react/jsx-wrap-multilines': ['error', { prop: false }],
    'react/state-in-constructor': 'off',
  },
  settings: {
    'import/resolver': {
      alias: {
        extensions: ['.ts', '.tsx', '.js', '.jsx'],
        map: [
          ["@centreon/ui", "@centreon/centreon-frontend/packages/centreon-ui"],
          ["@centreon/ui-context", "@centreon/centreon-frontend/packages/ui-context"]
        ],
      },
    },
  },
};
