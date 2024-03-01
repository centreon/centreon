import { Given, When, Then } from '@badeball/cypress-cucumber-preprocessor';

import {
  initializeOIDCUserAndGetLoginPage,
  configureOpenIDConnect
} from '../common';
import { configureProviderAcls } from '../../../../commons';

before(() => {
  cy.startContainers({ profiles: ['openid'] }).then(() => {
    configureProviderAcls();
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
});

Given('an administrator is logged on the platform', () => {
  cy.loginByTypeOfUser({ jsonName: 'admin' });
});

When(
  'the administrator sets authentication mode to OpenID Connect only',
  () => {
    cy.navigateTo({
      page: 'Authentication',
      rootItemNumber: 4
    })
      .get('div[role="tablist"] button:nth-child(2)')
      .click();

    cy.wait('@getOIDCProvider')
      .getByLabel({
        label: 'Enable OpenID Connect authentication',
        tag: 'input'
      })
      .check();

    cy.getByLabel({
      label: 'OpenID Connect only',
      tag: 'input'
    }).check();

    configureOpenIDConnect();

    cy.getByLabel({ label: 'save button', tag: 'button' }).click();

    cy.wait('@updateOIDCProvider')
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

    cy.visit('/');

    cy.loginKeycloak('admin');

    cy.get('#input-error')
      .should('be.visible')
      .and('include.text', 'Invalid username or password.');

    cy.loginKeycloak(username);
    cy.url().should('include', '/monitoring/resources');
  }
);

after(() => {
  cy.stopContainers();
});
