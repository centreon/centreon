import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { INTERCEPTORS } from 'fixtures/shared/constants/interceptors';
import { PAGES } from 'fixtures/shared/constants/pages';

beforeEach(() => {
  cy.startContainers();

  cy.intercept({
    method: 'GET',
    url: INTERCEPTORS.api.navigation_list
  }).as('getNavigationList');
  cy.intercept({
    method: 'GET',
    url: INTERCEPTORS.pages.time_zone
  }).as('getTimeZone');
});

afterEach(() => {
  cy.stopContainers();
});

Given('I am logged in as an Administrator', () => {
  cy.loginByTypeOfUser({ jsonName: 'admin' });
});

When('I navigate to "Administration" > "ACL" > "Actions Access"', () => {
  cy.visit(PAGES.configuration.aclActionsAccessLegacy);
  cy.wait('@getTimeZone');
});

When('I click on the "Add" button', () => {
  cy.getIframeBody().contains('a', 'Add').click();
  cy.wait('@getTimeZone');
});

Then('I see {string} listed as an action', (actionName: string) => {
  cy.getIframeBody().contains('td', actionName);
});

When('I navigate to "Administration" > "ACL" > "Menus Access"', () => {
  cy.visit(PAGES.configuration.aclMenusAccessLegacy);
  cy.wait('@getTimeZone');
});

Then(
  'I see {string} listed under the {string} section',
  (menuItem: string, sectionName: string) => {
    cy.getIframeBody()
      .contains('td', sectionName)
      .within(() => {
        cy.get('img').click();
      });
    cy.getIframeBody().contains(menuItem);
  }
);
