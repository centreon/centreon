import { Given, When, Then } from '@badeball/cypress-cucumber-preprocessor';

import { PatternType } from '../../../support/commands';
import dashboardAdministratorUser from '../../../fixtures/users/user-dashboard-administrator.json';
import dashboards from '../../../fixtures/dashboards/creation/dashboards.json';
import genericTextWidgets from '../../../fixtures/dashboards/creation/widgets/genericText.json';

before(() => {
  cy.startWebContainer();
  // cy.execInContainer({
  //   command: `sed -i 's@"dashboard": 0@"dashboard": 3@' /usr/share/centreon/config/features.json`,
  //   name: Cypress.env('dockerName')
  // });
  cy.executeCommandsViaClapi(
    'resources/clapi/config-ACL/dashboard-metrics-graph.json'
  );
  cy.execInContainer({
    command:
      'su -s /bin/sh apache -c "/usr/bin/env php -q /usr/share/centreon/cron/centAcl.php"',
    name: Cypress.env('dockerName')
  });
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
    url: `/centreon/api/latest/configuration/dashboards/*/access_rights/contacts`
  }).as('addContactToDashboardShareList');
  cy.loginByTypeOfUser({
    jsonName: dashboardAdministratorUser.login,
    loginViaApi: true
  });
  cy.insertDashboard({ ...dashboards.default });
  cy.logoutViaAPI();
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
    url: `/centreon/api/latest/configuration/dashboards/*/access_rights/contacts`
  }).as('addContactToDashboardShareList');
  cy.intercept({
    method: 'PATCH',
    url: `/centreon/api/latest/configuration/dashboards/*`
  }).as('updateDashboard');
  cy.loginByTypeOfUser({
    jsonName: dashboardAdministratorUser.login,
    loginViaApi: false
  });
  cy.visit('/centreon/home/dashboards');
});

after(() => {
  cy.requestOnDatabase({
    database: 'centreon',
    query: 'DELETE FROM dashboard'
  });
  cy.stopWebContainer();
});

Given(
  "a dashboard in the dashboard administrator user's dashboard library",
  () => {
    cy.insertDashboard({ ...dashboards.default });
    cy.visit(`${Cypress.config().baseUrl}/centreon/home/dashboards`);
    cy.getByLabel({
      label: 'view',
      tag: 'button'
    })
      .contains(dashboards.default.name)
      .click();
  }
);

When(
  'the dashboard administrator user selects the option to add a new widget',
  () => {
    cy.get('*[class^="react-grid-layout"]').children().should('have.length', 0);
    cy.getByTestId({ testId: 'edit_dashboard' }).click();
    cy.getByTestId({ testId: 'AddIcon' }).click();
  }
);

When('selects the widget type "Metrics graph"', () => {
  cy.getByTestId({ testId: 'Widget type' }).click();
  cy.contains('Metrics graph').click();
});

Then(
  'configuration properties for the Generic text widget are displayed',
  () => {
    cy.contains('Widget properties').should('exist');
    cy.getByLabel({ label: 'Title' }).should('exist');
    cy.getByLabel({ label: 'RichTextEditor' }).should('exist');
    cy.getByTestId({ testId: 'Time period' }).should('exist');
  }
);

When(
  'the dashboard administrator user selects a resource and a metric for the widget to report on',
  () => {
    cy.getByLabel({ label: 'Title' }).type(genericTextWidgets.default.title);
    cy.getByLabel({ label: 'RichTextEditor' })
      .eq(0)
      .type(genericTextWidgets.default.description);
    cy.getByTestId({ testId: 'Resource type' }).realClick();
    cy.get('[class*="MuiMenuItem-gutters"]').eq(0).click();
    cy.getByTestId({ testId: 'Select resource' }).click();
    cy.get('[class^="MuiAutocomplete-listbox"]').click();
    cy.getByTestId({ testId: 'Select metric' }).click();
    cy.get('[class^="MuiAutocomplete-option"]').eq(0).click();
    cy.wait(3000)
  }
);

Then("the same text is displayed in the widget's preview", () => {
  cy.getByLabel({ label: 'RichTextEditor' })
    .eq(1)
    .should('contain.text', genericTextWidgets.default.description);
});

When('the user saves the widget containing the Generic text', () => {
  cy.getByTestId({ testId: 'confirm' }).click();
});

Then("the Generic text widget is added in the dashboard's layout", () => {
  cy.get('*[class^="react-grid-layout"]').children().should('have.length', 1);
  cy.contains('Your widget has been created successfully!').should('exist');
  cy.getByTestId({
    patternType: PatternType.startsWith,
    testId: 'panel_/widgets/generictext'
  }).should('exist');
});

Then('its title and description are displayed', () => {
  cy.contains(genericTextWidgets.default.title).should('exist');
  cy.contains(genericTextWidgets.default.description).should('exist');
  cy.getByTestId({ testId: 'save_dashboard' }).click();
  cy.wait('@updateDashboard');
  cy.contains(genericTextWidgets.default.title).should('exist');
  cy.contains(genericTextWidgets.default.description).should('exist');
});
