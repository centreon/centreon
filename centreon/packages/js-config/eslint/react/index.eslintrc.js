module.exports = {
  env: {
    browser: true,
    es6: true,
    jest: true
  },
  extends: ['airbnb', '../base.eslintrc.js'],
  parserOptions: {
    ecmaFeatures: {
      jsx: true
    },
    ecmaVersion: 2018,
    sourceType: 'module'
  },

  plugins: ['react', 'hooks', 'react-hooks'],
  root: true,
  rules: {
    camelcase: ['error', { ignoreDestructuring: true, properties: 'never' }],
    'hooks/sort': [
      2,
      {
        groups: [
          'useStyles',
          'useTranslation',
          'useState',
          'useRequest',
          'useUserContext',
          'useAtom',
          'useAtomValue',
          'useSetAtom',
          'useCallback',
          'useEffect'
        ]
      }
    ],
    'import/extensions': [
      'error',
      'ignorePackages',
      {
        js: 'never',
        jsx: 'never',
        ts: 'never',
        tsx: 'never'
      }
    ],
    'import/no-extraneous-dependencies': 'off',
    'import/order': [
      'error',
      {
        'newlines-between': 'always',
        pathGroups: [
          {
            group: 'builtin',
            pattern: 'react',
            position: 'before'
          },
          {
            group: 'external',
            pattern: '@mui/**',
            position: 'after'
          },
          {
            group: 'parent',
            pattern: '@centreon/**',
            position: 'before'
          }
        ],
        pathGroupsExcludedImportTypes: ['builtin']
      }
    ],
    'no-use-before-define': 'off',
    'prefer-arrow-functions/prefer-arrow-functions': ['error'],
    'react/function-component-definition': [
      'error',
      {
        namedComponents: 'arrow-function',
        unnamedComponents: 'arrow-function'
      }
    ],
    'react/jsx-filename-extension': ['error', { extensions: ['.jsx'] }],
    'react/jsx-key': 'error',
    'react/jsx-no-duplicate-props': ['error', { ignoreCase: false }],
    'react/jsx-props-no-spreading': 'off',
    'react/jsx-sort-props': [
      'error',
      {
        callbacksLast: true,
        shorthandFirst: true
      }
    ],
    'react/jsx-uses-react': 'off',
    'react/jsx-wrap-multilines': ['error', { prop: false }],
    'react/react-in-jsx-scope': 'off',
    'react/state-in-constructor': 'off',
    'react-hooks/exhaustive-deps': 'off',
    'react-hooks/rules-of-hooks': 'error'
  }
};
