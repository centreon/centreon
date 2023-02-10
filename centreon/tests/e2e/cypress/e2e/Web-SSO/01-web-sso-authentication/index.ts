import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';

beforeEach(() => {
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/internal.php?object=centreon_topology&action=navigationList'
  }).as('getNavigationList');
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/latest/administration/authentication/providers/web-sso'
  }).as('getWebSSOProvider');
  cy.intercept({
    method: 'POST',
    url: '/centreon/api/latest/authentication/providers/configurations/local'
  }).as('postLocalAuthentification');
});

Given('an administrator logged in the platform', () => {
  cy.visit(`${Cypress.config().baseUrl}`);
  cy.loginByTypeOfUser({ jsonName: 'admin', preserveToken: true })
    .wait('@postLocalAuthentification')
    .its('response.statusCode')
    .should('eq', 200);
});

When('the administrator first configures the authentication mode', () => {
  cy.navigateTo({
    page: 'Authentication',
    rootItemNumber: 4
  })
    .get('div[role="tablist"] button:nth-child(3)')
    .click()
    .wait('@getWebSSOProvider');
});

Then(
  'default authentication mode must be Mixed and users created locally to centreon platform must be able to authenticate',
  () => {
    cy.getByLabel({ label: 'Mixed', tag: 'input' })
      .should('be.checked')
      .and('have.value', 'false');
    cy.logout().reload();
    cy.loginByTypeOfUser({ jsonName: 'admin', preserveToken: true })
      .wait('@postLocalAuthentification')
      .its('response.statusCode')
      .should('eq', 200);
  }
);
