import { Given, When, Then } from '@badeball/cypress-cucumber-preprocessor';

import dashboards from '../../../fixtures/dashboards/check-permissions/dashboards.json';

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
  cy.loginByTypeOfUser({
    jsonName: 'user-dashboard-administrator',
    loginViaApi: true
  });
  cy.insertDashboard({ ...dashboards.fromAdministratorUser });
  cy.loginByTypeOfUser({
    jsonName: 'user-dashboard-creator',
    loginViaApi: true
  });
  cy.insertDashboard({ ...dashboards.fromCreatorUser });
  cy.shareDashboardToUser({
    dashboardName: dashboards.fromCreatorUser.name,
    role: 'viewer',
    userName: 'user-dashboard-viewer'
  });
});

after(() => {
  cy.stopWebContainer();
});

afterEach(() => {
  cy.requestOnDatabase({
    database: 'centreon',
    query: 'DELETE FROM dashboard'
  });
});

Given('an admin user is logged in on a platform with dashboards', () => {
  cy.loginByTypeOfUser({
    jsonName: 'admin',
    loginViaApi: true
  });
});

When('the admin user accesses the dashboard library', () => {
  cy.visit(`${Cypress.config().baseUrl}/centreon/home/dashboards`);
});

Then(
  'the admin user can view all the dashboards configured on the platform',
  () => {
    cy.getByLabel({
      label: 'view',
      tag: 'button'
    })
      .contains(dashboards.fromAdministratorUser.name)
      .should('exist');
    cy.getByLabel({
      label: 'view',
      tag: 'button'
    })
      .contains(dashboards.fromCreatorUser.name)
      .should('exist');
  }
);

Then('the admin user can perform update operations on any dashboard', () => {
  cy.contains(dashboards.fromAdministratorUser.name)
    .parent()
    .find('button[aria-label="edit"]')
    .click();
  cy.getByLabel({ label: 'Name', tag: 'input' }).clear();
  cy.getByLabel({ label: 'Name', tag: 'input' }).type(
    `${dashboards.fromAdministratorUser.name}-edited`
  );
  cy.getByLabel({ label: 'Description', tag: 'textarea' }).clear();
  cy.getByLabel({ label: 'Description', tag: 'textarea' }).type(
    `${dashboards.fromAdministratorUser.description} and admin`
  );

  cy.getByLabel({ label: 'Update', tag: 'button' }).click();

  cy.contains(dashboards.fromCreatorUser.name)
    .parent()
    .find('button[aria-label="edit"]')
    .click();
  cy.getByLabel({ label: 'Name', tag: 'input' }).clear();
  cy.getByLabel({ label: 'Name', tag: 'input' }).type(
    `${dashboards.fromCreatorUser.name}-edited`
  );
  cy.getByLabel({ label: 'Description', tag: 'textarea' }).clear();
  cy.getByLabel({ label: 'Description', tag: 'textarea' }).type(
    `${dashboards.fromCreatorUser.description} and admin`
  );

  cy.getByLabel({ label: 'Update', tag: 'button' }).click();

  cy.reload();
  cy.contains(`${dashboards.fromAdministratorUser.name}-edited`).should(
    'exist'
  );
  cy.contains(`${dashboards.fromAdministratorUser.name} and admin`).should(
    'exist'
  );
  cy.contains(`${dashboards.fromCreatorUser.name}-edited`).should('exist');
  cy.contains(`${dashboards.fromCreatorUser.name} and admin`).should('exist');
});
