import { Given, When, Then } from '@badeball/cypress-cucumber-preprocessor';

import { enableResourcesAccessManagementFeature } from '../common';

beforeEach(() => {
  //   cy.startContainers();
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/internal.php?object=centreon_topology&action=navigationList'
  }).as('getNavigationList');
});

Given('I am logged in as a user with administrator role', () => {
  cy.loginByTypeOfUser({ jsonName: 'admin' });
});

Given(
  'I have access to the Administration > ACL > Resource Access Management feature',
  () => {
    enableResourcesAccessManagementFeature();
  }
);

When('I navigate to the Resource Access Management page', () => {
  cy.visit(`centreon/administration/resource-access/rules`);
});

Then(
  'I should see a table with columns: "Name", "Description", "Actions", "Status"',
  () => {
    cy.get('[class$="-table"]').each(($row) => {
      cy.wrap($row).within(() => {
        cy.contains('Name').should('exist');
        cy.contains('Description').should('exist');
        cy.contains('Actions').should('exist');
        cy.contains('Status').should('exist');
      });
    });
  }
);

Then('a button to add a new rule is available', () => {
  cy.get('[data-testid="createResourceAccessRule"]').should('exist');
});

Then('I should see at least 15 rules registered', () => {
  cy.executePostRequestMultipleTimes();
});

Given('the default pagination should be set to 10 per page', () => {
  // Add assertion to check if the default pagination is set to 10 per page
});

When('I click on the next page button', () => {
  // Add code to click on the next page button
});

Then('I should see the next 5 rules displayed', () => {
  // Add assertion to check if the next 5 rules are displayed
});

When('I click on the previous page button', () => {
  // Add code to click on the previous page button
});

Then('I should see the previous first 10 rules displayed', () => {
  // Add assertion to check if the previous first 10 rules are displayed
});

// afterEach(() => {
//   cy.stopContainers();
// });
