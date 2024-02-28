import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';

import { initializeWebSSOUserAndGetLoginPage } from '../common';

before(() => {
  cy.startContainers();

  initializeWebSSOUserAndGetLoginPage();
});

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
    method: 'PUT',
    url: '/centreon/api/latest/administration/authentication/providers/web-sso'
  }).as('updateWebSSOProvider');
  cy.intercept({
    method: 'POST',
    url: '/centreon/api/latest/authentication/providers/configurations/local'
  }).as('postLocalAuthentification');
});

Given('an administrator logged in the platform', () => {
  cy.loginByTypeOfUser({ jsonName: 'admin' })
    .wait('@postLocalAuthentification')
    .its('response.statusCode')
    .should('eq', 200);
});

When('the administrator sets authentication mode to Web SSO only', () => {
  cy.navigateTo({
    page: 'Authentication',
    rootItemNumber: 4
  })
    .get('div[role="tablist"] button:nth-child(3)')
    .click();
  cy.wait('@getWebSSOProvider');
  cy.getByLabel({
    label: 'Enable Web SSO authentication',
    tag: 'input'
  }).check();
  cy.getByLabel({ label: 'Web SSO only', tag: 'input' }).check();
  cy.getByLabel({ label: 'Login header attribute name', tag: 'input' }).type(
    '{selectall}{backspace}REMOTE_USER'
  );
  cy.getByLabel({ label: 'save button', tag: 'button' }).click();
  cy.wait('@updateWebSSOProvider')
    .its('response.statusCode')
    .should('eq', 204)
    .logout();
});

Then('users and local admin user must not be able to authenticate', () => {
  // TODO: - Test the authentication via the provider. Check out this issue for more information: https://github.com/cypress-io/cypress/issues/17701
  // cy.session('AUTH_SESSION_ID_LEGACY', () => {
  //   cy.visit(`${Cypress.config().baseUrl}`);
  //   cy.loginKeycloak('admin')
  //     .get('#input-error')
  //     .should('be.visible')
  //     .and('include.text', 'Invalid username or password.');
  // });
});

after(() => {
  cy.stopContainers();
});
