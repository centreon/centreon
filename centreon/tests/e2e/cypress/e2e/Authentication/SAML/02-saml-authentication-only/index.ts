import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';

import {
  configureSAML,
  initializeSAMLUser,
  navigateToSAMLConfigPage
} from '../common';

before(() => {
  cy.startWebContainer()
    .startOpenIdProviderContainer()
    .then(() => {
      initializeSAMLUser();
    });
});

Given('an administrator is logged on the platform', () => {
  cy.loginByTypeOfUser({ jsonName: 'admin' });
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

    cy.session(`wrong_${username}`, () => {
      cy.visit('/');

      cy.loginKeycloack('admin')
        .get('#input-error')
        .should('be.visible')
        .and('include.text', 'Invalid username or password.')
        .loginKeycloack(username);

      cy.url().should('include', '/monitoring/resources');
    });
  }
);

after(() => {
  cy.stopWebContainer().stopOpenIdProviderContainer();
});
