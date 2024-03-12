import { Given, When, Then } from '@badeball/cypress-cucumber-preprocessor';
import { Contact, Token, durationMap } from '../common';

before(() => {
  cy.startContainers();

  cy.fixture('api-token/users.json').then((users: Record<string, Contact>) => {
    Object.values(users).forEach((user) => {
      cy.addContact(user);
    });
  });
});

beforeEach(() => {
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/internal.php?object=centreon_topology&action=navigationList'
  }).as('getNavigationList');
  cy.intercept({
    method: 'GET',
    url: 'centreon/api/latest/administration/tokens?*'
  }).as('getTokens');
});

after(() => {
  cy.stopContainers();
});

Given('I am logged in as an administrator', () => {
  cy.loginByTypeOfUser({ jsonName: 'admin' });
});

Given('API tokens with predefined details are created', () => {
  cy.fixture('api-token/tokens.json').then((tokens: Record<string, Token>) => {
    Object.values(tokens).forEach((token) => {
      const today = new Date();
      const expirationDate = new Date(today);
      const duration = durationMap[token.duration];
      expirationDate.setDate(today.getDate() + duration);
      const expirationDateISOString = expirationDate.toISOString();

      const payload = {
        expiration_date: expirationDateISOString,
        name: token.name,
        user_id: token.userId
      };
      cy.request({
        method: 'POST',
        url: '/centreon/api/latest/administration/tokens',
        body: payload,
        headers: {
          'Content-Type': 'application/json'
        }
      }).then((response) => {
        expect(response.status).to.eq(201);
      });
    });
  });
});

Given('I am on the API tokens page', () => {
  cy.navigateTo({
    page: 'API Tokens',
    rootItemNumber: 4
  });

  cy.wait('@getTokens');
});

When('I click on the {string} column header', (columnHeader: string) => {
  cy.contains(columnHeader).click();
});

Then('the tokens are sorted by {string} in ascending order', (orderBy) => {
  cy.wait(10000);
  // Implement steps to verify tokens are sorted by the specified column in ascending order
});
