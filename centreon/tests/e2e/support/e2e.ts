/* eslint-disable import/newline-after-import */
/* eslint-disable @typescript-eslint/no-var-requires */
/* eslint-disable @typescript-eslint/no-unused-vars */
import 'cypress-wait-until';
import 'cypress-real-events';
import 'cypress-on-fix';

import './commands';

interface RetryTestInfo {
  testName: string;
  featureName: string;
}


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

// Cypress.on('test:after:run', (test, runnable) => {
//   if (test.state === 'failed' && test.retries > 0) {
//     const testName = test.title;
//     const featureName = runnable.parent?.title || '';

//     const retryTestInfo: RetryTestInfo = {
//       testName,
//       featureName,
//     };

//     cy.task('writeRetryInfo', { retryTestInfo })
//       .then(() => {
//         console.log('Retry information stored successfully');
//       });
//   }
// });