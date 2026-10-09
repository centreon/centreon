const fs = require('node:fs');
const path = require('node:path');
const { mergeDeepRight } = require('ramda');

// d3-time-format is only a dependency of d3-scale, so pnpm does not hoist it:
// resolve it next to d3-scale.
const d3TimeFormatDirectory = path.join(
  fs.realpathSync(path.join(__dirname, 'node_modules/d3-scale')),
  '../d3-time-format'
);

module.exports = mergeDeepRight(require('./packages/js-config/jest'), {
  // Keys are regular expressions: anchor them so that `d3-time` does not also
  // swallow `d3-time-format`.
  moduleNameMapper: {
    '\\.(s?css|png|svg|jpg)$': '<rootDir>/www/front_src/src/__mocks__/image.js',
    '^d3-array$': '<rootDir>/node_modules/d3-array/dist/d3-array.min.js',
    '^d3-color$': '<rootDir>/node_modules/d3-color/dist/d3-color.min.js',
    '^d3-format$': '<rootDir>/node_modules/d3-format/dist/d3-format.min.js',
    '^d3-interpolate$':
      '<rootDir>/node_modules/d3-interpolate/dist/d3-interpolate.min.js',
    '^d3-scale$': '<rootDir>/node_modules/d3-scale/dist/d3-scale.min.js',
    '^d3-time-format$': `${d3TimeFormatDirectory}/dist/d3-time-format.min.js`,
    '^d3-time$': '<rootDir>/node_modules/d3-time/dist/d3-time.min.js'
  },
  roots: ['<rootDir>/www/front_src/src/'],
  setupFilesAfterEnv: ['<rootDir>/setupTest.js'],
  testEnvironmentOptions: {
    url: 'http://localhost/'
  },
  testMatch: ['**/__tests__/**/*.[jt]s?(x)', '**/?(*.)+(test).[jt]s?(x)'],
  testResultsProcessor: 'jest-junit'
});
