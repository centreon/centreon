import { Given, When, Then } from '@badeball/cypress-cucumber-preprocessor';

beforeEach(() => {
  cy.startContainers();

  cy.intercept({
    method: 'GET',
    url: '/centreon/api/internal.php?object=centreon_topology&action=navigationList'
  }).as('getNavigationList');
  cy.intercept({
    method: 'GET',
    url: '/centreon/include/common/userTimezone.php'
  }).as('getTimeZone');
});

afterEach(() => {
  cy.stopContainers();
});

Given('I am logged in as an Administrator', () => {
  cy.loginByTypeOfUser({ jsonName: 'admin' });
});

When('I navigate to "Administration" > "ACL" > "Actions Access"', () => {
  cy.navigateTo({
    page: 'Actions Access',
    rootItemNumber: 4,
    subMenu: 'ACL'
  });
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
  cy.navigateTo({
    page: 'Menus Access',
    rootItemNumber: 4,
    subMenu: 'ACL'
  });
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
