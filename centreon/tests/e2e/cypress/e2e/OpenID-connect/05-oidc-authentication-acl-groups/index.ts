import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';

import {
  configureOpenIDConnect,
  getAccessGroupId,
  initializeOIDCUserAndGetLoginPage,
  removeContact
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
    url: '/centreon/include/common/userTimezone.php'
  }).as('getTimeZone');
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
    url: '/centreon/api/latest/configuration/access-groups?page=1&sort_by=%7B%22name%22%3A%22ASC%22%7D&search=%7B%22%24and%22%3A%5B%5D%7D'
  }).as('getListAccesGroup');
});

Given('an administrator is logged in the platform', () => {
  cy.loginByTypeOfUser({ jsonName: 'admin' })
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
  'the administrator sets valid settings in the Roles mapping and saves',
  () => {
    cy.getByLabel({
      label: 'Enable OpenID Connect authentication',
      tag: 'input'
    }).check({ force: true });
    cy.getByLabel({ label: 'Identity provider' })
      .eq(0)
      .contains('Identity provider')
      .click({ force: true });
    configureOpenIDConnect();
    cy.getByLabel({ label: 'Roles mapping' })
      .eq(0)
      .contains('Roles mapping')
      .click({ force: true });
    cy.getByLabel({
      label: 'Enable automatic management',
      tag: 'input'
    })
      .eq(0)
      .check();
    cy.getByLabel({
      label: 'Roles attribute path',
      tag: 'input'
    })
      .clear()
      .type('realm_access.roles');
    cy.getByLabel({
      label: 'Introspection endpoint',
      tag: 'input'
    })
      .should('be.checked')
      .and('have.value', 'introspection_endpoint');
    cy.getByLabel({
      label: 'Role value',
      tag: 'input'
    })
      .clear()
      .type('centreon-editor');
    cy.getByLabel({
      label: 'ACL access group',
      tag: 'input'
    })
      .click({ force: true })
      .wait('@getListAccesGroup')
      .get('div[role="presentation"] ul li')
      .click()
      .getByLabel({
        label: 'ACL access group',
        tag: 'input'
      })
      .should('have.value', 'ALL');
    cy.getByLabel({ label: 'save button', tag: 'button' })
      .click()
      .wait('@updateOIDCProvider')
      .its('response.statusCode')
      .should('eq', 204);
  }
);

Then(
  'the users from the 3rd party authentication service are affected to ACL groups',
  () => {
    cy.session('AUTH_SESSION_ID_LEGACY', () => {
      cy.visit(`${Cypress.config().baseUrl}`);
      cy.get('a').click();
      cy.loginKeycloack('user-non-admin-for-OIDC-authentication')
        .url()
        .should('include', '/monitoring/resources')
        .logout()
        .reload();
    });
    cy.loginByTypeOfUser({ jsonName: 'admin' })
      .wait('@postLocalAuthentification')
      .its('response.statusCode')
      .should('eq', 200);
    getAccessGroupId('ALL').then((groupId) => {
      cy.visit(`/centreon/main.php?p=50203&o=c&acl_group_id=${groupId}`)
        .wait('@getTimeZone')
        .getIframeBody()
        .find('form')
        .within(() => {
          cy.get('select[name="cg_contacts-t[]"]').contains('oidc');
        });
    });
  }
);

after(() => {
  removeContact();
  cy.stopOpenIdProviderContainer();
});
