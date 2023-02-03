import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';

beforeEach(() => {
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/internal.php?object=centreon_topology&action=navigationList'
  }).as('getNavigationList');
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/latest/administration/authentication/providers/web-sso'
  }).as('getWebSSOResponse');
  cy.intercept({
    method: 'PUT',
    url: '/centreon/api/latest/administration/authentication/providers/web-sso'
  }).as('updateWebSSOResponse');
  cy.intercept({
    method: 'POST',
    url: '/centreon/api/latest/authentication/providers/configurations/local'
  }).as('localAuthentification');
});

Given('an administrator logged in the platform', () => {
  cy.session('AUTH_SESSION_ID_LEGACY', () => {
    cy.visit(`${Cypress.config().baseUrl}`);
    cy.loginKeycloack('user-for-web-sso-authentication');
  });
  cy.loginByTypeOfUser({ jsonName: 'admin', preserveToken: true })
    .wait('@localAuthentification')
    .its('response.statusCode')
    .should('eq', 200);
});

When('the administrator sets authentication mode to Web SSO only', () => {
  cy.navigateTo({
    page: 'Authentication',
    rootItemNumber: 4
  })
    .get('div[role="tablist"] button:nth-child(3)')
    .click()
    .wait('@getWebSSOResponse');
  cy.getByLabel({
    label: 'Enable Web SSO authentication',
    tag: 'input'
  }).check();
  cy.getByLabel({ label: 'Web SSO only', tag: 'input' }).check();
  cy.getByLabel({ label: 'Login header attribute name', tag: 'input' })
    .clear()
    .type('REMOTE_USER');
  cy.getByLabel({ label: 'save button', tag: 'button' })
    .click()
    .wait('@updateWebSSOResponse')
    .its('response.statusCode')
    .should('eq', 204);
});

Then('users and local admin user must not be able to authenticate', () => {
  cy.session('AUTH_SESSION_ID_LEGACY', () => {
    cy.loginKeycloack('admin')
      .get('#input-error')
      .should('be.visible')
      .and('include.text', 'Invalid username or password.');
  });
});
