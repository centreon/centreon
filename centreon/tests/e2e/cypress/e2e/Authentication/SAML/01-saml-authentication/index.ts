/* eslint-disable cypress/unsafe-to-chain-command */
import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';

import {
  configureSAML,
  initializeSAMLUser,
  navigateToSAMLConfigPage
} from '../common';

before(() => {
  cy.startWebContainer()
    .startOpenIdProviderContainer()
    .then(() => {
      initializeSAMLUser();
    });
});

beforeEach(() => {
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/internal.php?object=centreon_topology&action=navigationList'
  }).as('getNavigationList');
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/latest/administration/authentication/providers/saml'
  }).as('getSAMLProvider');
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/latest/authentication/providers/configurations'
  }).as('getCentreonAuthConfigs');
  cy.intercept({
    method: 'PUT',
    url: '/centreon/api/latest/administration/authentication/providers/saml'
  }).as('updateSAMLProvider');
  cy.intercept({
    method: 'POST',
    url: '/centreon/api/latest/authentication/providers/configurations/local'
  }).as('postLocalAuthentification');
});

Given('an administrator is logged on the platform', () => {
  cy.loginByTypeOfUser({ jsonName: 'admin' });
});

When(
  'the administrator sets valid settings in the SAML configuration form and saves',
  () => {
    navigateToSAMLConfigPage();

    configureSAML();

    cy.getByLabel({ label: 'save button', tag: 'button' }).click();
  }
);

Then('the configuration is saved', () => {
  cy.wait('@updateSAMLProvider').its('response.statusCode').should('eq', 204);

  cy.logout();
});

When('the administrator first configures the authentication mode', () => {
  navigateToSAMLConfigPage();
});

Then(
  'default authentication mode must be Mixed and users created locally to centreon platform must be able to authenticate',
  () => {
    cy.getByLabel({
      label: 'Mixed',
      tag: 'input'
    })
      .should('be.checked')
      .and('have.value', 'false');

    cy.logout();

    cy.wait('@getCentreonAuthConfigs');

    cy.getByLabel({ label: 'Alias', tag: 'input' }).should('exist');

    cy.loginByTypeOfUser({
      jsonName: 'user-non-admin-for-SAML-authentication'
    })
      .wait('@postLocalAuthentification')
      .its('response.statusCode')
      .should('eq', 200);

    cy.logout();
  }
);

When('the administrator activates SAML authentication on the platform', () => {
  cy.navigateTo({
    page: 'Authentication',
    rootItemNumber: 4
  })
    .get('div[role="tablist"] button:nth-child(4)')
    .click();

  cy.getByLabel({
    label: 'Enable SAMLv2 authentication',
    tag: 'input'
  }).check();

  cy.getByLabel({ label: 'save button', tag: 'button' }).click();

  cy.wait('@updateSAMLProvider').its('response.statusCode').should('eq', 204);
});

Then(
  'any user can authenticate using the authentication provider that is configured',
  () => {
    cy.logout();

    cy.wait('@getCentreonAuthConfigs');

    const username = 'user-non-admin-for-SAML-authentication';

    cy.session(username, () => {
      cy.visit('/').getByLabel({ label: 'Login with SAML', tag: 'a' }).click();
      cy.loginKeycloack(username)
        .url()
        .should('include', '/monitoring/resources');
    });
  }
);

after(() => {
  cy.stopWebContainer().stopOpenIdProviderContainer();
});
