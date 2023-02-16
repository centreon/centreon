  import { Given, When, Then } from '@badeball/cypress-cucumber-preprocessor';

import {
  initializeOIDCUserAndGetLoginPage,
  removeContact,
  configureOpenIDConnect
} from '../common';

before(() => {
  cy.startOpenIdProviderContainer().then(() => {
    initializeOIDCUserAndGetLoginPage();
  });
});

beforeEach(() => {
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/internal.php?object=centreon_topology&action=navigationList'
  }).as('getNavigationList');
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/latest/administration/authentication/providers/openid'
  }).as('getOIDCProvider');
  cy.intercept({
    method: 'PUT',
    url: '/centreon/api/latest/administration/authentication/providers/openid'
  }).as('updateOIDCProvider');
  cy.intercept({
    method: 'POST',
    url: '/centreon/api/latest/authentication/providers/configurations/local'
  }).as('postLocalAuthentification');
});

Given('an administrator is relogged on the platform', () => {
  cy.loginByTypeOfUser({ jsonName: 'admin', preserveToken: true })
    .wait('@postLocalAuthentification')
    .its('response.statusCode')
    .should('eq', 200)
    .navigateTo({
      page: 'Authentication',
      rootItemNumber: 4
    })
    .get('div[role="tablist"] button:nth-child(2)')
    .click()
    .wait('@getOIDCProvider');
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
      .check();
    cy.getByLabel({ label: 'Identity provider' })
      .eq(0)
      .contains('Identity provider')
      .click();
    configureOpenIDConnect();
    cy.getByLabel({ label: 'save button', tag: 'button' })
      .click()
      .wait('@updateOIDCProvider')
      .its('response.statusCode')
      .should('eq', 204);
    cy.getByLabel({
      label: 'OpenID Connect only',
      tag: 'input'
    })
      .should('be.checked')
      .and('have.value', 'true')
      .logout();
  }
);

Then(
  'only users created using the 3rd party authentication provide must be able to authenticate and local admin user must not be able to authenticate',
  () => {
    cy.session('AUTH_SESSION_ID_LEGACY', () => {
      cy.visit(`${Cypress.config().baseUrl}`);
      cy.loginKeycloack('admin')
        .get('#input-error')
        .should('be.visible')
        .and('include.text', 'Invalid username or password.')
        .loginKeycloack('user-non-admin-for-OIDC-authentication')
        .wait('@getNavigationList')
        .url()
        .should('include', '/monitoring/resources')
        .logout()
        .reload();
    });
  }
);

after(() => {
  removeContact();
  cy.stopOpenIdProviderContainer();
});
