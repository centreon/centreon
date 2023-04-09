import 'cypress-wait-until';
import './Commands';

Cypress.on('uncaught:exception', (err) => {
  if (
    err.message.includes('Request failed with status code 401') ||
    err.message.includes('Request failed with status code 403') ||
    err.message.includes('undefined')
  ) {
    return false;
  }

  return true;
});
