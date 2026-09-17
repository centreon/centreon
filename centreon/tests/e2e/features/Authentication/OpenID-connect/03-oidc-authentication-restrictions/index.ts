import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { INTERCEPTORS } from 'fixtures/shared/constants/interceptors';
import { PAGES } from 'fixtures/shared/constants/pages';

import { configureProviderAcls } from '../../../../commons';
import {
  configureOpenIdConnect,
  initializeOidcUserAndGetLoginPage
} from '../common';

before(() => {
  cy.startContainers({ profiles: ['openid'] }).then(() => {
    configureProviderAcls();
    initializeOidcUserAndGetLoginPage();
  });
});

beforeEach(() => {
  cy.intercept({
    method: 'GET',
    url: INTERCEPTORS.api.navigation_list
  }).as('getNavigationList');
  cy.intercept({
    method: 'GET',
    url: `${INTERCEPTORS.api.authentication_provider}/openid`
  }).as('getOIDCProvider');
  cy.intercept({
    method: 'PUT',
    url: `${INTERCEPTORS.api.authentication_provider}/openid`
  }).as('updateOIDCProvider');
  cy.intercept({
    method: 'POST',
    url: INTERCEPTORS.api.local_authentication
  }).as('postLocalAuthentification');
});

Given('an administrator is logged on the platform', () => {
  cy.loginByTypeOfUser({ jsonName: 'admin' })
    .wait('@postLocalAuthentification')
    .its('response.statusCode')
    .should('eq', 200)
    .visit(PAGES.configuration.authentication)
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

    configureOpenIdConnect();

    // authentication conditions section
    cy.get('[data-testid="Authentication conditions-header"]').click();
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
