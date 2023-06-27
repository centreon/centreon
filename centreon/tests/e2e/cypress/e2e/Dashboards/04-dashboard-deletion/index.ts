import { Given, When, Then } from '@badeball/cypress-cucumber-preprocessor';

import dashboardsOnePage from '../../../fixtures/dashboards/navigation/dashboards-single-page.json';

before(() => {
  cy.startWebContainer();
  cy.execInContainer({
    command: `sed -i 's@"dashboard": 0@"dashboard": 3@' /usr/share/centreon/config/features.json`,
    name: Cypress.env('dockerName')
  });
  cy.executeCommandsViaClapi(
    'resources/clapi/config-ACL/dashboard-configuration-creator.json'
  );
});

after(() => {
  cy.stopWebContainer();
});

beforeEach(() => {
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/internal.php?object=centreon_topology&action=navigationList'
  }).as('getNavigationList');
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/latest/configuration/dashboards?'
  }).as('listAllDashboards');
  cy.loginByTypeOfUser({
    jsonName: 'user-dashboard-creator',
    loginViaApi: false
  });
  cy.insertDashboardList('dashboards/navigation/dashboards-single-page.json');
});

afterEach(() => {
  cy.requestOnDatabase({
    database: 'centreon',
    query: 'DELETE FROM dashboard'
  });
});

Given('a user with dashboard update rights on the dashboards library', () => {
  cy.visit(`${Cypress.config().baseUrl}/centreon/home/dashboards`);
});

When(
  'the user clicks on the delete button for a dashboard featured in the library',
  () => {
    const dashboardToDelete = dashboardsOnePage[dashboardsOnePage.length - 3];

    cy.contains(dashboardToDelete.name)
      .parent()
      .find('button[aria-label="delete"]')
      .click();
  }
);

Then('a confirmation pop-up appears', () => {
  const dashboardToDelete = dashboardsOnePage[dashboardsOnePage.length - 3];

  cy.contains('Delete dashboard').should('be.visible');
  cy.contains(
    `Are you sure you want to delete ${dashboardToDelete.name} ?`
  ).should('be.visible');
  cy.getByLabel({ label: 'Delete', tag: 'button' }).should('be.enabled');
  cy.getByLabel({ label: 'Cancel', tag: 'button' }).should('be.enabled');
});

When('the user confirms the choice to delete the dashboard', () => {
  cy.getByLabel({ label: 'Delete', tag: 'button' }).click();
  cy.wait('@listAllDashboards');
});

Then('the dashboard is not listed anymore in the dashboards library', () => {
  const dashboardToDelete = dashboardsOnePage[dashboardsOnePage.length - 3];

  cy.getByLabel({
    label: 'view',
    tag: 'button'
  })
    .contains(dashboardToDelete.name)
    .should('not.exist');

  cy.getByLabel({
    label: 'view',
    tag: 'button'
  })
    .contains(dashboardToDelete.description)
    .should('not.exist');
});
