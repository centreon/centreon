import 'cypress-wait-until';
import './commands';

Cypress.on('uncaught:exception', (err) => {
  if (
    err.message.includes('Request failed with status code 401') ||
    err.message.includes('Request failed with status code 403') ||
    err.message.includes('undefined') ||
    err.message.includes('canceled')
  ) {
    return false;
  }

  return true;
});
