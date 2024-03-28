import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';

import {
  configureSAML,
  initializeSAMLUser,
  navigateToSAMLConfigPage
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
});

Given('an administrator is logged on the platform', () => {
  cy.loginByTypeOfUser({ jsonName: 'admin', loginViaApi: true });
});

When('the administrator sets authentication mode to SAML only', () => {
  navigateToSAMLConfigPage();

  cy.getByLabel({
    label: 'SAML only',
    tag: 'input'
  }).check();

  cy.getByLabel({
    label: 'Enable SAMLv2 authentication',
    tag: 'input'
  }).check();

  configureSAML();

  cy.getByLabel({ label: 'save button', tag: 'button' }).click();

  cy.wait('@updateSAMLProvider').its('response.statusCode').should('eq', 204);

  cy.logout();
});

Then(
  'only existing users on Centreon must be able to authenticate with only SAML protocol',
  () => {
    const username = 'user-non-admin-for-SAML-authentication';

    cy.visit('/');

    cy.intercept({
      method: 'GET',
      url: '/centreon/api/internal.php?object=centreon_topcounter&action=user'
    }).as('getUserInformation');

    cy.loginKeycloak('admin');
    cy.get('#input-error')
      .should('be.visible')
      .and('include.text', 'Invalid username or password.');

    cy.loginKeycloak(username);

    cy.wait('@getUserInformation').its('response.statusCode').should('eq', 200);
    cy.url().should('include', '/monitoring/resources');
  }
);

after(() => {
  // avoid random "Cannot read properties of null (reading 'postMessage')" when stopping containers
  cy.on('uncaught:exception', () => false);

  cy.stopContainers();
});
