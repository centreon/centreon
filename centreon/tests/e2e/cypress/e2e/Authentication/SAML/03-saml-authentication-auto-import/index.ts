/* eslint-disable cypress/unsafe-to-chain-command */
import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';

import { configureSAML, navigateToSAMLConfigPage } from '../common';
import { getUserContactId } from '../../../../commons';

before(() => {
  cy.startWebContainer().startOpenIdProviderContainer();
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

  cy.getByLabel({
    label: 'Enable auto import',
    tag: 'input'
  }).check();

  configureSAML();

  cy.getByLabel({ label: 'Auto import users' })
    .eq(0)
    .contains('Auto import users')
    .click({ force: true });

  cy.getByLabel({
    label: 'Contact template',
    tag: 'input'
  })
    .clear()
    .type('contact_template')
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
    label: 'Email attribute path',
    tag: 'input'
  })
    .clear()
    .type('email');
  cy.getByLabel({
    label: 'Fullname attribute path',
    tag: 'input'
  })
    .clear()
    .type('name');

  cy.getByLabel({ label: 'save button', tag: 'button' }).click();

  cy.wait('@updateSAMLProvider').its('response.statusCode').should('eq', 204);

  cy.logout();
});

Then(
  'the users from the 3rd party authentication service with the contact template are imported',
  () => {
    const username = 'user-non-admin-for-SAML-authentication';

    cy.session(username, () => {
      cy.visit('/').getByLabel({ label: 'Login with SAML', tag: 'a' }).click();
      cy.loginKeycloack(username)
        .url()
        .should('include', '/monitoring/resources')
        .logout();

      cy.getByLabel({ label: 'Alias', tag: 'input' }).should('exist');
    });

    cy.loginByTypeOfUser({ jsonName: 'admin' })
      .wait('@postLocalAuthentification')
      .its('response.statusCode')
      .should('eq', 200);

    getUserContactId('saml').then((samlId) => {
      cy.visit(`/centreon/main.php?p=60301&o=c&contact_id=${samlId}`)
        .wait('@getTimeZone')
        .getIframeBody()
        .find('form')
        .within(() => {
          cy.getByTestId({ tag: 'input', testId: 'contact_alias' }).should(
            'have.value',
            'saml'
          );
          cy.getByTestId({ tag: 'input', testId: 'contact_name' }).should(
            'have.value',
            'SAML SAML'
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
  cy.stopWebContainer().stopOpenIdProviderContainer();
});
