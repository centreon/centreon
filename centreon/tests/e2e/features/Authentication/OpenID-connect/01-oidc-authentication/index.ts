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
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/latest/users/filters/events-view?page=1&limit=100'
  }).as('getFilters');
});

Given('an administrator is logged on the platform', () => {
  cy.loginByTypeOfUser({ jsonName: 'admin' });
});

When(
  'the administrator sets valid settings in the OpenID Connect configuration form and saves the form',
  () => {
    cy.navigateTo({
      page: 'Authentication',
      rootItemNumber: 4
    })
      .get('div[role="tablist"] button:nth-child(2)')
      .click();

    cy.wait('@getOIDCProvider');

    configureOpenIDConnect();
  }
);

Then('the configuration is saved and secrets are not visible', () => {
  cy.getByLabel({ label: 'save button', tag: 'button' }).click();

  cy.wait('@updateOIDCProvider')
    .its('response.statusCode')
    .should('eq', 204)
    .getByLabel({ label: 'Client secret', tag: 'input' })
    .should('have.attr', 'type', 'password')
    .logout();

  cy.getByLabel({ label: 'Alias', tag: 'input' }).should('exist');
});

When('the administrator configures the authentication mode', () => {
  cy.navigateTo({
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
      .and('have.value', 'false');

    cy.logout();

    cy.getByLabel({ label: 'Alias', tag: 'input' }).should('exist');

    cy.loginByTypeOfUser({
      jsonName: 'user-non-admin-for-OIDC-authentication'
    })
      .wait('@postLocalAuthentification')
      .its('response.statusCode')
      .should('eq', 200);
  }
);

Given('an administrator is relogged on the platform', () => {
  cy.loginByTypeOfUser({ jsonName: 'admin', loginViaApi: true })
    .navigateTo({
      page: 'Authentication',
      rootItemNumber: 4
    })
    .get('div[role="tablist"] button:nth-child(2)')
    .click();

  cy.wait('@getOIDCProvider');
});

When(
  'the administrator activates OpenID Connect authentication on the platform',
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

    cy.getByLabel({ label: 'save button', tag: 'button' }).click();

    cy.wait('@updateOIDCProvider')
      .its('response.statusCode')
      .should('eq', 204)
      .getByLabel({
        label: 'Enable OpenID Connect authentication',
        tag: 'input'
      })
      .should('be.checked')
      .and('have.value', 'on')
      .logout();

    cy.getByLabel({ label: 'Alias', tag: 'input' }).should('exist');
  }
);

Then(
  'any user can authenticate using the authentication provider that is configured',
  () => {
    const username = 'user-non-admin-for-OIDC-authentication';

    cy.visit('/');
    cy.contains('Login with openid').should('be.visible').click();
    cy.loginKeycloak(username);

    cy.url().should('include', '/monitoring/resources');
    cy.wait('@getFilters').its('response.statusCode').should('eq', 200);
  }
);

after(() => {
  cy.stopContainers();
});
