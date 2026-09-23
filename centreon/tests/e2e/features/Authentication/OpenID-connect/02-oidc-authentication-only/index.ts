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
});

Given('an administrator is logged on the platform', () => {
  cy.loginByTypeOfUser({ jsonName: 'admin' });
});

When(
  'the administrator sets authentication mode to OpenID Connect only',
  () => {
    cy.visit(PAGES.configuration.authentication)
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

    configureOpenIdConnect();

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

    cy.contains('Invalid username or password.');

    cy.loginKeycloak(username);
    cy.url().should('include', '/monitoring/resources');
  }
);

after(() => {
  cy.stopContainers();
});
