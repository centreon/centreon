/* eslint-disable @typescript-eslint/no-unused-expressions */
/* eslint-disable no-loop-func */
/* eslint-disable newline-before-return */
/* eslint-disable cypress/unsafe-to-chain-command */
/* eslint-disable no-plusplus */
/* eslint-disable no-case-declarations */
import { Given, When, Then } from '@badeball/cypress-cucumber-preprocessor';

import {
  checkHostsAreMonitored,
  checkMetricsAreMonitored,
  checkServicesAreMonitored
} from '../../../commons';
import dashboardAdministratorUser from '../../../fixtures/users/user-dashboard-administrator.json';
import dashboards from '../../../fixtures/dashboards/creation/dashboards.json';
import genericTextWidgets from '../../../fixtures/dashboards/creation/widgets/genericText.json';

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
  cy.intercept({
    method: 'GET',
    url: /\/centreon\/api\/latest\/monitoring\/services\/names.*$/
  }).as('servicesNames');
  cy.intercept({
    method: 'GET',
    url: /\/centreon\/api\/latest\/monitoring\/dashboard\/metrics\/top\?.*$/
  }).as('dashboardMetricsTop');
  cy.intercept({
    method: 'PATCH',
    url: `/centreon/api/latest/configuration/dashboards/*`
  }).as('updateDashboard');
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
    url: /\/centreon\/api\/latest\/monitoring\/services\/names.*$/
  }).as('servicesNames');
  cy.loginByTypeOfUser({
    jsonName: dashboardAdministratorUser.login,
    loginViaApi: true
  });
});

after(() => {
  cy.stopContainers();
});

Given('a dashboard administrator on the dashboard web interface', () => {
  cy.insertDashboard(dashboards.fromDashboardCreatorUser);
  cy.editDashboard(dashboards.fromDashboardCreatorUser.name);
});

When('the dashboard administrator adds a Generic text widget', () => {
  cy.contains('Add a widget').click();
  cy.getByTestId({ testId: 'Widget type' }).click();
  cy.contains('Generic text').click();
  cy.getByLabel({ label: 'Title' }).type(genericTextWidgets.default.title);
  cy.getByLabel({ label: 'RichTextEditor' })
    .eq(0)
    .type(genericTextWidgets.default.description);
  cy.getByTestId({ testId: 'confirm' }).click();
  cy.get('.MuiAlert-message').should('not.exist');
});

When('the dashboard administrator adds a Single metric widget', () => {
  cy.getByLabel({ label: 'Add a widget' }).click();
  cy.getByTestId({ testId: 'Widget type' }).click();
  cy.contains('Single metric').click();
  cy.getByLabel({ label: 'Title' }).type(genericTextWidgets.default.title);
  cy.waitUntilPingExists();
  cy.getByTestId({ testId: 'Select metric' }).should('be.enabled').click();
  cy.contains('rta (ms)').realClick();
  cy.getByTestId({ testId: 'confirm' }).click();
  cy.get('.MuiAlert-message').should('not.exist');
});

When('the dashboard administrator adds a Metrics graph widget', () => {
  cy.getByLabel({ label: 'Add a widget' }).click();
  cy.getByTestId({ testId: 'Widget type' }).click();
  cy.contains('Metrics graph').click();
  cy.getByLabel({ label: 'Title' }).type(genericTextWidgets.default.title);
  cy.getByTestId({ testId: 'Resource type' }).realClick();
  cy.getByLabel({ label: 'Host Group' }).click();
  cy.getByTestId({ testId: 'Select resource' }).click();
  cy.contains('Linux-Servers').realClick();
  cy.getByTestId({ testId: 'Select metric' }).should('be.enabled').click();
  cy.getByTestId({ testId: 'rta' }).realClick();
  cy.wait('@performanceData');
  cy.getByTestId({ testId: 'confirm' }).click();
  cy.get('.MuiAlert-message').should('not.exist');
});

When('the dashboard administrator adds a Top bottom widget', () => {
  cy.getByLabel({ label: 'Add a widget' }).click();
  cy.getByTestId({ testId: 'Widget type' }).click();
  cy.contains('Top/bottom').click();
  cy.getByLabel({ label: 'Title' }).type(genericTextWidgets.default.title);
  cy.getByTestId({ testId: 'Resource type' }).realClick();
  cy.getByLabel({ label: 'Host Group' }).click();
  cy.getByTestId({ testId: 'Select resource' }).click();
  cy.contains(hostGroupName).realClick();
  cy.getByTestId({ testId: 'Select metric' }).click();
  cy.getByTestId({ testId: 'rta' }).realClick();
  cy.wait('@dashboardMetricsTop');
  cy.getByTestId({ testId: 'confirm' }).click();
  cy.get('.MuiAlert-message').should('not.exist');
});

When(
  'the dashboard administrator adds a Status grid widget and saves changes',
  () => {
    cy.getByLabel({ label: 'Add a widget' }).click();
    cy.getByTestId({ testId: 'Widget type' }).click();
    cy.contains('Status grid').click();
    cy.getByLabel({ label: 'Title' }).type(genericTextWidgets.default.title);
    cy.getByTestId({ testId: 'Resource type' }).realClick();
    cy.getByLabel({ label: 'Host Group' }).click();
    cy.getByTestId({ testId: 'Select resource' }).click();
    cy.contains('Linux-Servers').realClick();
    cy.get('input[name="success"]').click();
    cy.getByTestId({ testId: 'confirm' }).click();
    cy.getByTestId({ testId: 'save_dashboard' }).click();
  }
);

Then(
  'the dashboard administrator is now on the newly created dashboard in view mode',
  () => {
    cy.wait('@updateDashboard');
    cy.url().should('match', /\/centreon\/home\/dashboards\/library\/\d+/);
  }
);

Given(
  'a dashboard administrator who has just configured a multi-widget dashboard',
  () => {
    cy.visitDashboard(dashboards.fromDashboardCreatorUser.name);
  }
);

When(
  'the dashboard administrator updates the positions of the widgets and saves the dashboard',
  () => {
    cy.getByTestId({ testId: 'edit_dashboard' }).click();
    cy.on('uncaught:exception', (err) => {
      if (err.message.includes('onDrag called before onDragStart')) {
        return false;
      }
      return true;
    });
    cy.get('.react-grid-item')
      .eq(0)
      .find('.react-resizable-handle-se')
      .trigger('mousedown', { button: 0, force: true })
      .trigger('dragstart', { force: true })
      .trigger('mousemove', { clientX: 486, force: true });

    cy.get('.react-grid-item').eq(0).realClick();

    cy.get('.react-grid-item')
      .eq(1)
      .find('.react-resizable-handle-se')
      .trigger('mousedown', { button: 0, force: true })
      .trigger('dragstart', { force: true })
      .trigger('mousemove', { clientX: 486, force: true });

    cy.get('.react-grid-item').eq(1).realClick();

    cy.get('.react-grid-item')
      .eq(1)
      .find('[data-testid*="_move_panel"]')
      .then((element) => {
        cy.wrap(element)
          .trigger('dragstart', { force: true })
          .trigger('mousedown', { button: 0, force: true })
          .trigger('mousemove', { clientX: 836, clientY: 840, force: true });
      });
    cy.get('.react-grid-item').eq(1).realClick();

    cy.getByTestId({ testId: 'save_dashboard' }).click();
    cy.wait('@updateDashboard');
  }
);

Then('the dashboard is updated with the new widget layout', () => {
  cy.get('[class*="graphContainer"]').should('be.visible');
  cy.get('.react-grid-item')
    .eq(0)
    .invoke('attr', 'style')
    .then((style) => {
      expect(style).to.include('width: calc(425px)');
    });
  cy.get('.react-grid-item')
    .eq(1)
    .invoke('attr', 'style')
    .then((style) => {
      expect(style).to.include('width: calc(425px)');
    });
});

Given(
  'the dashboard administrator with a configured multi-widget dashboard',
  () => {
    cy.visitDashboard(dashboards.fromDashboardCreatorUser.name);
  }
);

When(
  'the dashboard administrator clicks on the "view Resource Status" button from the {string} widget',
  (widgetType) => {
    let eqIndex;

    switch (widgetType) {
      case 'single metric':
        eqIndex = 0;
        break;

      case 'metrics graph':
        eqIndex = 1;
        break;

      case 'top bottom':
        eqIndex = 2;
        break;

      case 'status grid':
        eqIndex = 3;
        break;

      default:
        break;
    }

    if (eqIndex !== undefined) {
      cy.getByTestId({ testId: 'edit_dashboard' }).click();
      cy.get('a[data-testid="See more on the Resources Status page"]')
        .eq(eqIndex)
        .invoke('attr', 'href')
        .then((href) => {
          if (href) {
            cy.visit(href);
          } else {
            cy.log('Href is null or undefined');
          }
        });
      cy.wait('@resourceRequest');
    }
  }
);

Then(
  'the dashboard administrator should be redirected to the {string} widget resources',
  (widgetType) => {
    let statusFound = false;
    switch (widgetType) {
      case 'single metric':
        cy.url().should('include', '/centreon/monitoring/resources?details=');
        cy.get('[class$="-resourceNameText-text-rowNotHovered"]')
          .eq(0)
          .should('contain.text', 'Ping');
        cy.get('[class$="-resourceNameText-text-rowNotHovered"]')
          .eq(1)
          .should('contain.text', 'Centreon-Server');
        break;

      case 'metrics graph':
        cy.url().should('include', '/centreon/monitoring/resources?filter=');
        const metricsGraphStatuses = ['Critical'];

        for (let i = 0; i < metricsGraphStatuses.length; i++) {
          cy.get('[class$="chip-statusColumnChip"]')
            .eq(i)
            .should('contain.text', metricsGraphStatuses[i]);
        }
        break;

      case 'status grid':
        cy.url().should('include', '/centreon/monitoring/resources?filter=');
        const statusGridStatuses = [
          'Critical',
          'Unknown',
          'Unknown',
          'Ok',
          'Up'
        ];
        cy.get('[class$="chip-statusColumnChip"]')
          .each(($chip) => {
            if (statusGridStatuses.includes($chip.text()) && !statusFound) {
              statusFound = true;
              return false;
            }
            return undefined;
          })
          .then(() => {
            expect(statusFound).to.be.true;
          });
        break;
      case 'top buttom':
        cy.url().should('include', '/centreon/monitoring/resources?filter=');
        const topButtomStatuses = [
          'Critical',
          'Unknown',
          'Unknown',
          'Unknown',
          'Unknown',
          'Pending',
          'Pending',
          'OK',
          'OK',
          'OK'
        ];
        cy.get('[class$="chip-statusColumnChip"]')
          .each(($chip) => {
            if (topButtomStatuses.includes($chip.text()) && !statusFound) {
              statusFound = true;
              return false;
            }
            return undefined;
          })
          .then(() => {
            expect(statusFound).to.be.true;
          });
        break;
      default:
        break;
    }
  }
);
