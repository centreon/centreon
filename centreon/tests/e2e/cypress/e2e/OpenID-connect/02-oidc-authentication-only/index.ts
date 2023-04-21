import { Given, When, Then } from '@badeball/cypress-cucumber-preprocessor';

import {
  initializeOIDCUserAndGetLoginPage,
  removeContact,
  configureOpenIDConnect
} from '../common';

before(() => {
  cy
    .startContainer({
      name: Cypress.env('dockerName'),
      os: 'alma9',
      version: 'MON-17315-platform-update-automation'
    })
    .startOpenIdProviderContainer()
    .then(() => {
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

Given('an administrator is logged on the platform', () => {
  cy.loginByTypeOfUser({ jsonName: 'admin' });
});

When(
  'the administrator sets authentication mode to OpenID Connect only',
  () => {
    cy
      .navigateTo({
        page: 'Authentication',
        rootItemNumber: 4
      })
      .get('div[role="tablist"] button:nth-child(2)')
      .click();

    cy
      .wait('@getOIDCProvider')
      .getByLabel({
        label: 'Enable OpenID Connect authentication',
        tag: 'input'
      })
      .check()
      .getByLabel({
        label: 'OpenID Connect only',
        tag: 'input'
      })
      .check()
      .getByLabel({ label: 'Identity provider' })
      .eq(0)
      .contains('Identity provider')
      .click({ force: true });

    configureOpenIDConnect();

    cy.getByLabel({ label: 'save button', tag: 'button' })
      .click({ force: true })
      .wait('@updateOIDCProvider')
      .its('response.statusCode')
      .should('eq', 204)
      .getByLabel({
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
    const username = 'user-non-admin-for-OIDC-authentication';

    cy.session(`wrong_${username}`, () => {
      cy.visit('/');

      cy
        .loginKeycloack('admin')
        .get('#input-error')
        .should('be.visible')
        .and('include.text', 'Invalid username or password.')
        .loginKeycloack(username)

      cy
        .url()
        .should('include', '/monitoring/resources');
    });
  }
);

after(() => {
  cy
    .visitEmptyPage()
    .stopContainer(Cypress.env('dockerName'))
    .stopOpenIdProviderContainer();
});
