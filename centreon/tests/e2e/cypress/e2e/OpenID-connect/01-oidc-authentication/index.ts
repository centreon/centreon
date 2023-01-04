import { Given, When, Then } from '@badeball/cypress-cucumber-preprocessor';

import {
  configureOpenIDConnect,
  initializeOIDCUserAndGetLoginPage,
  removeContact
} from '../common';

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
  cy.intercept({
    method: 'POST',
    url: '/centreon/api/latest/authentication/providers/configurations/local'
  }).as('localAuthentification');
});

Given('an administrator is logged on the platform', () => {
  cy.visit(`${Cypress.config().baseUrl}`);
  cy.loginByTypeOfUser({ jsonName: 'admin', preserveToken: true });
});

When(
  'the administrator sets valid settings in the OpenID Connect configuration form and saves the form',
  () => {
    cy.wait('@getNavigationList')
      .navigateTo({
        page: 'Authentication',
        rootItemNumber: 4
      })
      .get('div[role="tablist"] button:nth-child(2)')
      .click()
      .wait('@getOIDCResponse');
    cy.getByLabel({ label: 'Identity provider' }).click();
    configureOpenIDConnect();
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

When('the administrator configures the authentication mode', () => {
  cy.wait('@getNavigationList')
    .navigateTo({
      page: 'Authentication',
      rootItemNumber: 4
    })
    .get('div[role="tablist"] button:nth-child(2)')
    .click();
});

Then(
  'default authentication mode must be Mixed and users created locally to centreon platform must be able to authenticate',
  () => {
    cy.getByLabel({
      label: 'Mixed',
      tag: 'input'
    })
      .should('be.checked')
      .and('have.value', 'false')
      .logout()
      .reload()
      .loginByTypeOfUser({
        jsonName: 'user-non-admin-for-OIDC-authentication',
        preserveToken: true
      })
      .wait('@localAuthentification')
      .its('response.statusCode')
      .should('eq', 200)
      .wait('@getNavigationList')
      .logout()
      .reload();
  }
);

Given('an administrator is relogged on the platform', () => {
  cy.visit(`${Cypress.config().baseUrl}`);
  cy.loginByTypeOfUser({ jsonName: 'admin', preserveToken: true })
    .wait('@getNavigationList')
    .navigateTo({
      page: 'Authentication',
      rootItemNumber: 4
    })
    .get('div[role="tablist"] button:nth-child(2)')
    .click()
    .wait('@getOIDCResponse');
});

When(
  'the administrator activates OpenID Connect authentication on the platform',
  () => {
    cy.getByLabel({
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
    cy.session('AUTH_SESSION_ID_LEGACY', () => {
      cy.visit(`${Cypress.config().baseUrl}`);
      cy.get('a').click();
      cy.loginKeycloack('user-non-admin-for-OIDC-authentication')
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
});
