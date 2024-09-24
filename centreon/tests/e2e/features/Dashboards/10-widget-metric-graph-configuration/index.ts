import { Given, When, Then } from '@badeball/cypress-cucumber-preprocessor';

import { PatternType } from '../../../support/commands';
import dashboardAdministratorUser from '../../../fixtures/users/user-dashboard-administrator.json';
import dashboards from '../../../fixtures/dashboards/creation/dashboards.json';
import genericTextWidgets from '../../../fixtures/dashboards/creation/widgets/genericText.json';
import metricsGraphWidget from '../../../fixtures/dashboards/creation/widgets/metricsGraphWidget.json';
import metricsGraphDoublecWidget from '../../../fixtures/dashboards/creation/widgets/dashboardWithTwometricsGraphWidget.json';
import { checkMetricsAreMonitored, checkServicesAreMonitored } from 'e2e/commons';

before(() => {
  cy.startWebContainer();
  cy.enableDashboardFeature();
  cy.executeCommandsViaClapi(
    'resources/clapi/config-ACL/dashboard-metrics-graph.json'
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
    url: '/centreon/api/latest/configuration/dashboards**'
  }).as('listAllDashboards');
  cy.intercept({
    method: 'POST',
    url: `/centreon/api/latest/configuration/dashboards/*/access_rights/contacts`
  }).as('addContactToDashboardShareList');
  cy.intercept({
    method: 'GET',
    url: /\/api\/latest\/monitoring\/dashboard\/metrics\/performances\/data\?.*$/
  }).as('performanceData');
  cy.loginAsAdminViaApiV2()
  .scheduleServiceCheck({ host: 'Centreon-Server', service: 'Ping' })
  .logoutViaAPI();

  checkServicesAreMonitored([
    {
      name: 'Ping',
      status: 'ok'
    }
  ]);
  checkMetricsAreMonitored([
    {
      host: 'Centreon-Server',
      name: 'rta',
      service: 'Ping'
    }
  ]);
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
  cy.intercept({
    method: 'POST',
    url: `/centreon/api/latest/configuration/dashboards/*/access_rights/contacts`
  }).as('addContactToDashboardShareList');
  cy.intercept({
    method: 'PATCH',
    url: `/centreon/api/latest/configuration/dashboards/*`
  }).as('updateDashboard');
  cy.intercept({
    method: 'GET',
    url: /\/api\/latest\/monitoring\/dashboard\/metrics\/performances\/data\?.*$/
  }).as('performanceData');
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
  'configuration properties for the Metrics graph widget are displayed',
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
    cy.getByLabel({ label: 'Host Group' }).click();
    cy.getByTestId({ testId: 'Select resource' }).click();
    cy.contains('Linux-Servers').realClick();
    cy.getByTestId({ testId: 'Select metric' }).should('be.enabled').click();
    cy.getByTestId({ testId: 'rta' }).realClick();
    cy.wait('@performanceData');
  }
);

Then("a graph with a single bar is displayed in the widget's preview", () => {
  cy.getByTestId({ testId: 'warning-line-200-tooltip' }).should('exist');
  cy.getByTestId({ testId: 'critical-line-400-tooltip' }).should('exist');
  cy.getByTestId({ testId: 'Min' }).should('exist');
  cy.getByTestId({ testId: 'Max' }).should('exist');
  cy.getByTestId({ testId: 'Avg' }).should('exist');
});

Then(
  'this bar represents the evolution of the selected metric over the default period of time',
  () => {
    cy.get('.visx-group.visx-axis-bottom text')
      .last()
      .invoke('text')
      .should('match', /^(0?[1-9]|1[0-2]):[0-5][0-9] (AM|PM)$/);
  }
);

When('the user saves the Metrics Graph widget', () => {
  cy.getByTestId({ testId: 'confirm' }).click();
});

Then("the Metrics Graph widget is added to the dashboard's layout", () => {
  cy.getByTestId({ testId: 'warning-line-200-tooltip' }).should('exist');
  cy.getByTestId({ testId: 'critical-line-400-tooltip' }).should('exist');
});

Then('the information about the selected metric is displayed', () => {
  cy.get('[data-testid^="warning-line-"][data-testid$="-tooltip"]').should(
    'exist'
  );
  cy.getByTestId({ testId: 'Min' }).should('exist');
  cy.getByTestId({ testId: 'Max' }).should('exist');
  cy.getByTestId({ testId: 'Avg' }).should('exist');
});

Given('a dashboard featuring having Metrics Graph widget', () => {
  cy.insertDashboardWithMetricsGraphWidget(
    dashboards.default,
    metricsGraphWidget
  );
  cy.visit(`${Cypress.config().baseUrl}/centreon/home/dashboards`);
  cy.wait('@listAllDashboards');
  cy.getByLabel({
    label: 'view',
    tag: 'button'
  })
    .contains(dashboards.default.name)
    .click();
  cy.getByLabel({
    label: 'Edit dashboard',
    tag: 'button'
  }).click();
  cy.wait('@performanceData');
  cy.getByTestId({ testId: 'MoreHorizIcon' }).click();
  cy.getByLabel({
    label: 'Edit widget',
    tag: 'li'
  }).realClick();
});

When(
  'the dashboard administrator user updates the custom warning threshold',
  () => {
    cy.get('[class^="MuiAccordionDetails-root"]').eq(1).scrollIntoView();
    cy.contains('Custom').find('input').eq(0).click();
    cy.getByLabel({
      label: 'Thresholds',
      tag: 'input'
    }).type('500');
  }
);

Then(
  'the Metrics Graph widget is refreshed to display the updated warning threshold horizontal bar',
  () => {
    cy.getByTestId({ testId: 'warning-line-500-tooltip' }).should('exist');
  }
);

When(
  'the dashboard administrator user updates the custom critical threshold',
  () => {
    cy.get('input[type="radio"][value="custom"]').eq(1).click({ force: true });
    cy.getByLabel({
      label: 'Thresholds',
      tag: 'input'
    })
      .eq(1)
      .type('300', { force: true });
  }
);

Then(
  'the Metrics Graph widget is refreshed to display the updated critical threshold horizontal bar',
  () => {
    cy.getByTestId({ testId: 'critical-line-300-tooltip' }).should('exist');
  }
);

When(
  'the dashboard administrator user updates a threshold to a value beyond the default range of the Y-axis',
  () => {
    cy.get('input[type="radio"][value="custom"]').eq(1).click({ force: true });
    cy.getByLabel({
      label: 'Thresholds',
      tag: 'input'
    })
      .eq(1)
      .clear();
    cy.getByLabel({
      label: 'Thresholds',
      tag: 'input'
    })
      .eq(1)
      .type('600', { force: true });
  }
);

Then(
  'the Y-axis of the Metrics Graph widget is updated to reflect the change in threshold',
  () => {
    cy.getByTestId({ testId: 'critical-line-600-tooltip' }).should('exist');
  }
);

Given('a dashboard that includes a configured Metrics Graph widget', () => {
  cy.insertDashboardWithMetricsGraphWidget(
    dashboards.default,
    metricsGraphWidget
  );
  cy.visit(`${Cypress.config().baseUrl}/centreon/home/dashboards`);
  cy.wait('@listAllDashboards');
  cy.getByLabel({
    label: 'view',
    tag: 'button'
  })
    .contains(dashboards.default.name)
    .click();
});

When(
  'the dashboard administrator user duplicates the Metrics Graph widget',
  () => {
    cy.getByLabel({
      label: 'Edit dashboard',
      tag: 'button'
    }).click();
    cy.wait('@performanceData');
    cy.getByTestId({ testId: 'MoreHorizIcon' }).click();
    cy.getByTestId({ testId: 'ContentCopyIcon' }).click();
  }
);

Then('a second Metrics Graph widget is displayed on the dashboard', () => {
  cy.getByTestId({ testId: 'Min' }).eq(1).should('exist');
  cy.getByTestId({ testId: 'Max' }).eq(1).should('exist');
  cy.getByTestId({ testId: 'Avg' }).eq(1).should('exist');
});

Then('the second widget has the same properties as the first widget', () => {
  cy.getByTestId({ testId: 'critical-line-400-tooltip' }).eq(1).should('exist');
  cy.getByTestId({ testId: 'warning-line-200-tooltip' }).eq(1).should('exist');
});

Given('a dashboard featuring two Metrics Graph widgets', () => {
  cy.insertDashboardWithMetricsGraphWidget(
    dashboards.default,
    metricsGraphDoublecWidget
  );
  cy.visit(`${Cypress.config().baseUrl}/centreon/home/dashboards`);
  cy.wait('@listAllDashboards');
  cy.getByLabel({
    label: 'view',
    tag: 'button'
  })
    .contains(dashboards.default.name)
    .click();
  cy.getByLabel({
    label: 'Edit dashboard',
    tag: 'button'
  }).click();
  cy.getByTestId({ testId: 'More actions' }).eq(0).click();
  cy.wait('@performanceData');
});

When(
  'the dashboard administrator user deletes one of the Metrics Graph widgets',
  () => {
    cy.getByTestId({ testId: 'DeleteIcon' }).click();
    cy.getByLabel({
      label: 'Delete',
      tag: 'li'
    }).realClick();
  }
);

Then(
  'only the contents of the other Metrics Graph widget are displayed',
  () => {
    cy.getByTestId({ testId: 'critical-line-400-tooltip' }).should('exist');
    cy.getByTestId({ testId: 'warning-line-200-tooltip' }).should('exist');
    cy.getByTestId({ testId: 'Min' }).should('exist');
    cy.getByTestId({ testId: 'Max' }).should('exist');
    cy.getByTestId({ testId: 'Avg' }).should('exist');
  }
);

Given('a dashboard featuring a configured Metrics Graph widget', () => {
  cy.insertDashboardWithMetricsGraphWidget(
    dashboards.default,
    metricsGraphWidget
  );
  cy.visit(`${Cypress.config().baseUrl}/centreon/home/dashboards`);
  cy.wait('@listAllDashboards');
  cy.getByLabel({
    label: 'view',
    tag: 'button'
  })
    .contains(dashboards.default.name)
    .click();
  cy.getByLabel({
    label: 'Edit dashboard',
    tag: 'button'
  }).click();
  cy.getByTestId({ testId: 'MoreHorizIcon' }).click();
  cy.getByLabel({
    label: 'Edit widget',
    tag: 'li'
  }).realClick();
  cy.wait('@performanceData');
});

When(
  'the dashboard administrator user selects a metric with a different unit than the initial metric in the dataset selection',
  () => {
    cy.getByTestId({ testId: 'Select metric' }).should('be.enabled').click();
    cy.getByTestId({ testId: 'pl' }).realClick();
  }
);

Then(
  'additional bars representing the metric behavior of these metrics are added to the Metrics Graph widget',
  () => {
    cy.getByTestId({ testId: 'Min' }).eq(1).should('exist');
    cy.getByTestId({ testId: 'Max' }).eq(1).should('exist');
    cy.getByTestId({ testId: 'Avg' }).eq(1).should('exist');
  }
);

Then(
  'an additional Y-axis based on the unit of these additional bars is displayed',
  () => {
    cy.get('g.visx-axis-left').should('exist');
    cy.get('g.visx-axis-right').should('exist');
  }
);

Then('the thresholds are automatically hidden', () => {
  cy.get('span[data-checked="false"]').should('exist');
  cy.getByTestId({ testId: 'confirm' }).click();
  cy.wait('@performanceData')
  cy.getByTestId({ testId: 'warning-line-200-tooltip' }).should('not.exist');
  cy.getByTestId({ testId: 'critical-line-400-tooltip' }).should('not.exist');
});
