import { Given, When, Then } from '@badeball/cypress-cucumber-preprocessor';

import {
  configureOpenIDConnect,
  initializeOIDCUserAndGetLoginPage
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
  cy.intercept({
    method: 'POST',
    url: '/centreon/api/latest/authentication/providers/configurations/local'
  }).as('postLocalAuthentification');
});

Given('an administrator is logged on the platform', () => {
  cy.loginByTypeOfUser({ jsonName: 'admin' })
    .wait('@postLocalAuthentification')
    .its('response.statusCode')
    .should('eq', 200)
    .navigateTo({
      page: 'Authentication',
      rootItemNumber: 4
    })
    .get('div[role="tablist"] button:nth-child(2)')
    .click();

  cy.wait('@getOIDCProvider');
});

When(
  'the administrator sets valid settings in the Authentication conditions and saves',
  () => {
    cy.getByLabel({
      label: 'Enable OpenID Connect authentication',
      tag: 'input'
    }).check();

    configureOpenIDConnect();

    // authentication conditions section
    cy.getByLabel({ label: 'Authentication conditions' }).click();
    cy.getByLabel({ label: 'Blacklist client addresses' }).type(
      '{selectall}{backspace}127.0.0.1{enter}'
    );
    cy.getByLabel({
      label: 'Conditions attribute path',
      tag: 'input'
    }).type('{selectall}{backspace}preferred_username');
    cy.getByLabel({
      label: 'Enable conditions on identity provider',
      tag: 'input'
    }).check();
    cy.getByLabel({
      label: 'Introspection endpoint',
      tag: 'input'
    })
      .should('be.checked')
      .and('have.value', 'introspection_endpoint');
    cy.getByLabel({
      label: 'Condition value',
      tag: 'input'
    }).type('{selectall}{backspace}oidc');

    cy.getByLabel({ label: 'save button', tag: 'button' }).click();
    cy.wait('@updateOIDCProvider').its('response.statusCode').should('eq', 204);

    cy.logout();
  }
);

Then(
  'only users with the valid authentication conditions can access the platform',
  () => {
    cy.visit('/');
    cy.contains('Login with openid').should('be.visible').click();
    cy.loginKeycloak('user-non-admin-for-OIDC-authentication');
    cy.url().should('include', '/monitoring/resources');
  }
);

after(() => {
  cy.stopContainers();
});
