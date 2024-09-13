/* eslint-disable cypress/unsafe-to-chain-command */
import { Given, When, Then } from '@badeball/cypress-cucumber-preprocessor';

import {
  checkHostsAreMonitored,
  checkServicesAreMonitored,
  checkMetricsAreMonitored
} from '../../../commons';
import dashboardAdministratorUser from '../../../fixtures/users/user-dashboard-administrator.json';
import dashboards from '../../../fixtures/dashboards/creation/dashboards.json';
import genericTextWidgets from '../../../fixtures/dashboards/creation/widgets/genericText.json';
import metricsGraphWidget from '../../../fixtures/dashboards/creation/widgets/metricsGraphWidget.json';
import metricsGraphDoubleWidget from '../../../fixtures/dashboards/creation/widgets/dashboardWithTwometricsGraphWidget.json';
import metricsGraphWithMultipleHosts from '../../../fixtures/dashboards/creation/widgets/metricsGraphWithMultipleHosts.json';
import metricsGraphWithMultipleMetrics from '../../../fixtures/dashboards/creation/widgets/dashboardWithMetricsGraphWidgetWithMultipleMetrics.json';

const services = {
  serviceCritical: {
    host: 'host3',
    name: 'service3',
    template: 'SNMP-Linux-Load-Average'
  },
  serviceOk: { host: 'host2', name: 'service_test_ok', template: 'Ping-LAN' },
  serviceWarning: {
    host: 'host2',
    name: 'service2',
    template: 'SNMP-Linux-Memory'
  }
};
const resultsToSubmit = [
  {
    host: services.serviceWarning.host,
    output: 'submit_status_2',
    service: services.serviceCritical.name,
    status: 'critical'
  },
  {
    host: services.serviceWarning.host,
    output: 'submit_status_2',
    service: services.serviceWarning.name,
    status: 'warning'
  },
  {
    host: services.serviceWarning.host,
    output: 'submit_status_2',
    service: services.serviceOk.name,
    status: 'ok'
  },
  {
    host: services.serviceCritical.host,
    output: 'submit_status_2',
    service: services.serviceOk.name,
    status: 'ok'
  }
];

before(() => {
  cy.startContainers();
  cy.enableDashboardFeature();
  cy.executeCommandsViaClapi(
    'resources/clapi/config-ACL/dashboard-metrics-graph.json'
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
    method: 'POST',
    url: `/centreon/api/latest/configuration/dashboards/*/access_rights/contacts`
  }).as('addContactToDashboardShareList');
  cy.intercept({
    method: 'GET',
    url: /\/api\/latest\/monitoring\/dashboard\/metrics\/performances\/data\?.*$/
  }).as('performanceData');
  cy.addHost({
    hostGroup: 'Linux-Servers',
    name: services.serviceOk.host,
    template: 'generic-host'
  })
    .addService({
      activeCheckEnabled: false,
      host: services.serviceOk.host,
      maxCheckAttempts: 1,
      name: services.serviceOk.name,
      template: services.serviceOk.template
    })
    .addService({
      activeCheckEnabled: false,
      host: services.serviceOk.host,
      maxCheckAttempts: 1,
      name: services.serviceWarning.name,
      template: services.serviceWarning.template
    })
    .addService({
      activeCheckEnabled: false,
      host: services.serviceOk.host,
      maxCheckAttempts: 1,
      name: services.serviceCritical.name,
      template: services.serviceCritical.template
    });
  cy.addHost({
    hostGroup: 'Linux-Servers',
    name: services.serviceCritical.host,
    template: 'generic-host'
  })
    .addService({
      activeCheckEnabled: false,
      host: services.serviceCritical.host,
      maxCheckAttempts: 1,
      name: services.serviceOk.name,
      template: services.serviceOk.template
    })
    .addService({
      activeCheckEnabled: false,
      host: services.serviceCritical.host,
      maxCheckAttempts: 1,
      name: services.serviceWarning.name,
      template: services.serviceWarning.template
    })
    .addService({
      activeCheckEnabled: false,
      host: services.serviceCritical.host,
      maxCheckAttempts: 1,
      name: services.serviceCritical.name,
      template: services.serviceCritical.template
    })
    .applyPollerConfiguration();

  cy.loginByTypeOfUser({
    jsonName: 'admin'
  });

  cy.scheduleServiceCheck({ host: 'Centreon-Server', service: 'Ping' });

  checkHostsAreMonitored([
    { name: services.serviceOk.host },
    { name: services.serviceCritical.host }
  ]);
  checkServicesAreMonitored([
    { name: services.serviceCritical.name },
    { name: services.serviceOk.name }
  ]);
  cy.submitResults(resultsToSubmit);
  checkServicesAreMonitored([
    { name: services.serviceCritical.name, status: 'critical' },
    { name: services.serviceOk.name, status: 'ok' }
  ]);

  checkMetricsAreMonitored([
    {
      host: 'Centreon-Server',
      name: 'rta',
      service: 'Ping'
    }
  ]);

  checkServicesAreMonitored([
    {
      name: 'Ping',
      status: 'ok'
    }
  ]);

  cy.logoutViaAPI();
  cy.applyAcl();
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
  cy.insertDashboardWithWidget(dashboards.default, metricsGraphWidget);
  cy.editDashboard(dashboards.default.name);
  cy.wait('@performanceData');
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
  cy.insertDashboardWithWidget(dashboards.default, metricsGraphWidget);
  cy.visitDashboard(dashboards.default.name);
});

When(
  'the dashboard administrator user duplicates the Metrics Graph widget',
  () => {
    cy.getByTestId({ testId: 'RefreshIcon' }).should('be.visible');
    cy.getByTestId({ testId: 'RefreshIcon' }).click();
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
  cy.insertDashboardWithWidget(dashboards.default, metricsGraphDoubleWidget);
  cy.editDashboard(dashboards.default.name);
  cy.getByTestId({ testId: 'More actions' }).eq(0).click();
  cy.wait('@performanceData');
});

When(
  'the dashboard administrator user deletes one of the Metrics Graph widgets',
  () => {
    cy.getByTestId({ testId: 'DeleteIcon' }).click();
    cy.getByLabel({
      label: 'Delete',
      tag: 'button'
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
  cy.insertDashboardWithWidget(dashboards.default, metricsGraphWidget);
  cy.editDashboard(dashboards.default.name);
  cy.editWidget(1);
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
    cy.contains('Centreon-Server: Packet Loss').should('exist');
    cy.get('g.visx-axis-left').should('exist');
    cy.get('g.visx-axis-right').should('exist');
  }
);

Then('the thresholds are automatically hidden', () => {
  cy.get('span[data-checked="false"]').should('exist');
  cy.getByTestId({ testId: 'confirm' }).click();
  cy.wait('@performanceData');
  cy.getByTestId({ testId: 'warning-line-200-tooltip' }).should('not.exist');
  cy.getByTestId({ testId: 'critical-line-400-tooltip' }).should('not.exist');
});

Given('a dashboard with a configured Metrics Graph widget', () => {
  cy.insertDashboardWithWidget(dashboards.default, metricsGraphWidget);
  cy.editDashboard(dashboards.default.name);
  cy.editWidget(1);
  cy.wait('@performanceData');
  cy.getByTestId({ testId: 'Select metric' }).should('be.enabled').click();
});

When('the dashboard administrator selects more than two metric units', () => {
  cy.getByTestId({ testId: 'pl' }).realClick();
  cy.getByTestId({ testId: 'rtmax' }).realClick();
});

Then(
  'a message should be displayed indicating that thresholds are disabled',
  () => {
    cy.contains(
      'Thresholds are automatically hidden when you select several metrics with different units.'
    ).should('exist');
  }
);

Given('a dashboard having Metrics Graph widget with multiple hosts', () => {
  cy.insertDashboardWithWidget(
    dashboards.default,
    metricsGraphWithMultipleHosts
  );
  cy.editDashboard(dashboards.default.name);
  cy.editWidget(1);
  cy.wait('@performanceData');
});

When('the dashboard administrator opens service list', () => {
  cy.getByLabel({ label: 'Title' }).type(genericTextWidgets.default.title);
  cy.getByLabel({ label: 'RichTextEditor' })
    .eq(0)
    .type(genericTextWidgets.default.description);
  cy.getByLabel({ label: 'Open' }).eq(2).click();
});

Then(
  'only the services associated with the selected hosts should be displayed',
  () => {
    cy.contains('Ping').should('be.visible');
  }
);

Given(
  'a dashboard featuring a configured Metrics Graph widget with multiple metrics',
  () => {
    cy.insertDashboardWithWidget(
      dashboards.default,
      metricsGraphWithMultipleMetrics
    );
    cy.editDashboard(dashboards.default.name);
    cy.getByTestId({ testId: 'More actions' }).click();
    cy.getByLabel({
      label: 'Edit widget',
      tag: 'li'
    }).realClick();
    cy.wait('@performanceData');
  }
);

When('the dashboard administrator activates the curve points settings', () => {
  cy.getByTestId({ testId: '-summary' }).eq(2).click();
  cy.getByLabel({
    label: 'Display curve points',
    tag: 'input'
  }).click();
});

Then('the curve points should be displayed on the graph', () => {
  cy.get('circle').should('have.length.greaterThan', 0);
});

When('the dashboard administrator clicks on the custom button', () => {
  cy.getByTestId({ testId: '-summary' }).eq(2).click();
  cy.getByLabel({
    label: 'Display curve points',
    tag: 'input'
  }).realClick();
  cy.getByLabel({
    label: 'Custom',
    tag: 'button'
  }).click();
});

When(
  'the dashboard administrator updates the line width settings using the gauge',
  () => {
    cy.get('#sliderinput').eq(0).type('10');
  }
);

Then('the line width should be updated in the graph', () => {
  cy.get('path[stroke-width="10"]').should('exist');
});

When('the dashboard administrator clicks on the show button', () => {
  cy.getByTestId({ testId: '-summary' }).eq(2).click();
  cy.getByTestId({ testId: 'show' }).click();
});

When('the dashboard administrator updates the opacity using the gauge', () => {
  cy.get('#sliderinput').eq(0).type('100%');
});

Then('the opacity should be updated in the graph', () => {
  cy.get('path[fill^="rgba"][fill$=", 1)"]').should('exist');
});

When('the dashboard administrator clicks on the Dashed button', () => {
  cy.getByTestId({ testId: '-summary' }).eq(2).click();
  cy.getByTestId({ testId: 'dash' }).click();
});

When(
  'the dashboard administrator updates the dash and space input values',
  () => {
    cy.getByLabel({
      label: 'Dash width',
      tag: 'input'
    })
      .clear()
      .type('7');
    cy.getByLabel({
      label: 'Space',
      tag: 'input'
    })
      .clear()
      .type('7');
  }
);

Then('the line style should be updated based on the changed values', () => {
  cy.get('path[stroke-dasharray="7 7"]').should('exist');
});

When('the dashboard administrator clicks on the zero-centred button', () => {
  cy.getByTestId({ testId: '-summary' }).eq(2).click();
  cy.getByLabel({
    label: 'Zero-centered',
    tag: 'input'
  }).click();
});

Then(
  'the Metrics Graph widget should be refreshed to center the values around 0',
  () => {
    cy.get('text').contains('tspan', '0 ms').should('exist');
  }
);

When('the dashboard administrator selects the list display mode', () => {
  cy.getByTestId({ testId: '-summary' }).eq(2).click();
  cy.getByTestId({ testId: 'list' }).click();
});

Then(
  'the Metrics Graph widget should refresh to display items in a list format',
  () => {
    cy.get(
      'div[class$="-items"][data-as-list="true"][data-mode="normal"]'
    ).should('exist');
  }
);

When(
  'the dashboard administrator clicks the "Display as Bar Chart" button',
  () => {
    cy.getByTestId({ testId: '-summary' }).eq(2).click();
    cy.getByLabel({
      label: 'Bar',
      tag: 'div'
    }).click();
  }
);

Then('the graph should be displayed as a bar chart', () => {
  cy.get('path[data-testid*="stacked-bar-"]').should('exist');
});

When(
  'the dashboard administrator selects a custom time period for the graph',
  () => {
    cy.contains('Last hour').realClick()
    cy.contains('Customize').realClick()
  }
);

Then(
  'the graph updates to reflect data for the selected time period',
  () => {
    cy.getByLabel({
      label: 'From',
      tag: 'div'
    }).should('be.visible')
    cy.getByLabel({
      label: 'To',
      tag: 'div'
    }).should('be.visible')
  }
);