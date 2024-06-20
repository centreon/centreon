/* eslint-disable import/newline-after-import */
/* eslint-disable @typescript-eslint/no-var-requires */
/* eslint-disable @typescript-eslint/no-unused-vars */
import 'cypress-wait-until';
import 'cypress-real-events';
import './commands';
const fs = require('fs');
const path = require('path');

before(() => {
  Cypress.config('baseUrl', 'http://127.0.0.1:4000');

  cy.intercept('/waiting-page', {
    headers: { 'content-type': 'text/html' },
    statusCode: 200
  }).visit('/waiting-page');
});

Cypress.on('uncaught:exception', (err) => {
  if (
    err.message.includes('Request failed with status code 401') ||
    err.message.includes('Request failed with status code 403') ||
    err.message.includes('undefined') ||
    err.message.includes('postMessage') ||
    err.message.includes('canceled')
  ) {
    return false;
  }

  return true;
});

Cypress.on('test:after:run', (test, runnable) => {
  const resultFilePath = path.join(
    __dirname,
    '../results/hasRetries.json',
    'hasRetries.json'
  );

  // Initialize an empty object or load existing data
  let testRetries = {};
  if (fs.existsSync(resultFilePath)) {
    testRetries = JSON.parse(fs.readFileSync(resultFilePath, 'utf-8'));
  }

  // Check if the test has retries
  if (test.attempts && test.attempts.length > 1) {
    const testTitle = test.title.join(' > '); // Convert the array to a string
    testRetries[testTitle] = true;
  }

  // Save updated testRetries object to a file
  fs.writeFileSync(resultFilePath, JSON.stringify(testRetries, null, 2));
});
