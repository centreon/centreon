module.exports = {
  extends: ['./index.eslintrc.js', '../base.typescript.eslintrc.js'],
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
        'react/jsx-filename-extension': [
          'error',
          { extensions: ['.jsx', '.tsx'] },
        ],
        'react/require-default-props': 'off',
      },
    },
  ],
};
