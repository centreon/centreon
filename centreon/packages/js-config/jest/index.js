module.exports = {
  moduleNameMapper: {
    '\\.(s?css|png|svg|jpg)$': 'identity-obj-proxy',
    '^react($|/.+)': '<rootDir>/node_modules/react$1'
  },
  setupFilesAfterEnv: ['@testing-library/jest-dom/extend-expect'],
  testEnvironment: 'jsdom',
  testPathIgnorePatterns: ['/node_modules/'],
  transform: {
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
