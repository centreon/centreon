import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import 'cypress-real-events/support';

import {
  checkMetricsAreMonitored,
  checkServicesAreMonitored
} from '../../../commons';
import dashboards from '../../../fixtures/dashboards/creation/dashboards.json';
import dashboardAdministratorUser from '../../../fixtures/users/user-dashboard-administrator.json';
import genericTextWidgets from '../../../fixtures/dashboards/creation/widgets/genericText.json';
import metrics from '../../../fixtures/dashboards/creation/widgets/metrics.json';
import singleMetricPayload from '../../../fixtures/dashboards/creation/widgets/singleMetricPayloadPl.json';
import singleMetricPayloadRta from '../../../fixtures/dashboards/creation/widgets/singleMetricPayloadRta.json';
import singleMetricDoubleWidgets from '../../../fixtures/dashboards/creation/widgets/dashboardWithTwoWidgets.json';

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
    method: 'POST',
    url: '/centreon/api/latest/configuration/dashboards'
  }).as('createDashboard');

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
    method: 'POST',
    url: '/centreon/api/latest/configuration/dashboards'
  }).as('createDashboard');
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
    cy.visitDashboards();
    cy.contains(dashboards.default.name).click();
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

When('selects the widget type "Single metric"', () => {
  cy.getByTestId({ testId: 'Widget type' }).click();
  cy.contains('Single metric').click();
});

Then(
  'configuration properties for the Single metric widget are displayed',
  () => {
    cy.contains('Widget properties').should('exist');
    cy.getByLabel({ label: 'Title' }).should('exist');
    cy.getByLabel({ label: 'RichTextEditor' }).should('exist');
    cy.contains('Value settings').should('exist');
    cy.get('[class^="MuiAccordionDetails-root"]').eq(1).scrollIntoView();
    cy.get('[class*="displayTypeContainer"]').should('be.visible');
  }
);

When(
  'the dashboard administrator user selects a resource and the metric for the widget to report on',
  () => {
    cy.getByLabel({ label: 'Title' }).type(genericTextWidgets.default.title);
    cy.getByLabel({ label: 'RichTextEditor' })
      .eq(0)
      .type(genericTextWidgets.default.description);
    cy.getByTestId({ testId: 'Select resource' }).eq(0).click();
    cy.contains('Centreon-Server').realClick();
    cy.getByTestId({ testId: 'Select resource' }).eq(1).click();
    cy.contains('Ping').realClick();
    cy.getByTestId({ testId: 'Select metric' }).should('be.enabled').click();
    cy.contains('rta (ms)').realClick();
  }
);

Then('information about this metric is displayed in the widget preview', () => {
  cy.verifyGraphContainer(metrics);
});

When('the user saves the Single metric widget', () => {
  cy.getByTestId({ testId: 'confirm' }).click();
});

Then("the Single metric widget is added in the dashboard's layout", () => {
  cy.get('[class*="graphContainer"]').should('be.visible');
});

Then('the information about the selected metric is displayed', () => {
  cy.verifyGraphContainer(metrics);
});

Given('a dashboard featuring a single Single Metric widget', () => {
  cy.insertDashboardWithWidget(dashboards.default, singleMetricPayload);
  cy.visitDashboards();
  cy.contains(dashboards.default.name).click();
});

When(
  'the dashboard administrator user duplicates the Single Metric widget',
  () => {
    cy.visitDashboards();
    cy.getByTestId({ testId: 'MoreHorizIcon' }).click();
    cy.getByTestId({ testId: 'RefreshIcon' }).click();
    cy.getByTestId({ testId: 'MoreHorizIcon' }).click();
    cy.getByTestId({ testId: 'ContentCopyIcon' }).click();
  }
);

// Then('a second Single Metric widget is displayed on the dashboard', () => {
//   cy.get('[class*="graphContainer"]').eq(1).should('be.visible');
// });

// Then('the second widget reports on the same metric as the first widget', () => {
//   cy.get('[class*="MuiTypography-h2"]')
//     .eq(1)
//     .then(($element) => {
//       const text = $element.text();
//       expect(text).to.include('%');
//     });
// });

// Then('the second widget has the same properties as the first widget', () => {
//   cy.verifyDuplicatesGraphContainer(metrics);
// });

Given(
  'a dashboard with a Single Metric widget displaying a human-readable value format',
  () => {
    cy.insertDashboardWithWidget(dashboards.default, singleMetricPayloadRta);
  }
);

When(
  'the dashboard administrator user updates the value format of the Single Metric widget to "raw value"',
  () => {
    cy.editDashboard(dashboards.default.name);
    cy.editWidget(1);
    cy.get('[class^="MuiAccordionDetails-root"]').eq(1).scrollIntoView();
    cy.contains('Raw value').find('input').click();
  }
);

Then(
  'the displayed value format for this metric has been updated from human-readable to exhaustive',
  () => {
    cy.get('[class*="MuiTypography-h2"]')
      .invoke('text')
      .then((text) => {
        if (parseFloat(text) !== 0) {
          expect(text).to.match(/\d+\.\d{2,}/);
        }
      });
  }
);

Given('a dashboard containing a Single Metric widget', () => {
  cy.insertDashboardWithWidget(dashboards.default, singleMetricPayloadRta);
});

When(
  'the dashboard administrator user updates the custom warning threshold to a value below the current value',
  () => {
    cy.editDashboard(dashboards.default.name);
    cy.editWidget(1);
    cy.get('[class^="MuiAccordionDetails-root"]').eq(1).scrollIntoView();
    cy.contains('Custom').find('input').eq(0).click();
    cy.getByLabel({
      label: 'Thresholds',
      tag: 'input'
    }).type('40');
  }
);

Then(
  'the widget is refreshed to make it look like the metric is in a warning state',
  () => {
    cy.contains(metrics.customValues.warning).should('be.visible');
  }
);

When(
  'the dashboard administrator user updates the custom critical threshold to a value below the current value',
  () => {
    cy.get('input[type="radio"][value="custom"]').eq(1).click();
    cy.getByLabel({
      label: 'Thresholds',
      tag: 'input'
    })
      .eq(1)
      .type('40');
  }
);

Then(
  'the widget is refreshed to make it look like the metric is in a critical state',
  () => {
    cy.contains(metrics.customValues.critical).should('be.visible');
  }
);

Given('a dashboard featuring a Single Metric widget', () => {
  cy.insertDashboardWithWidget(dashboards.default, singleMetricPayloadRta);
});

When(
  'the dashboard administrator user changes the display type of the widget to a gauge',
  () => {
    cy.editDashboard(dashboards.default.name);
    cy.editWidget(1);
    cy.getByTestId({ testId: 'SpeedIcon' }).click();
  }
);

Then(
  'the information reported by the widget is now displayed as a gauge',
  () => {
    cy.get('[class="visx-group"]').should('exist');
  }
);

When(
  'the dashboard administrator user changes the display type of the widget to a bar chart',
  () => {
    cy.getByTestId({ testId: 'BarChartIcon' }).click();
  }
);

Then(
  'the information reported by the widget is now displayed as a bar chart',
  () => {
    cy.get('[class*="visx-bar"]').should('exist');
  }
);

Given('a dashboard featuring two Single Metric widgets', () => {
  cy.insertDashboardWithWidget(dashboards.default, singleMetricDoubleWidgets);
});

When('the dashboard administrator user deletes one of the widgets', () => {
  cy.editDashboard(dashboards.default.name);
  cy.getByTestId({ testId: 'More actions' }).eq(0).click();
  cy.getByTestId({ testId: 'DeleteIcon' }).click();
  cy.getByLabel({
    label: 'Delete',
    tag: 'button'
  }).realClick();
});

Then('only the contents of the other widget are displayed', () => {
  cy.get('.react-grid-item').should('be.visible');
});
