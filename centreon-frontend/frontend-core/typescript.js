module.exports = {
  extends: [
    './index.js',
    'plugin:@typescript-eslint/recommended'
  ],
  parser: '@typescript-eslint/parser',
  plugins: ['@typescript-eslint'],
  settings: {
    'import/resolver': {
      alias: {
        map: [
          ['@centreon/ui', '@centreon/ui/src'],
        ],
        extensions: ['.ts', '.tsx', '.js', '.jsx']
      }
    }
  },
  rules: {
    'react/jsx-filename-extension': ['error', { extensions: ['.jsx', '.tsx'] }],
    '@typescript-eslint/camelcase': 'off',
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
    '@typescript-eslint/no-unused-expressions': ["error"]
  }
}