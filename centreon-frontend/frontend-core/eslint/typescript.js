module.exports = {
  extends: [
    './index.js'
  ],
  overrides: [
    {
      files: ["*.ts", "*.tsx"],
      extends: ['plugin:@typescript-eslint/recommended'],
      parser: '@typescript-eslint/parser',
      plugins: ['@typescript-eslint'],
      settings: {
        'import/resolver': {
          alias: {
            extensions: ['.ts', '.tsx', '.js', '.jsx']
          }
        }
      },
      rules: {
        'react/jsx-filename-extension': ['error', { extensions: ['.jsx', '.tsx'] }],
        camelcase: 'off',
        '@typescript-eslint/naming-convention': [
          'error',
          {
            selector: 'variable', format: ['camelCase', 'PascalCase'],
          },
          {
            selector: 'property', format: ['snake_case', 'camelCase'],
          },
          {
            selector: 'parameter', format: ['snake_case', 'camelCase'],
          },
        ],
        '@typescript-eslint/prefer-function-type': 'error',
        "@typescript-eslint/consistent-type-definitions": ["error", "interface"],
        '@typescript-eslint/array-type': ["error", {
          "default" : "generic",
          "readonly": "generic"
        }],
        '@typescript-eslint/explicit-member-accessibility': ["error", {
          accessibility: 'explicit',
          overrides: {
            accessors: 'explicit',
            constructors: 'explicit',
            methods: 'explicit',
            properties: 'explicit',
            parameterProperties: 'explicit'
          }
        }],
        '@typescript-eslint/no-unused-expressions': ['error'],
        'no-unused-expressions': 'off',
        'react/require-default-props': 'off',
      },
    }
  ]
}