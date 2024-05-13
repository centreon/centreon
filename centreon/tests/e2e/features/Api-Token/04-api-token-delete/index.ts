import { Given, When, Then } from '@badeball/cypress-cucumber-preprocessor';
import { Contact, Token, durationMap } from '../common';
import tokens from '../../../fixtures/api-token/tokens.json';

const tokenToDelete = tokens.Token_1.name;

beforeEach(() => {
  cy.startContainers();

  cy.intercept({
    method: 'GET',
    url: '/centreon/api/internal.php?object=centreon_topology&action=navigationList'
  }).as('getNavigationList');
  cy.intercept({
    method: 'GET',
    url: 'centreon/api/latest/administration/tokens?*'
  }).as('getTokens');

  cy.fixture('api-token/users.json').then((users: Record<string, Contact>) => {
    Object.values(users).forEach((user) => {
      cy.addContact(user);
    });
  });
});

afterEach(() => {
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

  cy.getByLabel({ label: 'Refresh', tag: 'button' }).click();
  cy.wait('@getTokens');
});

When('I locate the API token to delete', () => {
  cy.get('.MuiTableBody-root .MuiTableRow-root')
    .contains(tokenToDelete)
    .parent()
    .parent()
    .as('tokenRowToDelete');
});

When('I click on the "delete token" icon for that token', () => {
  cy.get('@tokenRowToDelete').within(() => {
    cy.getByLabel({ label: 'Delete', tag: 'button' }).click();
  });
});

When('I confirm the deletion in the confirmation dialog', () => {
  cy.getByTestId({ testId: 'Confirm', tag: 'button' }).click();
});

Then('the token is deleted successfully', () => {
  cy.wait('@getTokens');
  cy.get('.MuiTableBody-root .MuiTableRow-root')
    .contains(tokenToDelete)
    .should('not.exist');
});

When('I cancel the deletion in the confirmation dialog', () => {
  cy.getByTestId({ testId: 'Cancel', tag: 'button' }).click();
});

Then('the deletion action is cancelled', () => {
  cy.get('.MuiTableBody-root .MuiTableRow-root')
    .contains(tokenToDelete)
    .should('exist');
});
