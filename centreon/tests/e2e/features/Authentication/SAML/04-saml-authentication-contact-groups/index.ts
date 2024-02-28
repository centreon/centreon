import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';

import {
  configureSAML,
  initializeSAMLUser,
  navigateToSAMLConfigPage
} from '../common';
import { configureProviderAcls, getUserContactId } from '../../../../commons';

before(() => {
  cy.startContainers({ profiles: ['saml'] }).then(() => {
    configureProviderAcls();
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
    method: 'PUT',
    url: '/centreon/api/latest/administration/authentication/providers/saml'
  }).as('updateSAMLProvider');
  cy.intercept({
    method: 'POST',
    url: '/centreon/api/latest/authentication/providers/configurations/local'
  }).as('postLocalAuthentification');
  cy.intercept({
    method: 'GET',
    url: '/centreon/include/common/userTimezone.php'
  }).as('getTimeZone');
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/latest/configuration/contacts/groups?page=1&sort_by=%7B%22name%22%3A%22ASC%22%7D&search=%7B%22%24and%22%3A%5B%5D%7D'
  }).as('getListContactGroups');
});

Given('an administrator is logged on the platform', () => {
  cy.loginByTypeOfUser({ jsonName: 'admin' });
});

When(
  'the administrator sets valid settings in the Groups mapping and saves',
  () => {
    navigateToSAMLConfigPage();

    cy.getByLabel({
      label: 'Enable SAMLv2 authentication',
      tag: 'input'
    }).check();

    configureSAML();

    cy.getByLabel({ label: 'Groups mapping' }).click();

    cy.getByLabel({
      label: 'Enable automatic management',
      tag: 'input'
    })
      .eq(1)
      .check();

    cy.getByLabel({
      label: 'Groups attribute path',
      tag: 'input'
    }).type('{selectAll}{backspace}groups');

    cy.getByLabel({
      label: 'Group value',
      tag: 'input'
    }).type('{selectAll}{backspace}/Supervisors');

    cy.getByLabel({
      label: 'Contact group',
      tag: 'input'
    }).click();

    cy.wait('@getListContactGroups');

    cy.get('div[role="presentation"] ul li').eq(1).click();

    cy.getByLabel({
      label: 'Contact group',
      tag: 'input'
    }).should('have.value', 'Supervisors');

    cy.getByLabel({ label: 'save button', tag: 'button' }).click();

    cy.wait('@updateSAMLProvider').its('response.statusCode').should('eq', 204);

    cy.logout();
  }
);

Then(
  'the users from the 3rd party authentication service are attached to contact groups for each condition validated',
  () => {
    const username = 'user-non-admin-for-SAML-authentication';

    cy.visit('/').getByLabel({ label: 'Login with SAML', tag: 'a' }).click();

    cy.intercept({
      method: 'GET',
      url: '/centreon/api/internal.php?object=centreon_topcounter&action=user'
    }).as('getUserInformation');

    cy.loginKeycloak(username);

    cy.wait('@getUserInformation').its('response.statusCode').should('eq', 200);
    cy.url().should('include', '/monitoring/resources');

    cy.logout();

    cy.getByLabel({ label: 'Alias', tag: 'input' }).should('exist');

    cy.loginByTypeOfUser({ jsonName: 'admin' })
      .wait('@postLocalAuthentification')
      .its('response.statusCode')
      .should('eq', 200);
    getUserContactId('saml').then((contactId) => {
      cy.visit(`/centreon/main.php?p=60301&o=c&contact_id=${contactId}`)
        .wait('@getTimeZone')
        .getIframeBody()
        .find('form')
        .within(() => {
          cy.getByTestId({ tag: 'select', testId: 'contact_cgNotif' }).contains(
            'Supervisors'
          );
        });
    });
  }
);

after(() => {
  // avoid random "Cannot read properties of null (reading 'postMessage')" when stopping containers
  cy.on('uncaught:exception', () => false);

  cy.stopContainers();
});
