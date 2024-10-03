import { Given, When, Then } from '@badeball/cypress-cucumber-preprocessor';

import dashboardsOnePage from '../../../fixtures/dashboards/navigation/dashboards-single-page.json';

before(() => {
  cy.startContainers();
  cy.enableDashboardFeature();
  cy.executeCommandsViaClapi(
    'resources/clapi/config-ACL/dashboard-configuration-creator.json'
  );
});

after(() => {
  cy.stopContainers();
});

beforeEach(() => {
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/internal.php?object=centreon_topology&action=navigationList'
  }).as('getNavigationList');
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/latest/configuration/dashboards**'
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
  cy.visitDashboards();
});

When(
  'the user clicks on the delete button for a dashboard featured in the library',
  () => {
    cy.getByLabel({ label: 'More actions', tag: 'button' }).eq(2).click();
    cy.getByLabel({ label: 'Delete' }).click();
  }
);

Then('a confirmation poppin appears', () => {
  const dashboardToDelete = dashboardsOnePage[dashboardsOnePage.length - 3];

  cy.contains('Delete').should('be.visible');
  cy.getByLabel({ label: 'Cancel', tag: 'button' }).should('be.visible');
  cy.contains(
    `The ${dashboardToDelete.name} dashboard will be permanently deleted.`
  ).should('be.visible');
});

When('the user confirms the choice to delete the dashboard', () => {
  cy.getByLabel({ label: 'Delete', tag: 'button' }).last().click();
  cy.wait('@listAllDashboards');
});

Then('the dashboard is not listed anymore in the dashboards library', () => {
  const dashboardToDelete = dashboardsOnePage[dashboardsOnePage.length - 3];

  cy.contains(dashboardToDelete.name).should('not.exist');

  cy.contains(dashboardToDelete.description).should('not.exist');
});

Given(
  'a user with dashboard edition rights about to delete a dashboard',
  () => {
    cy.visitDashboards();
    cy.getByLabel({ label: 'More actions', tag: 'button' }).eq(2).click();
    cy.getByLabel({ label: 'Delete' }).click();
  }
);

When('the user cancels their choice', () => {
  cy.getByLabel({ label: 'Cancel', tag: 'button' }).click();
});

Then('the dashboard is still listed in the dashboards library', () => {
  const dashboardToDelete = dashboardsOnePage[dashboardsOnePage.length - 3];
  cy.contains(dashboardToDelete.name).parent().should('exist');
});
