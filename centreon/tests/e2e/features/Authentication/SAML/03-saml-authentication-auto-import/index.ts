/* eslint-disable cypress/unsafe-to-chain-command */
import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';

import { configureSAML, navigateToSAMLConfigPage } from '../common';
import {
  configureACLGroups,
  configureProviderAcls,
  getUserContactId
} from '../../../../commons';

before(() => {
  cy.startContainers({ profiles: ['saml'] }).then(() => {
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
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/latest/configuration/contacts/templates?page=1&sort_by=%7B%22name%22%3A%22ASC%22%7D&search=%7B%22%24and%22%3A%5B%5D%7D'
  }).as('getListContactTemplates');
  cy.intercept({
    method: 'GET',
    url: '/centreon/include/common/userTimezone.php'
  }).as('getTimeZone');
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/latest/configuration/access-groups?page=1&sort_by=%7B%22name%22%3A%22ASC%22%7D&search=%7B%22%24and%22%3A%5B%5D%7D'
  }).as('getListAccessGroup');
});

Given('an administrator is logged on the platform', () => {
  cy.loginByTypeOfUser({ jsonName: 'admin' });
});

When('the administrator activates the auto-import option for SAML', () => {
  navigateToSAMLConfigPage();

  cy.getByLabel({
    label: 'Enable SAMLv2 authentication',
    tag: 'input'
  }).check();

  configureSAML();

  cy.getByLabel({ label: 'Auto import users' }).click();

  cy.getByLabel({
    label: 'Enable auto import',
    tag: 'input'
  }).check();

  cy.getByLabel({
    label: 'Contact template',
    tag: 'input'
  })
    .type('{selectall}{backspace}contact_template')
    .wait('@getListContactTemplates')
    .get('div[role="presentation"] ul li')
    .eq(-1)
    .click()
    .getByLabel({
      label: 'Contact template',
      tag: 'input'
    })
    .should('have.value', 'contact_template');

  cy.getByLabel({
    label: 'Email attribute',
    tag: 'input'
  }).type('{selectall}{backspace}urn:oid:1.2.840.113549.1.9.1');

  cy.getByLabel({
    label: 'Full name attribute',
    tag: 'input'
  }).type('{selectall}{backspace}urn:oid:2.5.4.42');

  configureACLGroups('Role');

  cy.getByLabel({ label: 'save button', tag: 'button' }).click();

  cy.wait('@updateSAMLProvider').its('response.statusCode').should('eq', 204);

  cy.logout();
});

Then(
  'the users from the 3rd party authentication service with the contact template are imported',
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

    getUserContactId('saml@localhost').then((samlId) => {
      cy.visit(`/centreon/main.php?p=60301&o=c&contact_id=${samlId}`)
        .wait('@getTimeZone')
        .getIframeBody()
        .find('form')
        .within(() => {
          cy.getByTestId({ tag: 'input', testId: 'contact_alias' }).should(
            'have.value',
            'saml@localhost'
          );
          cy.getByTestId({ tag: 'input', testId: 'contact_name' }).should(
            'have.value',
            'SAML'
          );
          cy.getByTestId({ tag: 'input', testId: 'contact_email' }).should(
            'have.value',
            'saml@localhost'
          );
          cy.getByTestId({ tag: 'select', testId: 'contact_template_id' })
            .find(':selected')
            .contains('contact_template');
        });
    });
  }
);

after(() => {
  // avoid random "Cannot read properties of null (reading 'postMessage')" when stopping containers
  cy.on('uncaught:exception', () => false);

  cy.stopContainers();
});
