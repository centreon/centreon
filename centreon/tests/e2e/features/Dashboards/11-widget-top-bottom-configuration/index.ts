import { Given, When, Then } from '@badeball/cypress-cucumber-preprocessor';

import {
  checkMetricsAreMonitored,
  checkServicesAreMonitored
} from '../../../commons';
import dashboards from '../../../fixtures/dashboards/creation/dashboards.json';
import dashboardAdministratorUser from '../../../fixtures/users/user-dashboard-administrator.json';
import topBottomWidget from '../../../fixtures/dashboards/creation/widgets/dashboardWithTopBottomWidget.json';
import dashbboardWithTwoTopBottomWidgets from '../../../fixtures/dashboards/creation/widgets/dashboardWithTwoTopBottomWidgets.json';
import genericTextWidgets from '../../../fixtures/dashboards/creation/widgets/genericText.json';

const hostName = 'Centreon-Server';
const hostGroupName = 'Linux-Servers';

before(() => {
  cy.startContainers();
  cy.enableDashboardFeature();
  cy.executeCommandsViaClapi(
    'resources/clapi/config-ACL/dashboard-widget-metrics.json'
  );
  cy.applyAcl();
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
      host: hostName,
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
});

afterEach(() => {
  cy.requestOnDatabase({
    database: 'centreon',
    query: 'DELETE FROM dashboard'
  });
});

after(() => {
  cy.stopContainers();
});

Given(
  "a dashboard in the dashboard administrator user's dashboard library",
  () => {
    cy.insertDashboard({ ...dashboards.default });
    cy.visitDashboard(dashboards.default.name);
  }
);

When(
  'the dashboard administrator user selects the option to add a new widget',
  () => {
    cy.get('*[class^="react-grid-layout"]').children().should('have.length', 0);
    cy.getByTestId({ testId: 'edit_dashboard' }).click();
    cy.getByTestId({ testId: 'AddIcon' }).should('have.length', 1).click();
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
    cy.contains(hostGroupName).realClick();
    cy.getByTestId({ testId: 'Select metric' }).click();
    cy.getByTestId({ testId: 'rta' }).realClick();
    cy.wait('@dashboardMetricsTop');
  }
);

Then(
  'a top of best-performing resources for this metbric are displayed in the widget preview',
  () => {
    cy.getByTestId({ testId: 'warning-line-200-tooltip' }).should('exist');
    cy.getByTestId({ testId: 'critical-line-400-tooltip' }).should('exist');
    cy.contains('#1 Centreon-Server_Ping').should('exist');
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
  cy.insertDashboardWithWidget(dashboards.default, topBottomWidget);
  cy.editDashboard(dashboards.default.name);
  cy.editWidget(1);
  cy.getByTestId({ testId: 'warning-line-200-tooltip' }).should('be.visible');
});

When(
  'the dashboard administrator user removes a host from the dataset selection of the Top Bottom widget',
  () => {
    cy.contains(hostName)
      .parent()
      .getByTestId({ testId: 'CancelIcon' })
      .eq(0)
      .click();
  }
);

Then(
  'the bar associated with the host is removed from the Top Bottom widget preview',
  () => {
    // Uncomment once MON-33311 is fixed
    // cy.getByTestId({ testId: 'warning-line-200-tooltip' })
    //   .eq(1)
    //   .should('not.exist');
    // cy.getByTestId({ testId: 'critical-line-400-tooltip' })
    //   .eq(1)
    //   .should('not.exist');
  }
);

When(
  'the dashboard administrator user adds a host from the dataset selection of the Top Bottom widget',
  () => {
    cy.getByTestId({ testId: 'Resource type' }).realClick();
    cy.getByLabel({ label: 'Host Group' }).click();
    cy.getByTestId({ testId: 'Select resource' }).click();
    cy.contains(hostGroupName).realClick();
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
  cy.insertDashboardWithWidget(dashboards.default, topBottomWidget);
  cy.visitDashboard(dashboards.default.name);
});

When(
  'the dashboard administrator user duplicates the Top Bottom widget',
  () => {
    cy.getByTestId({ testId: 'RefreshIcon' }).should('be.visible');
    cy.getByTestId({ testId: 'RefreshIcon' }).click();
    cy.getByLabel({
      label: 'Edit dashboard',
      tag: 'button'
    }).click();
    cy.getByTestId({ testId: 'More actions' }).click();
    cy.getByTestId({ testId: 'ContentCopyIcon' }).click();
  }
);

Then('a second Top Bottom widget is displayed on the dashboard', () => {
  cy.getByTestId({ testId: 'warning-line-200-tooltip' }).eq(1).should('exist');
  cy.getByTestId({ testId: 'critical-line-400-tooltip' }).eq(1).should('exist');
});

Given('a dashboard featuring two Top Bottom widgets', () => {
  cy.insertDashboardWithWidget(
    dashboards.default,
    dashbboardWithTwoTopBottomWidgets
  );
  cy.editDashboard(dashboards.default.name);
  cy.wait('@dashboardMetricsTop');
  cy.getByTestId({ testId: 'More actions' }).eq(0).click();
});

When('the dashboard administrator user deletes one of the widgets', () => {
  cy.getByTestId({ testId: 'DeleteIcon' }).click();
  cy.getByLabel({
    label: 'Delete',
    tag: 'button'
  }).realClick();
});

Then('only the contents of the other widget are displayed', () => {
  cy.getByTestId({ testId: 'warning-line-200-tooltip' }).should('exist');
  cy.getByTestId({ testId: 'critical-line-400-tooltip' }).should('exist');
});

Given('a dashboard with a configured Top Bottom widget', () => {
  cy.insertDashboardWithWidget(dashboards.default, topBottomWidget);
  cy.editDashboard(dashboards.default.name);
  cy.editWidget(1);
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
  cy.insertDashboardWithWidget(dashboards.default, topBottomWidget);
  cy.editDashboard(dashboards.default.name);
  cy.editWidget(1);
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
  cy.insertDashboardWithWidget(dashboards.default, topBottomWidget);
  cy.editDashboard(dashboards.default.name);
  cy.editWidget(1);
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
    cy.getByTestId({ testId: 'warning-line-40-tooltip' }).should('exist');
    cy.getByTestId({ testId: 'critical-line-400-tooltip' }).should('exist');
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
    cy.getByTestId({ testId: 'warning-line-40-tooltip' }).should('exist');
    cy.getByTestId({ testId: 'critical-line-60-tooltip' }).should('exist');
  }
);

Given('a dashboard with a Top bottom widget', () => {
  cy.insertDashboardWithWidget(dashboards.default, topBottomWidget);
  cy.editDashboard(dashboards.default.name);
  cy.contains('Centreon-Server_Ping').should('be.visible');
});

When('the dashboard administrator clicks on a random resource', () => {
  cy.get('[data-testid="link to Ping"]')
    .invoke('attr', 'href')
    .then((href) => {
      expect(href).to.exist;
      cy.visit(href);
    });
});

Then(
  'the user should be redirected to the resource status screen and all the resources must be displayed',
  () => {
    cy.contains('Ping').should('exist');
    cy.contains('Centreon-Server').should('exist');
  }
);
