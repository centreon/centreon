import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';

import {
  configureOpenIDConnect,
  initializeOIDCUserAndGetLoginPage
} from '../common';
import { configureProviderAcls, getAccessGroupId } from '../../../../commons';

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
  }).as('getListAccessGroup');
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
    .click();

  cy.wait('@getOIDCProvider');
});

When(
  'the administrator sets valid settings in the Roles mapping and saves',
  () => {
    configureOpenIDConnect();

    cy.getByLabel({ label: 'Roles mapping' }).click();
    cy.getByLabel({
      label: 'Enable OpenID Connect authentication',
      tag: 'input'
    }).check();
    cy.getByLabel({
      label: 'Enable automatic management',
      tag: 'input'
    })
      .eq(0)
      .check();
    cy.getByLabel({
      label: 'Roles attribute path',
      tag: 'input'
    }).type('{selectall}{backspace}realm_access.roles');
    cy.getByLabel({
      label: 'Introspection endpoint',
      tag: 'input'
    })
      .should('be.checked')
      .and('have.value', 'introspection_endpoint');
    cy.getByLabel({
      label: 'Role value',
      tag: 'input'
    }).type('{selectall}{backspace}centreon-editor');
    cy.getByLabel({
      label: 'ACL access group',
      tag: 'input'
    }).click();

    cy.wait('@getListAccessGroup')
      .get('div[role="presentation"] ul li')
      .contains('ALL')
      .click();

    cy.getByLabel({
      label: 'ACL access group',
      tag: 'input'
    }).should('have.value', 'ALL');

    cy.getByLabel({ label: 'save button', tag: 'button' }).click();
    cy.wait('@updateOIDCProvider').its('response.statusCode').should('eq', 204);

    cy.logout();
  }
);

Then(
  'the users from the 3rd party authentication service are affected to ACL groups',
  () => {
    cy.visit('/');

    cy.intercept({
      method: 'GET',
      url: '/centreon/api/internal.php?object=centreon_topcounter&action=user'
    }).as('getUserInformation');

    cy.contains('Login with openid').should('be.visible').click();

    cy.loginKeycloak('user-non-admin-for-OIDC-authentication');

    cy.wait('@getUserInformation').its('response.statusCode').should('eq', 200);
    cy.url().should('include', '/monitoring/resources');

    cy.logout();
    cy.getByLabel({ label: 'Alias', tag: 'input' }).should('be.visible');

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
  cy.stopContainers();
});
