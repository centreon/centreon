import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';

import { configureOpenIDConnect } from '../common';
import {
  configureACLGroups,
  configureProviderAcls,
  getUserContactId
} from '../../../../commons';

before(() => {
  cy.startContainers({ profiles: ['openid'] }).then(() => {
    configureProviderAcls();
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
    url: '/centreon/api/latest/configuration/contacts/templates?page=1&sort_by=%7B%22name%22%3A%22ASC%22%7D&search=%7B%22%24and%22%3A%5B%5D%7D'
  }).as('getListContactTemplates');
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
  'the administrator activates the auto-import option for OpenID Connect',
  () => {
    cy.getByLabel({
      label: 'Enable OpenID Connect authentication',
      tag: 'input'
    }).check();

    configureOpenIDConnect();

    // Auto import users section
    cy.getByLabel({ label: 'Auto import users' }).click();
    cy.getByLabel({ label: 'Enable auto import', tag: 'input' }).check();
    cy.getByLabel({
      label: 'Contact template',
      tag: 'input'
    }).type('{selectall}{backspace}contact_template');
    cy.wait('@getListContactTemplates')
      .get('div[role="presentation"] ul li')
      .eq(-1)
      .click();
    cy.getByLabel({
      label: 'Contact template',
      tag: 'input'
    }).should('have.value', 'contact_template');
    cy.getByLabel({
      label: 'Email attribute path',
      tag: 'input'
    }).type('{selectall}{backspace}email');
    cy.getByLabel({
      label: 'Fullname attribute path',
      tag: 'input'
    }).type('{selectall}{backspace}name');

    configureACLGroups('realm_access.roles');

    cy.getByLabel({ label: 'save button', tag: 'button' }).click();

    cy.wait('@updateOIDCProvider').its('response.statusCode').should('eq', 204);

    cy.logout();
  }
);

Then(
  'the users from the 3rd party authentication service with the contact template are imported',
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
    cy.getByLabel({ label: 'Alias', tag: 'input' }).should('exist');

    cy.loginByTypeOfUser({ jsonName: 'admin' })
      .wait('@postLocalAuthentification')
      .its('response.statusCode')
      .should('eq', 200);

    getUserContactId('oidc').then((oidcId) => {
      cy.visit(`/centreon/main.php?p=60301&o=c&contact_id=${oidcId}`)
        .wait('@getTimeZone')
        .getIframeBody()
        .find('form')
        .within(() => {
          cy.getByTestId({ tag: 'input', testId: 'contact_alias' }).should(
            'have.value',
            'oidc'
          );
          cy.getByTestId({ tag: 'input', testId: 'contact_name' }).should(
            'have.value',
            'OpenId Connect OIDC'
          );
          cy.getByTestId({ tag: 'input', testId: 'contact_email' }).should(
            'have.value',
            'oidc@localhost'
          );
          cy.getByTestId({ tag: 'select', testId: 'contact_template_id' })
            .find(':selected')
            .contains('contact_template');
        });
    });
  }
);

after(() => {
  cy.stopContainers();
});
