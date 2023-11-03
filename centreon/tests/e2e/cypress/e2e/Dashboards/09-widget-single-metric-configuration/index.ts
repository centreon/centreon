import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';

import dashboards from '../../../fixtures/dashboards/creation/dashboards.json';
import adminUser from '../../../fixtures/users/admin.json';
import genericTextWidgets from '../../../fixtures/dashboards/creation/widgets/genericText.json';
import singleMetricWidget from '../../../fixtures/dashboards/creation/widgets/singleWidgetText.json';
import singleMetricPayload from '../../../fixtures/dashboards/creation/widgets/singleMetricPayload.json';

before(() => {
  cy.startWebContainer();
  // cy.execInContainer({
  //   command: `sed -i 's@"dashboard": 0@"dashboard": 3@' /usr/share/centreon/config/features.json`,
  //   name: Cypress.env('dockerName')
  // });
  // cy.executeCommandsViaClapi(
  //   'resources/clapi/config-ACL/dashboard-widget-metrics.json'
  // );
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
    jsonName: adminUser.login,
    loginViaApi: true
  });
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
    jsonName: adminUser.login,
    loginViaApi: true
  });
  // cy.insertDashboard({ ...dashboards.default });
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

// When('selects the widget type "Single metric"', () => {
//   cy.getByTestId({ testId: 'Widget type' }).click();
//   cy.contains('Single metric').click();
// });

// Then(
//   'configuration properties for the Single metric widget are displayed',
//   () => {
//     cy.contains('Widget properties').should('exist');
//     cy.getByLabel({ label: 'Title' }).should('exist');
//     cy.getByLabel({ label: 'RichTextEditor' }).should('exist');
//     cy.contains('Value settings').should('exist');
//     cy.get('[class^="MuiAccordionDetails-root"]').eq(1).scrollIntoView();
//     cy.get('[class*="graphTypeContainer"]').should('be.visible');
//   }
// );

// When(
//   'the dashboard administrator user selects a resource and the metric for the widget to report on',
//   () => {
//     cy.getByLabel({ label: 'Title' }).type(genericTextWidgets.default.title);
//     cy.getByLabel({ label: 'RichTextEditor' })
//       .eq(0)
//       .type(genericTextWidgets.default.description);
//     cy.get('[class*="MuiSelect-select"]').click();
//     cy.get('[class*="MuiMenuItem-gutters"]').eq(0).click();
//     cy.getByTestId({ testId: 'Select resource' }).click();
//     cy.get('[class^="MuiAutocomplete-listbox"]').click();
//     cy.getByTestId({ testId: 'Select metric' }).click();
//     cy.get('[class^="MuiAutocomplete-option"]').eq(0).click();
//   }
// );

// Then('information about this metric is displayed in the widget preview', () => {
//   cy.verifyGraphContainer(singleMetricWidget);
// });

// When('the user saves the Single metric widget', () => {
//   cy.getByTestId({ testId: 'confirm' }).click();
// });

// Then("the Single metric widget is added in the dashboard's layout", () => {
//   cy.get('[class*="graphContainer"]').should('be.visible');
// });

// Then('the information about the selected metric is displayed', () => {
//   cy.verifyGraphContainer(singleMetricWidget);
// });

Given('a dashboard featuring a single Single metric widget', () => {
  cy.insertDashboardWithDoubleWidget(dashboards.default, singleMetricPayload);
  cy.visit(`${Cypress.config().baseUrl}/centreon/home/dashboards`);
  cy.getByLabel({
    label: 'view',
    tag: 'button'
  })
    .contains(dashboards.default.name)
    .click();
});

When(
  'the dashboard administrator user duplicates the Single metric widget',
  () => {
    cy.getByLabel({
      label: 'Edit dashboard',
      tag: 'button'
    }).click();
    cy.getByTestId({ testId: 'MoreVertIcon' }).click();
    cy.getByTestId({ testId: 'ContentCopyIcon' })
      .should('be.visible')
      .click({ force: true });
  }
);

Then('a second Single metric widget is displayed on the dashboard', () => {
  cy.verifyDuplicatesGraphContainer(singleMetricWidget);
});
