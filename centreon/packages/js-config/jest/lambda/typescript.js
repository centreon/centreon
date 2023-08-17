/*
 * Copyright 2023 Centreon Team
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

// For a detailed explanation regarding each configuration property, visit:
// https://jestjs.io/docs/en/configuration.html
const path = require('path');

const rootPath = path.join(__dirname);



module.exports = {
    rootDir: rootPath,
    // Automatically clear mock calls and instances between every test
    clearMocks: true,
    // The directory where Jest should output its coverage files
    coverageDirectory: '<rootDir>/coverage',
    // An array of regexp pattern strings used to skip coverage collection
    coveragePathIgnorePatterns: ['\\\\node_modules\\\\', 'tests'],

    // An array of file extensions your modules use
    moduleFileExtensions: ['ts', 'tsx', 'js', 'jsx'],

    // Automatically reset mock state between every test
    // resetMocks: true,

    testMatch: ['**/*.(test|tests|spec|specs).+(ts|tsx|js)'],

    // This option allows the use of a custom results processor
    // testResultsProcessor: 'jest-sonar-reporter',

    // A map from regular expressions to paths to transformers
    transform: {
        '^.+\\.(ts|tsx)$': ['ts-jest', { tsconfig: '<rootDir>/tsconfig.json' }],
    },
};