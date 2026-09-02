import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { INTERCEPTORS } from 'fixtures/shared/constants/interceptors';
import { PAGES } from 'fixtures/shared/constants/pages';

import {
  checkHostsAreMonitored,
  checkMetricsAreMonitored,
  checkServicesAreMonitored
} from '../../../commons';
import dashboards from '../../../fixtures/dashboards/creation/dashboards.json';
import statuschartWidget from '../../../fixtures/dashboards/creation/widgets/dashboardWithStatusChartWidget.json';
import genericTextWidgets from '../../../fixtures/dashboards/creation/widgets/genericText.json';

const greenCssBackground = 'background: rgb(159, 199, 78)';
const orangeCssBackground = 'background: rgb(252, 196, 129)';
const redCssBackground = 'background: rgb(255, 110, 110)';
const blueCssBackground = 'background: rgb(30, 190, 179)';

const hostGroupName = 'Linux-Servers';

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
  cy.intercept({
    method: 'GET',
    url: INTERCEPTORS.api.generate_reload_pollers
  }).as('generateAndReloadPollers');
  cy.intercept({
    method: 'GET',
    url: INTERCEPTORS.api.navigation_list
  }).as('getNavigationList');
  cy.intercept({
    method: 'GET',
    url: /\/centreon\/api\/latest\/monitoring\/resources.*$/
  }).as('resourceRequest');
  cy.startContainers();
  cy.enableDashboardFeature();
  cy.executeCommandsViaClapi(
    'resources/clapi/config-ACL/dashboard-metrics-graph.json'
  );
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
      name: 'service2',
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
      name: 'service2',
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

  cy.scheduleHostCheck({ host: services.serviceOk.host }).scheduleHostCheck({
    host: services.serviceCritical.host
  });

  ['Disk-/', 'Load', 'Memory', 'Ping'].forEach((service) => {
    cy.scheduleServiceCheck({ host: 'Centreon-Server', service });
  });

  checkMetricsAreMonitored([
    {
      host: 'Centreon-Server',
      name: 'rta',
      service: 'Ping'
    }
  ]);
  cy.logoutViaAPI();
  cy.applyAcl();
});

beforeEach(() => {
  cy.intercept({
    method: 'GET',
    url: INTERCEPTORS.api.navigation_list
  }).as('getNavigationList');
  cy.intercept({
    method: 'GET',
    url: `${INTERCEPTORS.api.dashboard_configuration}**`
  }).as('listAllDashboards');
  cy.intercept({
    method: 'POST',
    url: `${INTERCEPTORS.api.dashboard_configuration}/*/access_rights/contacts`
  }).as('addContactToDashboardShareList');
  cy.intercept({
    method: 'PATCH',
    url: `${INTERCEPTORS.api.dashboard_configuration}/*`
  }).as('updateDashboard');
  cy.intercept({
    method: 'GET',
    url: `${INTERCEPTORS.api.dashboard_configuration}/*`
  }).as('getDashboard');
  cy.intercept({
    method: 'GET',
    url: `${INTERCEPTORS.api.service_status}**`
  }).as('getServiceStatus');
  cy.intercept({
    method: 'GET',
    url: `${INTERCEPTORS.api.hosts_status}**`
  }).as('getHostStatus');
  cy.intercept({
    method: 'GET',
    url: /\/api\/latest\/monitoring\/dashboard\/metrics\/performances\/data\?.*$/
  }).as('performanceData');
  cy.intercept({
    method: 'GET',
    url: /\/centreon\/api\/latest\/monitoring\/resources.*$/
  }).as('resourceRequest');
  cy.loginByTypeOfUser({
    jsonName: 'admin',
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
    cy.contains('div[class*="-addWidgetPanel"] h5', 'Add a widget').click();
  }
);

When(
  'the dashboard administrator user selects the widget type "Status Chart"',
  () => {
    cy.getByTestId({ testId: 'Widget type' }).click();
    cy.contains('Status chart').click();
  }
);

Then(
  'configuration properties for the status chart widget are displayed',
  () => {
    cy.getByLabel({ label: 'Donut chart' }).should('exist');
    cy.getByLabel({ label: 'Pie chart' }).should('exist');
    cy.getByLabel({ label: 'Vertical bar chart' }).should('exist');
  }
);

When(
  'the dashboard administrator user selects resources for the widget',
  () => {
    cy.getByLabel({ label: 'Title' }).type(genericTextWidgets.default.title);
    cy.getByLabel({ label: 'RichTextEditor' })
      .eq(0)
      .type(genericTextWidgets.default.description);
    cy.getByTestId({ testId: 'Resource type' }).realClick();
    cy.getByLabel({ label: 'Host Group' }).click();
    cy.getByTestId({ testId: 'Select resource' }).click();
    cy.contains(hostGroupName).realClick();
  }
);

Then(
  'a donut chart representing the statuses of this list of resources are displayed in the widget preview',
  () => {
    cy.verifyLegendItemStyle(
      0,
      [
        greenCssBackground,
        orangeCssBackground,
        redCssBackground,
        blueCssBackground
      ],
      ['100.0%', '41.7%', '50.0%', '8.3%']
    );
  }
);

When('the user saves the Status Chart widget', () => {
  cy.getByTestId({ testId: 'confirm' }).click();
});

Then("the Status Chart widget is added in the dashboard's layout", () => {
  cy.verifyLegendItemStyle(
    0,
    [
      greenCssBackground,
      orangeCssBackground,
      redCssBackground,
      blueCssBackground
    ],
    ['100.0%', '41.7%', '50.0%', '8.3%'],
    ['41.7%', '50.0%', '100.0%', '33.3%']
  );
});

Given('a dashboard that includes a configured Status Chart widget', () => {
  cy.insertDashboardWithWidget(
    dashboards.default,
    statuschartWidget,
    'centreon-widget-statuschart',
    '/widgets/statuschart'
  );
  cy.editDashboard(dashboards.default.name);
  cy.editWidget(1);
});

When(
  'the dashboard administrator user selects a particular unit in the displayed unit list',
  () => {
    cy.contains('Number').click();
  }
);

Then('the unit of the resources already displayed should be updated', () => {
  cy.verifyLegendItemStyle(
    1,
    [
      greenCssBackground,
      orangeCssBackground,
      redCssBackground,
      blueCssBackground
    ],
    ['50.0%', '8.3%', '16.7%', '33.3%'],
    ['33.3%', '25.0%', '8.3%', '33.3%']
  );
});

Given('a dashboard featuring two Status Chart widgets', () => {
  cy.insertDashboardWithDoubleWidget(
    dashboards.default,
    statuschartWidget,
    statuschartWidget,
    'centreon-widget-statuschart',
    '/widgets/statuschart'
  );
  cy.editDashboard(dashboards.default.name);
  cy.wait('@getDashboard');
  cy.wait('@getServiceStatus');
  cy.wait('@getHostStatus');
  cy.getByTestId({ testId: 'More actions' }).eq(0).click();
});

When('the dashboard administrator user deletes one of the widgets', () => {
  cy.getByLabel({
    label: 'Delete widget',
    tag: 'li'
  }).realClick();
});

Then('only the contents of the other widget are displayed', () => {
  cy.verifyLegendItemStyle(
    1,
    [
      greenCssBackground,
      orangeCssBackground,
      redCssBackground,
      blueCssBackground
    ],
    ['50.0%', '16.7%', '16.7%', '33.3%'],
    ['33.3%', '8.3%', '8.3%', '33.3%']
  );
});

Given('a dashboard having a configured Status Chart widget', () => {
  cy.insertDashboardWithWidget(
    dashboards.default,
    statuschartWidget,
    'centreon-widget-statuschart',
    '/widgets/statuschart'
  );
});

When(
  'the dashboard administrator user duplicates the Status Chart widget',
  () => {
    cy.editDashboard(dashboards.default.name);
    cy.getByTestId({ testId: 'More actions' }).click();
    cy.getByTestId({ testId: 'Duplicate' }).click({ force: true });
  }
);

Then('a second Status Chart widget is displayed on the dashboard', () => {
  cy.verifyLegendItemStyle(
    3,
    [
      greenCssBackground,
      orangeCssBackground,
      redCssBackground,
      blueCssBackground
    ],
    ['50.0%', '16.7%', '16.7%', '33.3%'],
    ['33.3%', '8.3%', '8.3%', '33.3%']
  );
});

Given(
  'a dashboard administrator user configuring a Status Chart widget',
  () => {
    cy.insertDashboardWithWidget(
      dashboards.default,
      statuschartWidget,
      'centreon-widget-statuschart',
      '/widgets/statuschart'
    );
    cy.editDashboard(dashboards.default.name);
    cy.editWidget(1);
  }
);

When(
  'the dashboard administrator user updates the displayed resource type of the widget',
  () => {
    cy.get('input[name="host"].PrivateSwitchBase-input').click();
  }
);

Then(
  'the widget is updated to reflect that change of displayed resource type',
  () => {
    cy.verifyLegendItemStyle(
      1,
      [
        greenCssBackground,
        orangeCssBackground,
        redCssBackground,
        blueCssBackground
      ],
      ['50.0%', '8.3%', '16.7%', '33.3%'],
      ['33.3%', '25.0%', '8.3%', '33.3%']
    );
  }
);

Given('a dashboard with a Status Chart widget', () => {
  cy.insertDashboardWithWidget(
    dashboards.default,
    statuschartWidget,
    'centreon-widget-statuschart',
    '/widgets/statuschart'
  );
  cy.editDashboard(dashboards.default.name);
});

When('the dashboard administrator clicks on a random resource', () => {
  cy.get('[data-testid="Legend"] > *')
    .first()
    .find('a')
    .then((link) => {
      const href = link.attr('href');
      if (href) {
        cy.log('First link found:', href);
        cy.visit(href);
      } else {
        cy.log('No link found.');
      }
    });
});

Then(
  'the user should be redirected to the resource status screen and all the resources must be displayed',
  () => {
    cy.contains(/host2|host3/).should('exist');
  }
);

Given('the dashboard administrator adds more than 20 hosts', () => {
  cy.addMultipleHosts();
  cy.visit(PAGES.monitoring.resourcesStatus);
  cy.waitForElementToBeVisible('[data-testid="CloseIcon"]');

  cy.getByTestId({ testId: 'Clear filter' }).click({ force: true });
  cy.exportConfig();
  cy.waitUntil(
    () => {
      return cy
        .getByLabel({ label: 'Up status hosts', tag: 'a' })
        .invoke('text')
        .then((text) => {
          if (text !== '23') {
            cy.getByTestId({ testId: 'refresh' }).click();
            cy.getByLabel({ label: 'Select all' }).click();
            cy.getByLabel({ label: 'Forced check' }).click();
            cy.getByLabel({ label: 'Select all' }).click();
          }

          return text === '23';
        });
    },
    { interval: 20000, timeout: 600000 }
  );
});

Then('the number of hosts is evaluated to be 23', () => {
  cy.contains('23').should('be.visible');
});
