module.exports = {
  overrides: [
    {
      extends: [
        'plugin:@typescript-eslint/recommended',
        'plugin:typescript-sort-keys/recommended'
      ],
      files: ['*.ts', '*.tsx'],
      parser: '@typescript-eslint/parser',
      parserOptions: {
        ecmaVersion: 2022,
        sourceType: 'module'
      },
      plugins: ['@typescript-eslint', 'typescript-sort-keys'],
      rules: {
        '@typescript-eslint/array-type': [
          'error',
          {
            default: 'generic',
            readonly: 'generic'
          }
        ],
        '@typescript-eslint/camelcase': 'off',
        '@typescript-eslint/consistent-type-definitions': [
          'error',
          'interface'
        ],
        '@typescript-eslint/explicit-function-return-type': ['error'],
        '@typescript-eslint/explicit-member-accessibility': [
          'error',
          {
            accessibility: 'explicit',
            overrides: {
              accessors: 'explicit',
              constructors: 'explicit',
              methods: 'explicit',
              parameterProperties: 'explicit',
              properties: 'explicit'
            }
          }
        ],
        '@typescript-eslint/method-signature-style': ['error'],
        '@typescript-eslint/naming-convention': [
          'error',
          {
            format: ['camelCase', 'PascalCase'],
            selector: 'variable'
          },
          {
            filter: {
              match: false,
              regex: '((__esModule|.+-.+)|^_$|^(/|&))'
            },
            format: ['snake_case', 'camelCase', 'PascalCase'],
            selector: 'property'
          },
          {
            filter: {
              match: false,
              regex: '^_$|^(/|&)'
            },
            format: ['snake_case', 'camelCase', 'PascalCase'],
            selector: 'parameter'
          }
        ],
        '@typescript-eslint/no-shadow': ['error'],
        '@typescript-eslint/no-unused-expressions': ['error'],
        '@typescript-eslint/no-unused-vars': ['error'],
        '@typescript-eslint/prefer-function-type': 'error',
        '@typescript-eslint/type-annotation-spacing': [
          'error',
          {
            after: true,
            before: false,
            overrides: { arrow: { after: true, before: true } }
          }
        ],
        camelcase: 'off',
        'no-shadow': 'off',
        'no-unused-expressions': 'off'
      },
      settings: {
        'import/resolver': {
          alias: {
            extensions: ['.ts', '.tsx', '.js', '.jsx']
          }
        }
      }
    }
  ],
  root: true
};
