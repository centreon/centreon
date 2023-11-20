import { Given, When, Then } from '@badeball/cypress-cucumber-preprocessor';

import { PatternType } from '../../../support/commands';
import dashboards from '../../../fixtures/dashboards/creation/dashboards.json';
import dashboardAdministratorUser from '../../../fixtures/users/user-dashboard-administrator.json';
import topBottomWidget from '../../../fixtures/dashboards/creation/widgets/dashboardWithTopBottomWidget.json';

before(() => {
  cy.startWebContainer();
  // cy.execInContainer({
  //   command: `sed -i 's@"dashboard": 0@"dashboard": 3@' /usr/share/centreon/config/features.json`,
  //   name: Cypress.env('dockerName')
  // });
  cy.executeCommandsViaClapi(
    'resources/clapi/config-ACL/dashboard-widget-metrics.json'
  );
  const apacheUser = Cypress.env('WEB_IMAGE_OS').includes('alma')
    ? 'apache'
    : 'www-data';
  cy.execInContainer({
    command: `su -s /bin/sh ${apacheUser} -c "/usr/bin/env php -q /usr/share/centreon/cron/centAcl.php"`,
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
    method: 'GET',
    url: /\/api\/latest\/monitoring\/dashboard\/metrics\/performances\/data\?.*$/
  }).as('performanceData');
  cy.intercept({
    method: 'POST',
    url: `/centreon/api/latest/configuration/dashboards/*/access_rights/contacts`
  }).as('addContactToDashboardShareList');
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
    method: 'GET',
    url: /\/api\/latest\/monitoring\/dashboard\/metrics\/performances\/data\?.*$/
  }).as('performanceData');
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

afterEach(() => {
  cy.requestOnDatabase({
    database: 'centreon',
    query: 'DELETE FROM dashboard'
  });
});

after(() => {
  cy.stopWebContainer();
});

// Given(
//   "a dashboard in the dashboard administrator user's dashboard library",
//   () => {
//     cy.insertDashboard({ ...dashboards.default });
//     cy.visit(`${Cypress.config().baseUrl}/centreon/home/dashboards`);
//     cy.getByLabel({
//       label: 'view',
//       tag: 'button'
//     })
//       .contains(dashboards.default.name)
//       .click();
//   }
// );

// When(
//   'the dashboard administrator user selects the option to add a new widget',
//   () => {
//     cy.get('*[class^="react-grid-layout"]').children().should('have.length', 0);
//     cy.getByTestId({ testId: 'edit_dashboard' }).click();
//     cy.getByTestId({ testId: 'AddIcon' }).click();
//   }
// );

// When('selects the widget type "Top Bottom"', () => {
//   cy.getByTestId({ testId: 'Widget type' }).click();
//   cy.contains('Top/bottom').click();
// });

// Then('configuration properties for the Top Bottom widget are displayed', () => {
//   cy.getByTestId({ testId: 'Bottom' }).should('exist');
//   cy.getByTestId({ testId: 'Top' }).should('exist');
// });

// When(
//   'the dashboard administrator user selects a list of resources and the metric for the widget to report on',
//   () => {
//     cy.getByLabel({ label: 'Title' }).type(genericTextWidgets.default.title);
//     cy.getByLabel({ label: 'RichTextEditor' })
//       .eq(0)
//       .type(genericTextWidgets.default.description);
//     cy.getByTestId({ testId: 'Resource type' }).realClick();
//     cy.getByLabel({ label: 'Host Group' }).click();
//     cy.getByTestId({ testId: 'Select resource' }).click();
//     cy.get('[class^="MuiAutocomplete-listbox"]').click();
//     cy.getByTestId({ testId: 'Select metric' }).click();
//     cy.get('[class^="MuiAutocomplete-option"]').eq(0).click();
//   }
// );

// Then(
//   'a top of best-performing resources for this metbric are displayed in the widget preview',
//   () => {
//     cy.getByTestId({ testId: 'warning-line-200-tooltip' }).should('be.visible');
//     cy.getByTestId({ testId: 'critical-line-400-tooltip' }).should(
//       'be.visible'
//     );
//     cy.contains('#1 Centreon-Server_Ping').should('be.visible');
//   }
// );

// When('the user saves the Top Bottom widget', () => {
//   cy.getByTestId({ testId: 'confirm' }).click();
// });

// Then("the Top Bottom metric widget is added in the dashboard's layout", () => {
//   cy.getByTestId({ testId: 'warning-line-200-tooltip' }).should('exist');
//   cy.getByTestId({ testId: 'critical-line-400-tooltip' }).should('be.visible');
// });

Given('a dashboard featuring a configured Top Bottom widget', () => {
  cy.insertDashboardWithSingleMetricWidget(dashboards.default, topBottomWidget);
  cy.visit(`${Cypress.config().baseUrl}/centreon/home/dashboards`);
  cy.getByLabel({
    label: 'view',
    tag: 'button'
  })
    .contains(dashboards.default.name)
    .click();
    cy.wait(30000)
});
