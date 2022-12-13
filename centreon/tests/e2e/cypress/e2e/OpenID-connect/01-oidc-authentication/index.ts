import { Given, When, Then } from '@badeball/cypress-cucumber-preprocessor';

import {
  initializeOIDCUserAndGetLoginPage,
  oidcConfigValues,
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
  'the administrator sets valid settings in the OpenID Connect configuration form and saves the form',
  () => {
    cy.wait('@getOIDCResponse');
    cy.getByLabel({ label: 'Identity provider' }).click();
    cy.getByLabel({ label: 'Base URL', tag: 'input' })
      .clear()
      .type(oidcConfigValues.baseUrl);
    cy.getByLabel({ label: 'Authorization endpoint', tag: 'input' })
      .clear()
      .type(oidcConfigValues.authEndpoint);
    cy.getByLabel({ label: 'Token endpoint', tag: 'input' })
      .clear()
      .type(oidcConfigValues.tokenEndpoint);
    cy.getByLabel({ label: 'Client ID', tag: 'input' })
      .clear()
      .type(oidcConfigValues.clientID);
    cy.getByLabel({ label: 'Client secret', tag: 'input' })
      .clear()
      .type(oidcConfigValues.clientSecret);
    cy.getByLabel({ label: 'Login attribute path', tag: 'input' })
      .clear()
      .type(oidcConfigValues.loginAttrPath);
    cy.getByLabel({ label: 'Introspection token endpoint', tag: 'input' })
      .clear()
      .type(oidcConfigValues.introspectionTokenEndpoint);
    cy.getByLabel({
      label: 'Use basic authentication for token endpoint authentication',
      tag: 'input'
    }).uncheck();
    cy.getByLabel({ label: 'Disable verify peer', tag: 'input' }).check();
    cy.getByLabel({ label: 'save button', tag: 'button' }).click();
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
  cy.navigateTo({
    page: 'Authentication',
    rootItemNumber: 4
  })
    .get('div[role="tablist"] button:nth-child(2)')
    .eq(0)
    .contains('OpenID Connect Configuration')
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
    .eq(0)
    .contains('OpenID Connect Configuration')
    .click();
});

When(
  'the administrator activates OpenID Connect authentication on the platform',
  () => {
    cy.navigateTo({
      page: 'Authentication',
      rootItemNumber: 4
    })
      .get('div[role="tablist"] button:nth-child(2)')
      .eq(0)
      .contains('OpenID Connect Configuration')
      .click()
      .wait('@getOIDCResponse')
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
    cy.session('AUTH_SESSION_ID_LEGACY', () => {
      cy.visit(`${Cypress.config().baseUrl}`);
      cy.contains('Login with openid')
        .click()
        .loginKeycloack('user-non-admin-for-OIDC-authentication')
        .wait('@getNavigationList')
        .url()
        .should('include', '/monitoring/resources')
        .logout()
        .reload();
    });
  }
);

When(
  'the administrator sets authentication mode to OpenID Connect only',
  () => {
    cy.navigateTo({
      page: 'Authentication',
      rootItemNumber: 4
    })
      .get('div[role="tablist"] button:nth-child(2)')
      .eq(0)
      .contains('OpenID Connect Configuration')
      .click()
      .wait('@getOIDCResponse')
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
    Cypress.session.clearAllSavedSessions();
    cy.session('AUTH_SESSION_ID_LEGACY_2', () => {
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
});
