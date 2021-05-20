module.exports = {
  overrides: [
    {
      extends: [
        'plugin:@typescript-eslint/recommended',
        'plugin:typescript-sort-keys/recommended',
      ],
      files: ['*.ts', '*.tsx'],
      parser: '@typescript-eslint/parser',
      plugins: ['@typescript-eslint', 'typescript-sort-keys'],
      rules: {
        '@typescript-eslint/array-type': [
          'error',
          {
            default: 'generic',
            readonly: 'generic',
          },
        ],
        '@typescript-eslint/camelcase': 'off',
        '@typescript-eslint/consistent-type-definitions': [
          'error',
          'interface',
        ],
        '@typescript-eslint/explicit-member-accessibility': [
          'error',
          {
            accessibility: 'explicit',
            overrides: {
              accessors: 'explicit',
              constructors: 'explicit',
              methods: 'explicit',
              parameterProperties: 'explicit',
              properties: 'explicit',
            },
          },
        ],
        '@typescript-eslint/naming-convention': [
          'error',
          {
            format: ['camelCase', 'PascalCase'],
            selector: 'variable',
          },
          {
            filter: {
              match: false,
              regex: '(__esModule|.+-.+)',
            },
            format: ['snake_case', 'camelCase', 'PascalCase'],
            selector: 'property',
          },
          {
            filter: {
              match: false,
              regex: '^_$',
            },
            format: ['snake_case', 'camelCase', 'PascalCase'],
            selector: 'parameter',
          },
        ],
        '@typescript-eslint/no-shadow': ['error'],
        '@typescript-eslint/no-unused-expressions': ['error'],
        '@typescript-eslint/no-unused-vars': ['error'],
        '@typescript-eslint/prefer-function-type': 'error',
        camelcase: 'off',
        'no-shadow': 'off',
        'no-unused-expressions': 'off',
      },
      settings: {
        'import/resolver': {
          alias: {
            map: [
              ["@centreon/ui", "./node_modules/@centreon/centreon-frontend/packages/centreon-ui"],
              ["@centreon/ui-context", "./node_modules/@centreon/centreon-frontend/packages/ui-context"]
            ],
            extensions: ['.ts', '.tsx', '.js', '.jsx'],
          },
        },
      },
    },
  ],
  root: true,
};
