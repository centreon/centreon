import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';

import dashboards from '../../../fixtures/dashboards/creation/dashboards.json';
import adminUser from '../../../fixtures/users/admin.json';
import genericTextWidgets from '../../../fixtures/dashboards/creation/widgets/genericText.json';

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
  cy.insertDashboard({ ...dashboards.default });
});

afterEach(() => {
  cy.requestOnDatabase({
    database: 'centreon',
    query: 'DELETE FROM dashboard'
  });
});

Given(
  "a dashboard in the dashboard administrator user's dashboard library",
  () => {
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

When('selects the widget type "Single metric"', () => {
  cy.getByTestId({ testId: 'Widget type' }).click();
  cy.wait(2000)
  cy.contains('Single metric').click();
});

Then(
  'configuration properties for the Single metric widget are displayed',
  () => {
    cy.contains('Widget properties').should('exist');
    cy.getByLabel({ label: 'Title' }).should('exist');
    cy.getByLabel({ label: 'RichTextEditor' }).should('exist');
    cy.contains('Value settings').should('exist');
    cy.get('[class*="graphTypeContainer"]').should('be.visible');
  }
);

When(
  'the dashboard administrator user selects a resource and the metric for the widget to report on',
  () => {
    cy.getByLabel({ label: 'Title' }).type(genericTextWidgets.default.title);
    cy.getByLabel({ label: 'RichTextEditor' })
      .eq(0)
      .type(genericTextWidgets.default.description);
    cy.get('[class="MuiSelect-select"]').click();
    cy.get('[class="MuiMenuItem-gutters"]').eq(0).click();
    cy.getByTestId({ testId: 'Select resource' }).click();
    cy.contains('Linux-servers').click();


  }
);
