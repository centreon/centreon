module.exports = {
  moduleNameMapper: {
    '\\.(s?css|png|svg|svg\\?component|jpg)$': 'identity-obj-proxy',
    '^.+\\.(css|styl|less|sass|scss|png|jpg|ttf|woff|woff2)$':
      'jest-transform-stub',
    '^react($|/.+)': '<rootDir>/node_modules/react$1'
  },
  testEnvironment: 'jsdom',
  testPathIgnorePatterns: ['/node_modules/', '!*.cypress.spec.tsx'],
  transform: {
    '.+\\.(css|styl|less|sass|scss|png|jpg|ttf|woff|woff2)$':
      'jest-transform-stub',
    '^.+\\.[jt]sx?$': [
      '@swc/jest',
      {
        jsc: {
          transform: {
            react: {
              runtime: 'automatic'
            }
          }
        }
      }
    ]
  },
  transformIgnorePatterns: [
    '/node_modules/(?!@centreon/(ui|ui-context)).+\\.jsx?$'
  ]
};
