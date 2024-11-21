import { Given, When, Then } from '@badeball/cypress-cucumber-preprocessor';

import dashboards from '../../../fixtures/dashboards/creation/dashboards.json';
import dashboardAdministratorUser from '../../../fixtures/users/user-dashboard-administrator.json';
import topBottomWidget from '../../../fixtures/dashboards/creation/widgets/dashboardWithTopBottomWidget.json';
import dashbboardWithTwoTopBottomWidgets from '../../../fixtures/dashboards/creation/widgets/dashboardWithTwoTopBottomWidgets.json';
import genericTextWidgets from '../../../fixtures/dashboards/creation/widgets/genericText.json';
import { checkMetricsAreMonitored, checkServicesAreMonitored } from 'e2e/commons';

before(() => {
  cy.startWebContainer();
  cy.enableDashboardFeature();
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
    url: '/centreon/api/latest/configuration/dashboards**'
  }).as('listAllDashboards');
  cy.intercept({
    method: 'GET',
    url: /\/api\/latest\/monitoring\/dashboard\/metrics\/performances\/data\?.*$/
  }).as('performanceData');
  cy.intercept({
    method: 'GET',
    url: /\/centreon\/api\/latest\/monitoring\/dashboard\/metrics\/top\?.*$/
  }).as('dashboardMetricsTop');
  cy.intercept({
    method: 'POST',
    url: `/centreon/api/latest/configuration/dashboards/*/access_rights/contacts`
  }).as('addContactToDashboardShareList');
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
    method: 'GET',
    url: /\/api\/latest\/monitoring\/dashboard\/metrics\/performances\/data\?.*$/
  }).as('performanceData');
  cy.intercept({
    method: 'GET',
    url: /\/centreon\/api\/latest\/monitoring\/dashboard\/metrics\/top\?.*$/
  }).as('dashboardMetricsTop');
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

When('selects the widget type "Top Bottom"', () => {
  cy.getByTestId({ testId: 'Widget type' }).click();
  cy.contains('Top/bottom').click();
});

Then('configuration properties for the Top Bottom widget are displayed', () => {
  cy.getByTestId({ testId: 'Bottom' }).should('exist');
  cy.getByTestId({ testId: 'Top' }).should('exist');
});

When(
  'the dashboard administrator user selects a list of resources and the metric for the widget to report on',
  () => {
    cy.getByLabel({ label: 'Title' }).type(genericTextWidgets.default.title);
    cy.getByLabel({ label: 'RichTextEditor' })
      .eq(0)
      .type(genericTextWidgets.default.description);
    cy.getByTestId({ testId: 'Resource type' }).realClick();
    cy.getByLabel({ label: 'Host Group' }).click();
    cy.getByTestId({ testId: 'Select resource' }).click();
    cy.contains('Linux-Servers').realClick();
    cy.getByTestId({ testId: 'Select metric' }).click();
    cy.getByTestId({ testId: 'rta' }).realClick();
    cy.wait('@dashboardMetricsTop');
  }
);

Then(
  'a top of best-performing resources for this metbric are displayed in the widget preview',
  () => {
    cy.getByTestId({ testId: 'warning-line-200-tooltip' }).should('be.visible');
    cy.getByTestId({ testId: 'critical-line-400-tooltip' }).should(
      'be.visible'
    );
    cy.contains('#1 Centreon-Server_Ping').should('be.visible');
  }
);

When('the user saves the Top Bottom widget', () => {
  cy.getByTestId({ testId: 'confirm' }).click();
});

Then("the Top Bottom metric widget is added in the dashboard's layout", () => {
  cy.getByTestId({ testId: 'warning-line-200-tooltip' }).should('exist');
  cy.getByTestId({ testId: 'critical-line-400-tooltip' }).should('be.visible');
});

Given('a dashboard configured with a Top Bottom widget', () => {
  cy.insertDashboardWithSingleMetricWidget(dashboards.default, topBottomWidget);
  cy.visit(`${Cypress.config().baseUrl}/centreon/home/dashboards`);
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
  cy.getByTestId({ testId: 'More actions' }).click();
  cy.get('li[aria-label="Edit widget"]').click();
});

When(
  'the dashboard administrator user removes a host from the dataset selection of the Top Bottom widget',
  () => {
    cy.getByTestId({ testId: 'CancelIcon' })
      .eq(0)
      .click();
  }
);

Then(
  'the bar associated with the host is removed from the Top Bottom widget preview',
  () => {
    cy.getByTestId({ testId: 'warning-line-200-tooltip' })
      .eq(1)
      .should('not.exist');
    cy.getByTestId({ testId: 'critical-line-400-tooltip' })
      .eq(1)
      .should('not.exist');
  }
);

When(
  'the dashboard administrator user adds a host from the dataset selection of the Top Bottom widget',
  () => {
    cy.getByTestId({ testId: 'Resource type' }).realClick();
    cy.getByLabel({ label: 'Host Group' }).click();
    cy.getByTestId({ testId: 'Select resource' }).click();
    cy.contains('Linux-Servers').realClick();
    cy.getByTestId({ testId: 'Select metric' }).click();
    cy.getByTestId({ testId: 'rta' }).realClick();
  }
);

Then(
  'the bar associated with the host is added in the Top Bottom widget preview',
  () => {
    cy.getByTestId({ testId: 'warning-line-200-tooltip' })
      .eq(1)
      .should('exist');
    cy.getByTestId({ testId: 'critical-line-400-tooltip' })
      .eq(1)
      .should('exist');
  }
);

Given('a dashboard having a configured Top Bottom widget', () => {
  cy.insertDashboardWithSingleMetricWidget(dashboards.default, topBottomWidget);
  cy.visit(`${Cypress.config().baseUrl}/centreon/home/dashboards`);
  cy.getByLabel({
    label: 'view',
    tag: 'button'
  })
    .contains(dashboards.default.name)
    .click();
});

When(
  'the dashboard administrator user duplicates the Top Bottom widget',
  () => {
    cy.getByLabel({
      label: 'Edit dashboard',
      tag: 'button'
    }).click();
    cy.getByTestId({ testId: 'MoreHorizIcon' }).click();
    cy.getByTestId({ testId: 'RefreshIcon' }).click();
    cy.getByTestId({ testId: 'MoreHorizIcon' }).click({ force: true });
    cy.getByTestId({ testId: 'ContentCopyIcon' }).click();
  }
);

Then('a second Top Bottom widget is displayed on the dashboard', () => {
  cy.getByTestId({ testId: 'warning-line-200-tooltip' })
    .eq(1)
    .should('be.visible');
  cy.getByTestId({ testId: 'critical-line-400-tooltip' })
    .eq(1)
    .should('be.visible');
});

Given('a dashboard featuring two Top Bottom widgets', () => {
  cy.insertDashboardWithSingleMetricWidget(
    dashboards.default,
    dashbboardWithTwoTopBottomWidgets
  );
  cy.visit(`${Cypress.config().baseUrl}/centreon/home/dashboards`);
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
  cy.wait('@dashboardMetricsTop');
  cy.getByTestId({ testId: 'More actions' }).eq(0).click();
});

When('the dashboard administrator user deletes one of the widgets', () => {
  cy.getByTestId({ testId: 'DeleteIcon' }).click();
  cy.getByLabel({
    label: 'Delete',
    tag: 'li'
  }).realClick();
});

Then('only the contents of the other widget are displayed', () => {
  cy.getByTestId({ testId: 'warning-line-200-tooltip' }).should('be.visible');
  cy.getByTestId({ testId: 'critical-line-400-tooltip' }).should('be.visible');
});

Given('a dashboard with a configured Top Bottom widget', () => {
  cy.insertDashboardWithSingleMetricWidget(dashboards.default, topBottomWidget);
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
  cy.getByTestId({ testId: 'More actions' }).click();
  cy.get('li[aria-label="Edit widget"]').click();
});

When(
  'the dashboard administrator user selects the option to hide the value labels',
  () => {
    cy.getByLabel({
      label: 'Show value labels',
      tag: 'input'
    }).click();
    cy.wait('@dashboardMetricsTop');
  }
);

Then(
  'the value labels for all hosts in the Top Bottom widget are hidden in view mode',
  () => {
    cy.getByTestId({ testId: 'confirm' }).click();
    cy.get('.visx-group text:first-child').should('not.exist');
  }
);

Given('a dashboard containing a Top Bottom widget', () => {
  cy.insertDashboardWithSingleMetricWidget(dashboards.default, topBottomWidget);
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
  cy.getByTestId({ testId: 'More actions' }).click();
  cy.get('li[aria-label="Edit widget"]').click();
});

When(
  'the dashboard administrator user updates the value format of the Top Bottom widget to "raw value"',
  () => {
    cy.get('[class^="MuiAccordionDetails-root"]').eq(1).scrollIntoView();
    cy.contains('Raw value').find('input').click();
  }
);

Then(
  'the displayed value format for this metric has been updated from human-readable to exhaustive',
  () => {
    cy.waitUntil(
      () =>
        cy
          .get('.visx-group text:first-child')
          .invoke('text')
          .should((text) => {
            const metricRegex = /\d+\.\d{3,}/;

            return metricRegex.test(text);
          }),
      { interval: 1000, timeout: 10000 }
    );
  }
);

Given('a dashboard featuring a configured Top Bottom widget', () => {
  cy.insertDashboardWithSingleMetricWidget(dashboards.default, topBottomWidget);
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
  cy.getByTestId({ testId: 'More actions' }).click();
  cy.get('li[aria-label="Edit widget"]').click();
});

When(
  'the dashboard administrator user updates the custom warning threshold',
  () => {
    cy.get('[class^="MuiAccordionDetails-root"]').eq(1).scrollIntoView();
    cy.contains('Custom').find('input').eq(0).click();
    cy.getByLabel({
      label: 'Thresholds',
      tag: 'input'
    }).type('40');
  }
);

Then(
  'the widget is refreshed to display the updated warning threshold on all bars of the Top Bottom widget',
  () => {
    cy.getByTestId({ testId: 'warning-line-40-tooltip' }).should('be.visible');
    cy.getByTestId({ testId: 'critical-line-400-tooltip' }).should(
      'be.visible'
    );
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
      .type('60', { force: true });
  }
);

Then(
  'the widget is refreshed to display the updated critical threshold on all bars of the Top Bottom widget',
  () => {
    cy.getByTestId({ testId: 'warning-line-40-tooltip' }).should('be.visible');
    cy.getByTestId({ testId: 'critical-line-60-tooltip' }).should('be.visible');
  }
);
