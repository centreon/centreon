import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';

import {
  configureSAML,
  initializeSAMLUser,
  navigateToSAMLConfigPage,
  saveSamlFormIfEnabled
} from '../common';
import { configureProviderAcls } from '../../../../commons';

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
    method: 'GET',
    url: '/centreon/api/latest/authentication/providers/configurations'
  }).as('getCentreonAuthConfigs');
  cy.intercept({
    method: 'PUT',
    url: '/centreon/api/latest/administration/authentication/providers/saml'
  }).as('updateSAMLProvider');
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

When(
  'the administrator sets valid settings in the authentication conditions and saves',
  () => {
    navigateToSAMLConfigPage();

    cy.getByLabel({
      label: 'Enable SAMLv2 authentication',
      tag: 'input'
    }).check();

    configureSAML();

    cy.get('[data-testid="Authentication conditions-header"]').click();

    cy.getByLabel({
      label: 'Enable conditions on identity provider',
      tag: 'input'
    }).check();

    cy.getByLabel({
      label: 'Conditions attribute path',
      tag: 'input'
    }).type('{selectall}{backspace}urn:oid:1.2.840.113549.1.9.1');

    cy.getByLabel({
      label: 'Condition value',
      tag: 'input'
    }).type('{selectall}{backspace}saml@localhost');

    saveSamlFormIfEnabled();

    cy.logout();
  }
);

Then(
  'the users can access to Centreon UI only if all conditions are met',
  () => {
    const username = 'user-non-admin-for-SAML-authentication';

    cy.visit('/').getByLabel({ label: 'Login with SAML', tag: 'a' }).click();
    cy.loginKeycloak(username).url().should('include', '/monitoring/resources');
  }
);

after(() => {
  // avoid random "Cannot read properties of null (reading 'postMessage')" when stopping containers
  cy.on('uncaught:exception', () => false);

  cy.stopContainers();
});
