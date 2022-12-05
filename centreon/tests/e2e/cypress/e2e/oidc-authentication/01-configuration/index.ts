import { Given, When, Then } from '@badeball/cypress-cucumber-preprocessor';

import { initializeOIDCUserAndGetLoginPage, removeContact } from '../common';

before(() => {
  initializeOIDCUserAndGetLoginPage();
});

beforeEach(() => {
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/internal.php?object=centreon_topology&action=navigationList'
  }).as('getNavigationList');
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/latest/administration/authentication/providers/openid'
  }).as('getOIDCResponse');
  cy.intercept({
    method: 'PUT',
    url: '/centreon/api/latest/administration/authentication/providers/openid'
  }).as('updateOIDCResponse');
});

Given('an administrator is logged in the platform', () => {
  cy.loginByTypeOfUser({ jsonName: 'admin', preserveToken: true })
    .wait('@getNavigationList')
    .navigateTo({
      page: 'Authentication',
      rootItemNumber: 4
    })
    .get('div[role="tablist"] button:nth-child(2)')
    .eq(0)
    .contains('OpenID Connect Configuration')
    .click();
});

When(
  'the administrator sets valid settings in the OpenID Connect configuration form and saves',
  () => {
    cy.wait('@getOIDCResponse')
      .getByLabel({ label: 'Identity provider' })
      .click()
      .getByLabel({ label: 'Base URL', tag: 'input' })
      .clear()
      .type(
        'http://10.25.11.254:8080/auth/realms/Centreon_SSO/protocol/openid-connect'
      )
      .getByLabel({ label: 'Authorization endpoint', tag: 'input' })
      .clear()
      .type('/auth')
      .getByLabel({ label: 'Token endpoint', tag: 'input' })
      .clear()
      .type('/token')
      .getByLabel({ label: 'Client ID', tag: 'input' })
      .clear()
      .type('centreon-oidc-frontend')
      .getByLabel({ label: 'Client secret', tag: 'input' })
      .clear()
      .type('IKbUBottl5eoyhf0I5Io2nuDsTA85D50')
      .getByLabel({ label: 'Login attribute path', tag: 'input' })
      .clear()
      .type('preferred_username')
      .getByLabel({ label: 'Introspection token endpoint', tag: 'input' })
      .clear()
      .type('/token/introspect')
      .getByLabel({
        label: 'Use basic authentication for token endpoint authentication',
        tag: 'input'
      })
      .uncheck()
      .getByLabel({ label: 'Disable verify peer', tag: 'input' })
      .check()
      .getByLabel({ label: 'save button', tag: 'button' })
      .click();
  }
);

Then('the configuration is saved and secrets are not visible', () => {
  cy.wait('@updateOIDCResponse')
    .its('response.statusCode')
    .should('eq', 204)
    .getByLabel({ label: 'Client secret', tag: 'input' })
    .should('have.attr', 'type', 'password')
    .logout()
    .reload();
});

after(() => {
  removeContact();
});
