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
    .click()
    .wait('@getOIDCResponse');
});

When(
  'the administrator sets authentication mode to OpenID Connect only',
  () => {
    cy.getByLabel({
      label: 'Enable OpenID Connect authentication',
      tag: 'input'
    })
      .check()
      .getByLabel({
        label: 'OpenID Connect only',
        tag: 'input'
      })
      .check()
      .getByLabel({ label: 'save button', tag: 'button' })
      .click()
      .wait('@updateOIDCResponse')
      .its('response.statusCode')
      .should('eq', 204)
      .getByLabel({
        label: 'OpenID Connect only',
        tag: 'input'
      })
      .should('be.checked')
      .and('have.value', 'true')
      .logout()
      .reload({ timeout: 6000 });
  }
);

Then(
  'only users created using the 3rd party authentication provide must be able to authenticate and local admin user must not be able to authenticate',
  () => {
    cy.loginKeycloack('admin')
      .get('#input-error')
      .should('be.visible')
      .and('include.text', 'Invalid username or password.')
      .loginKeycloack('user-non-admin-for-OIDC-authentication')
      .wait('@getNavigationList')
      .url()
      .should('include', '/monitoring/resources');
  }
);

after(() => {
  removeContact();
});
