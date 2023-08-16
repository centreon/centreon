import { Given, When, Then } from '@badeball/cypress-cucumber-preprocessor';
import { last } from 'ramda';

import dashboards from '../../../fixtures/dashboards/check-permissions/dashboards.json';
import dashboardAdministratorUser from '../../../fixtures/users/user-dashboard-administrator.json';
import dashboardCreatorUser from '../../../fixtures/users/user-dashboard-creator.json';
import dashboardViewerUser from '../../../fixtures/users/user-dashboard-viewer.json';

before(() => {
  cy.startWebContainer();
  cy.execInContainer({
    command: `sed -i 's@"dashboard": 0@"dashboard": 3@' /usr/share/centreon/config/features.json`,
    name: Cypress.env('dockerName')
  });
  cy.executeCommandsViaClapi(
    'resources/clapi/config-ACL/dashboard-check-permissions.json'
  );
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
  cy.intercept({
    method: 'POST',
    url: '/centreon/api/latest/configuration/dashboards'
  }).as('createDashboard');
  cy.loginByTypeOfUser({
    jsonName: dashboardAdministratorUser.login,
    loginViaApi: true
  });
  cy.insertDashboard({ ...dashboards.fromDashboardAdministratorUser });
  cy.logoutViaAPI();
  cy.loginByTypeOfUser({
    jsonName: dashboardCreatorUser.login,
    loginViaApi: true
  });
  cy.insertDashboard({ ...dashboards.fromDashboardCreatorUser });
  cy.logoutViaAPI();
});

after(() => {
  cy.stopWebContainer();
});

afterEach(() => {
  cy.visit(`${Cypress.config().baseUrl}/centreon/home/dashboards`);
  cy.requestOnDatabase({
    database: 'centreon',
    query: 'DELETE FROM dashboard'
  });
  cy.logout();
});

Given('a non-admin user who is on a list of shared dashboards', () => {
  cy.loginByTypeOfUser({
    jsonName: dashboardAdministratorUser.login,
    loginViaApi: false
  });
  cy.visit(`${Cypress.config().baseUrl}/centreon/home/dashboards`);
});

When('the user selects the share option on a dashboard', () => {
  cy.getByLabel({
    label: 'view',
    tag: 'button'
  })
    .contains(dashboards.fromDashboardAdministratorUser.name)
    .click();
  cy.getByLabel({ label: 'share', tag: 'button' }).click();
});

Then('the user is redirected to the sharing list of the dashboard', () => {
  cy.contains('Manage access rights').should('be.visible');
  cy.get('*[class^="MuiList-root"]').eq(1).should('exist');
});

Then('the creator of the dashboard is listed as its sole editor', () => {
  cy.get('*[class^="MuiList-root"]')
    .eq(1)
    .children()
    .its('length')
    .should('eq', 1);
  cy.get('*[class^="MuiList-root"]')
    .eq(1)
    .children()
    .eq(0)
    .should('contain', `${dashboardAdministratorUser.login}`);
  cy.getByTestId({ testId: 'role-input' })
    .eq(1)
    .should('contain.text', 'editor');
});

Given('a non-admin user who has update rights on a dashboard', () => {
  cy.loginByTypeOfUser({
    jsonName: dashboardCreatorUser.login,
    loginViaApi: false
  });
  cy.visit(`${Cypress.config().baseUrl}/centreon/home/dashboards`);
});

When('the editor user sets another user as a viewer on the dashboard', () => {
  cy.getByLabel({
    label: 'view',
    tag: 'button'
  })
    .contains(dashboards.fromDashboardCreatorUser.name)
    .click();
  cy.getByLabel({ label: 'share', tag: 'button' }).click();
  cy.getByLabel({ label: 'Open', tag: 'button' }).click();
  cy.contains(dashboardViewerUser.login).click();
  cy.getByTestId({ testId: 'add' }).should('be.enabled');
  cy.getByTestId({ testId: 'role-input' })
    .eq(0)
    .should('contain.text', 'viewer');
  cy.getByTestId({ testId: 'add' }).click();

  cy.get('*[class^="MuiList-root"]')
    .eq(1)
    .children()
    .its('length')
    .should('eq', 2);
  cy.get('*[class^="MuiList-root"]')
    .eq(1)
    .children()
    .eq(0)
    .should('contain', `${dashboardViewerUser.login}`);

  cy.get('[data-state="added"]').should('exist');
  cy.getByLabel({ label: 'Update', tag: 'button' }).click();
});

Then(
  "the viewer user is listed as a viewer in the dashboard's share list",
  () => {
    cy.getByLabel({ label: 'share', tag: 'button' }).click();
    cy.get('*[class^="MuiList-root"]')
      .eq(1)
      .children()
      .contains('user-dashboard-viewer')
      .should('exist');

    cy.get('[data-state="added"]').should('not.exist');
    cy.getByLabel({ label: 'Cancel', tag: 'button' }).click();
    cy.logout();
    cy.getByLabel({ label: 'Alias', tag: 'input' }).should('exist');
  }
);

When('the viewer user logs in on the platform', () => {
  cy.loginByTypeOfUser({
    jsonName: dashboardViewerUser.login,
    loginViaApi: false
  });
  cy.visit(`${Cypress.config().baseUrl}/centreon/home/dashboards`);
});

Then(
  "the dashboard is featured in the viewer user's dashboards library",
  () => {
    cy.getByLabel({
      label: 'view',
      tag: 'button'
    })
      .contains(dashboards.fromDashboardCreatorUser.name)
      .should('exist');
  }
);

When('the viewer user clicks on the dashboard', () => {
  cy.getByLabel({
    label: 'view',
    tag: 'button'
  })
    .contains(dashboards.fromDashboardCreatorUser.name)
    .click();
});

Then(
  "the viewer user can visualize the dashboard's layout but cannot update or share it",
  () => {
    cy.location('pathname')
      .should('include', '/dashboards/')
      .invoke('split', '/')
      .should('not.be.empty')
      .then(last)
      .then(Number)
      .should('not.be', 'dashboards')
      .should('be.a', 'number');

    cy.getByTestId({ testId: 'edit' }).should('not.exist');
    cy.getByTestId({ testId: 'share' }).should('not.exist');
  }
);
