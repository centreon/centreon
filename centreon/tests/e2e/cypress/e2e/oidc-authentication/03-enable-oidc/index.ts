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

Given('an administrator logged in the platform', () => {
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
  'the administrator activates OpenID Connect authentication on the platform',
  () => {
    cy.wait('@getOIDCResponse')
      .getByLabel({
        label: 'Enable OpenID Connect authentication',
        tag: 'input'
      })
      .check()
      .getByLabel({ label: 'save button', tag: 'button' })
      .click()
      .wait('@updateOIDCResponse')
      .its('response.statusCode')
      .should('eq', 204)
      .getByLabel({
        label: 'Enable OpenID Connect authentication',
        tag: 'input'
      })
      .should('be.checked')
      .and('have.value', 'on')
      .logout()
      .reload();
  }
);

Then(
  'any user can authenticate using the authentication provider that is configured',
  () => {
    cy.get('a')
      .click()
      .loginKeycloack('user-non-admin-for-OIDC-authentication')
      .wait('@getNavigationList')
      .url()
      .should('include', '/monitoring/resources');
  }
);

after(() => {
  removeContact();
});
