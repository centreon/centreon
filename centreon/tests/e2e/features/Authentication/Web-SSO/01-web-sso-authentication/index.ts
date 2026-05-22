import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { INTERCEPTORS } from 'fixtures/shared/constants/interceptors';
import { PAGES } from 'fixtures/shared/constants/pages';

before(() => {
  cy.startContainers();
});

beforeEach(() => {
  cy.intercept({
    method: 'GET',
    url: INTERCEPTORS.api.navigation_list
  }).as('getNavigationList');
  cy.intercept({
    method: 'GET',
    url: `${INTERCEPTORS.api.authentication_provider}/web-sso`
  }).as('getWebSSOProvider');
  cy.intercept({
    method: 'POST',
    url: INTERCEPTORS.api.local_authentication
  }).as('postLocalAuthentification');
});

Given('an administrator logged in the platform', () => {
  cy.loginByTypeOfUser({ jsonName: 'admin' })
    .wait('@postLocalAuthentification')
    .its('response.statusCode')
    .should('eq', 200);
});

When('the administrator first configures the authentication mode', () => {
  cy.visit(PAGES.configuration.authentication)
    .get('div[role="tablist"] button:nth-child(3)')
    .click();
  cy.wait('@getWebSSOProvider');
});

Then(
  'default authentication mode must be Mixed and users created locally to centreon platform must be able to authenticate',
  () => {
    cy.getByLabel({ label: 'Mixed', tag: 'input' })
      .should('be.checked')
      .and('have.value', 'false');

    cy.logout();

    cy.getByLabel({ label: 'Alias', tag: 'input' }).should('be.visible');

    cy.loginByTypeOfUser({ jsonName: 'admin' })
      .wait('@postLocalAuthentification')
      .its('response.statusCode')
      .should('eq', 200);
  }
);

after(() => {
  cy.stopContainers();
});
