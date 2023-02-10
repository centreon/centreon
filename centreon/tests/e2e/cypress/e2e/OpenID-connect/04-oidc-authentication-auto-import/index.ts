import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';

import { configureOpenIDConnect, getUserContactId } from '../common';

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
});

Given('an administrator is logged in the platform', () => {
  cy.visit(`${Cypress.config().baseUrl}`);
  cy.loginByTypeOfUser({ jsonName: 'admin', preserveToken: true })
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
  'the administrator activates the auto-import option for OpenID Connect',
  () => {
    cy.getByLabel({
      label: 'Enable OpenID Connect authentication',
      tag: 'input'
    }).check();
    cy.getByLabel({ label: 'Identity provider' })
      .eq(0)
      .contains('Identity provider')
      .click();
    configureOpenIDConnect();
    cy.getByLabel({ label: 'Auto import users' })
      .eq(0)
      .contains('Auto import users')
      .click();
    cy.getByLabel({ label: 'Enable auto import', tag: 'input' }).check();
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
    cy.getByLabel({ label: 'save button', tag: 'button' })
      .click()
      .wait('@updateOIDCProvider')
      .its('response.statusCode')
      .should('eq', 204);
  }
);

Then(
  'the users from the 3rd party authentication service with the contact template are imported',
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
    cy.visit(`${Cypress.config().baseUrl}`);
    cy.loginByTypeOfUser({ jsonName: 'admin', preserveToken: true })
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
