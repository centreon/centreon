import { Given, When, Then } from '@badeball/cypress-cucumber-preprocessor';

import {
  checkHostsAreMonitored,
  checkServicesAreMonitored,
  checkMetricsAreMonitored
} from '../../../commons';
import dashboards from '../../../fixtures/dashboards/creation/dashboards.json';
import genericTextWidgets from '../../../fixtures/dashboards/creation/widgets/genericText.json';
import statuschartWidget from '../../../fixtures/dashboards/creation/widgets/dashboardWithStatusChartWidget.json';

const greenCssBackground = 'background: rgb(136, 185, 34)';
const orangeCssBackground = 'background: rgb(253, 155, 39)';
const redCssBackground = 'background: rgb(255, 102, 102)';
const greyCssBackground = 'background: rgb(227, 227, 227)';
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
    url: '/centreon/api/latest/configuration/monitoring-servers/generate-and-reload'
  }).as('generateAndReloadPollers');
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/internal.php?object=centreon_topology&action=navigationList'
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
    url: `/centreon/api/latest/configuration/dashboards/*`
  }).as('getDashboard');
  cy.intercept({
    method: 'GET',
    url: `centreon/api/latest/monitoring/services/status**`
  }).as('getServiceStatus');
  cy.intercept({
    method: 'GET',
    url: `centreon/api/latest/monitoring/hosts/status**`
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
    cy.getByTestId({ testId: 'AddIcon' }).should('have.length', 1).click();
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
    cy.getByTestId({ testId: 'CheckCircleIcon' }).should('exist');
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
    cy.getByTestId({ testId: 'up' }).should('exist');
    cy.getByTestId({ testId: 'critical' }).should('exist');
    cy.getByTestId({ testId: 'warning' }).should('exist');
    cy.getByTestId({ testId: 'unknown' }).should('exist');
    cy.getByTestId({ testId: 'unknown' }).should('exist');
    cy.getByTestId({ testId: 'ok' }).should('exist');
    cy.getByTestId({ testId: 'pending' }).should('exist');
    cy.getByTestId({ testId: 'Legend' }).eq(0).should('exist');
    cy.getByTestId({ testId: 'Legend' }).eq(1).should('exist');
    cy.verifyLegendItemStyle(
      0,
      [
        greenCssBackground,
        redCssBackground,
        greyCssBackground,
        blueCssBackground
      ],
      ['100.0%', '0.0%', '0.0%', '0.0%']
    );
    cy.getByLabel({ label: 'Unknown status services', tag: 'a' })
      .invoke('text')
      .then((text) => {
        const labelValue = parseInt(text, 10);
        const styleMap = {
          0: ['30.0%', '10.0%', '10.0%', '0.0%', '50.0%'],
          1: ['30.0%', '10.0%', '10.0%', '10.0%', '40.0%'],
          2: ['30.0%', '10.0%', '10.0%', '20.0%', '30.0%'],
          3: ['30.0%', '10.0%', '10.0%', '30.0%', '20.0%']
        };
        const styles = styleMap[labelValue] || styleMap[3];
        cy.verifyLegendItemStyle(
          1,
          [
            greenCssBackground,
            orangeCssBackground,
            redCssBackground,
            greyCssBackground,
            blueCssBackground
          ],
          styles
        );
      });
  }
);

When('the user saves the Status Chart widget', () => {
  cy.getByTestId({ testId: 'confirm' }).click();
});

Then("the Status Chart widget is added in the dashboard's layout", () => {
  cy.getByTestId({ testId: 'up' }).should('exist');
  cy.getByTestId({ testId: 'critical' }).should('exist');
  cy.getByTestId({ testId: 'warning' }).should('exist');
  cy.getByTestId({ testId: 'unknown' }).should('exist');
  cy.getByTestId({ testId: 'unknown' }).should('exist');
  cy.getByTestId({ testId: 'ok' }).should('exist');
  cy.getByTestId({ testId: 'pending' }).should('exist');
  cy.getByTestId({ testId: 'Legend' }).eq(0).should('exist');
  cy.getByTestId({ testId: 'Legend' }).eq(1).should('exist');
  cy.verifyLegendItemStyle(
    0,
    [
      greenCssBackground,
      redCssBackground,
      greyCssBackground,
      blueCssBackground
    ],
    ['100.0%', '0.0%', '0.0%', '0.0%']
  );
  cy.getByLabel({ label: 'Unknown status services', tag: 'a' })
    .invoke('text')
    .then((text) => {
      const labelValue = parseInt(text, 10);
      const styleMap = {
        0: ['30.0%', '10.0%', '10.0%', '0.0%', '50.0%'],
        1: ['30.0%', '10.0%', '10.0%', '10.0%', '40.0%'],
        2: ['30.0%', '10.0%', '10.0%', '20.0%', '30.0%'],
        3: ['30.0%', '10.0%', '10.0%', '30.0%', '20.0%']
      };
      const styles = styleMap[labelValue] || styleMap[3];
      cy.verifyLegendItemStyle(
        1,
        [
          greenCssBackground,
          orangeCssBackground,
          redCssBackground,
          greyCssBackground,
          blueCssBackground
        ],
        styles
      );
    });
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
  cy.getByLabel({ label: 'Unknown status services', tag: 'a' })
    .invoke('text')
    .then((text) => {
      const labelValue = parseInt(text, 10);
      const styleMap = {
        0: ['3', '1', '1', '0', '5'],
        1: ['3', '1', '1', '1', '4'],
        2: ['3', '1', '1', '2', '3'],
        3: ['3', '1', '1', '3', '2']
      };
      const styles = styleMap[labelValue] || styleMap[3];
      cy.verifyLegendItemStyle(
        1,
        [
          greenCssBackground,
          orangeCssBackground,
          redCssBackground,
          greyCssBackground,
          blueCssBackground
        ],
        styles
      );
    });
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
  cy.getByTestId({ testId: 'DeleteIcon' }).click();
  cy.getByLabel({
    label: 'Delete',
    tag: 'button'
  }).realClick();
});

Then('only the contents of the other widget are displayed', () => {
  cy.getByLabel({ label: 'Unknown status services', tag: 'a' })
    .invoke('text')
    .then((text) => {
      const labelValue = parseInt(text, 10);
      const styleMap = {
        0: ['3', '1', '1', '0', '5'],
        1: ['3', '1', '1', '1', '4'],
        2: ['3', '1', '1', '2', '3'],
        3: ['3', '1', '1', '3', '2']
      };
      const styles = styleMap[labelValue] || styleMap[3];
      cy.verifyLegendItemStyle(
        1,
        [
          greenCssBackground,
          orangeCssBackground,
          redCssBackground,
          greyCssBackground,
          blueCssBackground
        ],
        styles
      );
    });
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
    cy.getByTestId({ testId: 'MoreHorizIcon' }).click();
    cy.getByTestId({ testId: 'ContentCopyIcon' }).click({ force: true });
  }
);

Then('a second Status Chart widget is displayed on the dashboard', () => {
  cy.getByLabel({ label: 'Unknown status services', tag: 'a' })
  .invoke('text')
  .then((text) => {
    const labelValue = parseInt(text, 10);
    const styleMap = {
      0: ['30.0%', '10.0%', '10.0%', '0.0%', '50.0%'],
      1: ['30.0%', '10.0%', '10.0%', '30.0%', '40.0%'],
      2: ['30.0%', '10.0%', '10.0%', '20.0%', '30.0%'],
      3: ['30.0%', '10.0%', '10.0%', '30.0%', '20.0%']
    };
    const styles = styleMap[labelValue] || styleMap[3];
    cy.verifyLegendItemStyle(
      3,
      [greenCssBackground, orangeCssBackground, redCssBackground, greyCssBackground, blueCssBackground],
      styles
    );
  });
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
    cy.getByLabel({ label: 'Unknown status services', tag: 'a' })
      .invoke('text')
      .then((text) => {
        const labelValue = parseInt(text, 10);
        const styleMap = {
          0: ['30.0%', '10.0%', '10.0%', '0.0%', '50.0%'],
          1: ['30.0%', '10.0%', '10.0%', '10.0%', '40.0%'],
          2: ['30.0%', '10.0%', '10.0%', '20.0%', '30.0%'],
          3: ['30.0%', '10.0%', '10.0%', '30.0%', '20.0%']
        };
        const styles = styleMap[labelValue] || styleMap[3];
        cy.verifyLegendItemStyle(
          1,
          [
            greenCssBackground,
            orangeCssBackground,
            redCssBackground,
            greyCssBackground,
            blueCssBackground
          ],
          styles
        );
      });
  }
);

Given('a dashboard with a Status Chart widget', () => {
  cy.getByLabel({ label: 'Unknown status services', tag: 'a' })
    .invoke('text')
    .then((text) => {
      const labelValue = parseInt(text, 10);
      const styleMap = {
        0: ['30.0%', '10.0%', '10.0%', '0.0%', '50.0%'],
        1: ['30.0%', '10.0%', '10.0%', '10.0%', '40.0%'],
        2: ['30.0%', '10.0%', '10.0%', '20.0%', '30.0%'],
        3: ['30.0%', '10.0%', '10.0%', '30.0%', '20.0%']
      };
      const styles = styleMap[labelValue] || styleMap[3];
      cy.verifyLegendItemStyle(
        1,
        [
          greenCssBackground,
          orangeCssBackground,
          redCssBackground,
          greyCssBackground,
          blueCssBackground
        ],
        styles
      );
    });
  cy.editDashboard(dashboards.default.name);
});

When('the dashboard administrator clicks on a random resource', () => {
  cy.get('[data-testid="Legend"] > *')
    .first()
    .find('a')
    .then(($link) => {
      const href = $link.attr('href');
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
    cy.contains('host2').should('exist');
  }
);

Given('the dashboard administrator adds more than 20 hosts', () => {
  cy.addMultipleHosts();
  cy.navigateTo({
    page: 'Resources Status',
    rootItemNumber: 1
  });
  cy.waitForElementToBeVisible('[data-testid="CloseIcon"]');
  cy.getByTestId({ testId: 'CloseIcon' }).click();
  cy.exportConfig();
  cy.waitUntil(
    () => {
      return cy
        .getByLabel({ label: 'Up status hosts', tag: 'a' })
        .invoke('text')
        .then((text) => {
          if (text !== '23') {
            cy.getByTestId({ testId: 'RefreshIcon' }).click();
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

Then(
  'the number of hosts is evaluated to be 23',
  () => {
    cy.get('[data-testid="pieChart"]')
    .eq(0)
    .find('text tspan')
    .invoke('text')
    .then((text) => {
      cy.log(text);
      expect(text.trim()).to.eq('23hosts');
   });
  }
);