import { Given, When, Then } from '@badeball/cypress-cucumber-preprocessor';

import {
  checkHostsAreMonitored,
  checkServicesAreMonitored,
  checkMetricsAreMonitored
} from '../../../commons';
import dashboardAdministratorUser from '../../../fixtures/users/user-dashboard-administrator.json';
import dashboards from '../../../fixtures/dashboards/creation/dashboards.json';
import resourceTable from '../../../fixtures/dashboards/creation/widgets/dashboardWithResourceTableWidget.json';
import genericTextWidgets from '../../../fixtures/dashboards/creation/widgets/genericText.json';

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
    })
    .applyPollerConfiguration();

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

  ['Disk-/', 'Load', 'Memory', 'Ping'].forEach((service) => {
    cy.scheduleServiceCheck({ host: 'Centreon-Server', service });
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
    url: /\/api\/latest\/monitoring\/dashboard\/metrics\/performances\/data\?.*$/
  }).as('performanceData');
  cy.intercept({
    method: 'GET',
    url: /\/centreon\/api\/latest\/monitoring\/resources.*$/
  }).as('resourceRequest');
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/latest/monitoring/resources/hosts?page=1&limit=10&sort_by=**'
  }).as('resourceRequestByHost');
  cy.intercept({
    method: 'POST',
    url: '/centreon/api/latest/monitoring/resources/downtime'
  }).as('setDowntime');
  cy.intercept({
    method: 'POST',
    url: '/centreon/api/latest/monitoring/resources/acknowledge'
  }).as('setAcknowledge');
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

Given('a dashboard that includes a configured resource table widget', () => {
  cy.insertDashboardWithWidget(
    dashboards.default,
    resourceTable,
    'centreon-widget-resourcestable',
    '/widgets/resourcestable'
  );
  cy.editDashboard(dashboards.default.name);
  cy.wait('@resourceRequest');
  cy.editWidget(1);
  cy.wait('@resourceRequest');
  cy.getByLabel({ label: 'RichTextEditor' })
    .eq(0)
    .type(genericTextWidgets.default.description, { force: true });
});

When(
  'the dashboard administrator user selects view by host as a display type',
  () => {
    cy.get('button[data-testid="View by host"]').eq(1).click();
    cy.wait('@resourceRequestByHost');
    cy.wait('@resourceRequest');
  }
);

Then('only the hosts must be displayed', () => {
  cy.waitUntil(
    () =>
      cy
        .get(
          `.MuiTable-root .MuiTableRow-root:nth-child(1) .MuiTableCell-root:nth-child(2)`
        )
        .should('be.visible')
        .invoke('text')
        .then((content) => {
          const columnContents: Array<string> =
            content.match(/[A-Z][a-z]*/g) || [];

          return columnContents.length >= 1 && columnContents.includes('Up');
        }),
    { interval: 2000, timeout: 10000 }
  );
});

When(
  'the dashboard administrator user selects view by service as a display type',
  () => {
    cy.get('button[data-testid="View by service"]').eq(1).realClick();

    cy.wait('@resourceRequest');
  }
);

Then('only the services must be displayed', () => {
  cy.waitUntil(
    () =>
      cy
        .get(
          `.MuiTable-root .MuiTableRow-root:nth-child(1) .MuiTableCell-root:nth-child(2)`
        )
        .should('be.visible')
        .invoke('text')
        .then((content) => {
          const columnContents: Array<string> =
            content.match(/[A-Z][a-z]*/g) || [];

          return (
            columnContents.length >= 3 &&
            columnContents.includes('Critical') &&
            columnContents.includes('Warning')
          );
        }),
    { interval: 2000, timeout: 10000 }
  );
});

Given('a dashboard containing a configured resource table widget', () => {
  cy.insertDashboardWithWidget(
    dashboards.default,
    resourceTable,
    'centreon-widget-resourcestable',
    '/widgets/resourcestable'
  );
  cy.editDashboard(dashboards.default.name);
  cy.editWidget(1);
  cy.wait('@resourceRequest');
});

When(
  'the dashboard administrator user selects a particular status in the displayed resource status list',
  () => {
    cy.get('input[name="unhandled_problems"]').click();
    cy.wait('@resourceRequest');
  }
);

Then(
  'only the resources with this particular status are displayed in the resource table Widget',
  () => {
    cy.waitUntil(
      () =>
        cy
          .get(
            `.MuiTable-root .MuiTableRow-root:nth-child(1) .MuiTableCell-root:nth-child(2)`
          )
          .should('be.visible')
          .invoke('text')
          .then((content) => {
            const columnContents: Array<string> =
              content.match(/[A-Z][a-z]*/g) || [];

            return (
              columnContents.length >= 3 &&
              columnContents.includes('Critical') &&
              columnContents.includes('Warning')
            );
          }),
      { interval: 2000, timeout: 10000 }
    );
  }
);

When(
  'the dashboard administrator user selects all the status and save changes',
  () => {
    cy.contains('Select all').click();
    cy.wait('@resourceRequest');
    cy.getByLabel({ label: 'RichTextEditor' })
      .eq(0)
      .type(genericTextWidgets.default.description, { force: true });
  }
);

Then(
  'all the resources having the status selected are displayed in the resource table Widget',
  () => {
    cy.getCellContent(1, 2).then((myTableContent) => {
      expect(myTableContent[1]).to.include('Critical');
      expect(myTableContent[2]).to.include('Warning');
      expect(myTableContent[3]).to.include('Unknown');
      expect(myTableContent[6]).to.include('Pending');
    });
  }
);

Then(
  'only the unhandled resources are displayed in the resource table widget',
  () => {
    cy.waitUntil(
      () =>
        cy
          .get(
            `.MuiTable-root .MuiTableRow-root:nth-child(1) .MuiTableCell-root:nth-child(2)`
          )
          .should('be.visible')
          .invoke('text')
          .then((content) => {
            const columnContents: Array<string> =
              content.match(/[A-Z][a-z]*/g) || [];

            return (
              columnContents.length >= 3 &&
              columnContents.includes('Critical') &&
              columnContents.includes('Warning')
            );
          }),
      { interval: 2000, timeout: 10000 }
    );
  }
);

Given('a dashboard featuring two resource table widgets', () => {
  cy.insertDashboardWithDoubleWidget(
    dashboards.default,
    resourceTable,
    resourceTable,
    'centreon-widget-resourcestable',
    '/widgets/resourcestable'
  );
  cy.editDashboard(dashboards.default.name);
  cy.wait('@resourceRequest');
  cy.getByTestId({ testId: 'MoreHorizIcon' }).click();
  cy.getByTestId({ testId: 'ContentCopyIcon' }).click();
});

When('the dashboard administrator user deletes one of the widgets', () => {
  cy.getByTestId({ testId: 'DeleteIcon' }).click();
  cy.getByTestId({ testId: 'confirm' }).click();
});

Then('only the contents of the other widget are displayed', () => {
  cy.waitUntil(
    () =>
      cy
        .get(
          `.MuiTable-root .MuiTableRow-root:nth-child(1) .MuiTableCell-root:nth-child(2)`
        )
        .should('exist')
        .invoke('text')
        .then((content) => {
          const columnContents: Array<string> =
            content.match(/[A-Z][a-z]*/g) || [];

          return (
            columnContents.length >= 1 && columnContents.includes('Critical')
          );
        }),
    { interval: 2000, timeout: 10000 }
  );
});

Given('a dashboard having a configured resource table widget', () => {
  cy.insertDashboardWithWidget(
    dashboards.default,
    resourceTable,
    'centreon-widget-resourcestable',
    '/widgets/resourcestable'
  );
  cy.editDashboard(dashboards.default.name);
  cy.wait('@resourceRequest');
});

When(
  'the dashboard administrator user duplicates the resource table widget',
  () => {
    cy.getByTestId({ testId: 'MoreHorizIcon' }).click();
    cy.getByTestId({ testId: 'ContentCopyIcon' }).click();
  }
);

Then(
  'a second resource table widget is displayed on the dashboard having the same properties as the first widget',
  () => {
    cy.waitUntil(
      () =>
        cy
          .get(
            `.MuiTable-root:eq(1) .MuiTableRow-root:nth-child(1) .MuiTableCell-root:nth-child(2)`
          )
          .should('exist')
          .invoke('text')
          .then((content) => {
            const columnContents: Array<string> =
              content.match(/[A-Z][a-z]*/g) || [];

            return (
              columnContents.length >= 3 &&
              columnContents.includes('Critical') &&
              columnContents.includes('Warning')
            );
          }),
      { interval: 2000, timeout: 10000 }
    );
  }
);

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
    cy.getByTestId({ testId: 'edit_dashboard' }).click();
    cy.getByTestId({ testId: 'AddIcon' }).click();
  }
);

When(
  'the dashboard administrator selects the widget type "resource table"',
  () => {
    cy.getByTestId({ testId: 'Widget type' }).click();
    cy.contains('Resource table').click();
  }
);

Then(
  'configuration properties for the resource table widget are displayed',
  () => {
    cy.contains('Widget properties').should('exist');
    cy.getByLabel({ label: 'Title' }).should('exist');

    cy.get('button[data-testid="View by host"]').should('exist');
    cy.get('button[data-testid="All"]').should('exist');

    cy.get('input[name="success"]').should('exist');
    cy.get('input[name="warning"]').should('exist');
    cy.get('input[name="problem"]').should('exist');
    cy.get('input[name="undefined"]').should('exist');
    cy.get('input[name="pending"]').should('exist');
    cy.get('input[name="unhandled_problems"]').should('exist');
    cy.get('input[name="acknowledged"]').should('exist');
    cy.get('input[name="in_downtime"]').should('exist');
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
    cy.wait('@resourceRequest');
  }
);

When('the user saves the resource table widget', () => {
  cy.getByTestId({ testId: 'confirm' }).click();
});

Then("the resource table widget is added to the dashboard's layout", () => {
  cy.wait('@resourceRequest');
  cy.waitUntil(
    () =>
      cy
        .get(
          `.MuiTable-root .MuiTableRow-root:nth-child(1) .MuiTableCell-root:nth-child(2)`
        )
        .should('be.visible')
        .invoke('text')
        .then((content) => {
          const columnContents: Array<string> =
            content.match(/[A-Z][a-z]*/g) || [];

          return (
            columnContents.length >= 3 &&
            columnContents.includes('Critical') &&
            columnContents.includes('Warning')
          );
        }),
    { interval: 2000, timeout: 10000 }
  );
});

Given('a dashboard with a resource table widget', () => {
  cy.insertDashboardWithWidget(
    dashboards.default,
    resourceTable,
    'centreon-widget-resourcestable',
    '/widgets/resourcestable'
  );
  cy.editDashboard(dashboards.default.name);
  cy.wait('@resourceRequest');
  cy.editWidget(1);
  cy.wait('@resourceRequest');
  cy.getByLabel({ label: 'RichTextEditor' })
    .eq(0)
    .type(genericTextWidgets.default.description, { force: true });
  cy.contains('host2').eq(0).should('be.visible');
});

When('the dashboard administrator clicks on a random resource', () => {
  cy.contains('host2').eq(0).click({ force: true });
});

Then(
  'the user should be redirected to the resource status screen and all the resources must be displayed',
  () => {
    cy.contains('host2').should('exist');
  }
);

Given('a dashboard containing a resource table widget', () => {
  cy.logoutViaAPI();
  cy.loginByTypeOfUser({
    jsonName: 'admin',
    loginViaApi: false
  });
  cy.insertDashboardWithWidget(
    dashboards.default,
    resourceTable,
    'centreon-widget-resourcestable',
    '/widgets/resourcestable'
  );
  cy.editDashboard(dashboards.default.name);
  cy.wait('@resourceRequest');
  cy.editWidget(1);
  cy.wait('@resourceRequest');
});

When(
  'the dashboard administrator clicks on a random resource from the resource table',
  () => {
    cy.get('[aria-label^="Select row"]').eq(0).click({ force: true });
  }
);

Then(
  'the dashboard administrator clicks on the downtime button and submits',
  () => {
    cy.getByLabel({ label: 'Set downtime' }).eq(1).click();
    cy.contains('Set downtime').realClick();
    cy.wait('@setDowntime');
  }
);

Then('the dashboard administrator clicks on the downtime filter', () => {
  cy.get('input[name="unhandled_problems"]').click();
  cy.get('input[name="in_downtime"]').click();
});

Then('the resources set to downtime should be displayed', () => {
  cy.waitUntil(
    () =>
      cy.get('body').then(($body) => {
        const element = $body.find('svg[data-icon="Downtime"]');

        return element.length > 0 && element.is(':visible');
      }),
    {
      errorMsg: 'The element is not visible',
      interval: 2000,
      timeout: 50000
    }
  ).then((isVisible) => {
    if (!isVisible) {
      throw new Error('The element is not visible');
    }
  });
});

Then(
  'the dashboard administrator clicks on the acknowledge button and submits',
  () => {
    cy.getByTestId({ testId: 'mainAcknowledge' }).eq(1).click({ force: true });
    cy.getByTestId({ testId: 'Confirm' }).eq(1).click();
    cy.wait('@setAcknowledge');
  }
);

Then('the dashboard administrator clicks on the acknowledge filter', () => {
  cy.get('input[name="undefined"]').click();
  cy.get('input[name="warning"]').click();
  cy.get('input[name="acknowledged"]').click();
});

Then('the resources set to acknowledge should be displayed', () => {
  cy.waitUntil(
    () =>
      cy.get('body').then(($body) => {
        const element = $body.find('[aria-label="service2 Acknowledged"]');

        return element.length > 0 && element.is(':visible');
      }),
    {
      errorMsg: 'The element with label "service3 Acknowledged" is not visible',
      interval: 2000,
      timeout: 50000
    }
  ).then((isVisible) => {
    if (!isVisible) {
      throw new Error(
        'The element with label "service3 Acknowledged" is not visible'
      );
    }
  });
});
