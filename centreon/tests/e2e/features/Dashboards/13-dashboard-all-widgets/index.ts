/* eslint-disable newline-before-return */
/* eslint-disable cypress/unsafe-to-chain-command */
/* eslint-disable no-plusplus */
/* eslint-disable no-case-declarations */
import { Given, When, Then } from '@badeball/cypress-cucumber-preprocessor';

import {
  checkHostsAreMonitored,
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

  cy.logoutViaAPI();
  const apacheUser = Cypress.env('WEB_IMAGE_OS').includes('alma')
    ? 'apache'
    : 'www-data';
  cy.execInContainer({
    command: `su -s /bin/sh ${apacheUser} -c "/usr/bin/env php -q /usr/share/centreon/cron/centAcl.php"`,
    name: 'web'
  });
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
  cy.loginByTypeOfUser({
    jsonName: dashboardAdministratorUser.login,
    loginViaApi: false
  });
});

after(() => {
  cy.stopContainers();
});

Given('the dashboard administrator redirected to dashboard interface', () => {
  cy.insertDashboard(dashboards.fromDashboardCreatorUser);
  cy.visit('/centreon/home/dashboards');
  cy.wait('@listAllDashboards');
  cy.contains(dashboards.fromDashboardCreatorUser.name).click();
  cy.getByLabel({
    label: 'Edit dashboard',
    tag: 'button'
  }).click();
});

When('the dashboard administrator add generic text widget', () => {
  cy.getByTestId({ testId: 'AddIcon' }).click();
  cy.getByTestId({ testId: 'Widget type' }).click();
  cy.contains('Generic text').click();
  cy.getByLabel({ label: 'Title' }).type(genericTextWidgets.default.title);
  cy.getByLabel({ label: 'RichTextEditor' })
    .eq(0)
    .type(genericTextWidgets.default.description);
  cy.getByTestId({ testId: 'confirm' }).click();
  cy.get('.MuiAlert-message').should('not.exist');
});

When('the dashboard administrator add single metric widget', () => {
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

When('the dashboard administrator metrics graph widget', () => {
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

When('the dashboard administrator add top bottom widget', () => {
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

When('the dashboard administrator add Status grid widget', () => {
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
});

Then('the dashboard administrator save the dashboard', () => {
  cy.getByTestId({ testId: 'save_dashboard' }).click();
});

Given('the dashboard administrator redirected to dashboard screen', () => {
  cy.visit('/centreon/home/dashboards');
  cy.wait('@listAllDashboards');
  cy.contains(dashboards.fromDashboardCreatorUser.name).click();
});

When(
  'the dashboard administrator update widgets positions and save updates',
  () => {
    cy.getByTestId({ testId: 'edit_dashboard' }).click();
    cy.on('uncaught:exception', (err) => {
      if (err.message.includes('onDrag called before onDragStart')) {
        return false;
      }
      return true;
    });
    cy.get('.react-grid-item')
      .eq(3)
      .find('.react-resizable-handle-se')
      .trigger('mousedown', { button: 0 })
      .trigger('dragstart')
      .trigger('mousemove', { clientX: 486, force: true })
      .wait('@resourceRequest');

    cy.get('.react-grid-item').eq(3).realClick();

    cy.get('.react-grid-item')
      .eq(4)
      .find('.react-resizable-handle-se')
      .trigger('mousedown', { button: 0 })
      .trigger('dragstart')
      .trigger('mousemove', { clientX: 486, force: true });

    cy.get('.react-grid-item').eq(4).realClick();

    cy.get('.react-grid-item')
      .eq(4)
      .find('[data-testid*="_move_panel"]')
      .then((element) => {
        cy.wrap(element)
          .trigger('dragstart')
          .trigger('mousedown', { button: 0, force: true })
          .trigger('mousemove', { clientX: 836, clientY: 840, force: true });
      });
    cy.get('.react-grid-item').eq(4).realClick();

    cy.getByTestId({ testId: 'save_dashboard' }).click();
    cy.wait('@updateDashboard');
  }
);

Then('the new widget positions must be saved', () => {
  cy.get('.react-grid-item')
    .eq(3)
    .invoke('attr', 'style')
    .then((style) => {
      expect(style).to.include('width: calc(426px)');
    });
  cy.get('.react-grid-item')
    .eq(4)
    .invoke('attr', 'style')
    .then((style) => {
      expect(style).to.include('width: calc(426px)');
    });
});

Given('the dashboard administrator is now on the dashboard interface', () => {
  cy.visit('/centreon/home/dashboards');
  cy.wait('@listAllDashboards');
  cy.contains(dashboards.fromDashboardCreatorUser.name).click();
});

When(
  'the dashboard administrator clicks on view resource status button from {string} widget',
  (widgetType) => {
    let eqIndex;

    switch (widgetType) {
      case 'single metric':
        eqIndex = 0;
        break;

      case 'metrics graph':
        eqIndex = 1;
        break;

      case 'top buttom':
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
  'the dashboard administrator should be redirected to {string} widget resources',
  (widgetType) => {
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
        const metricsGraphStatuses = ['Critical', 'Warning'];

        for (let i = 0; i < metricsGraphStatuses.length; i++) {
          cy.get('[class$="chip-statusColumnChip"]')
            .eq(i)
            .should('contain.text', metricsGraphStatuses[i]);
        }
        break;

      case 'status grid':
        cy.url().should('include', '/centreon/monitoring/resources?filter=');
        const statusGridStatuses = ['Up', 'Up', 'Up'];
        for (let i = 0; i < statusGridStatuses.length; i++) {
          cy.get('[class$="chip-statusColumnChip"]')
            .eq(i)
            .should('contain.text', statusGridStatuses[i]);
        }
        break;
      case 'top buttom':
        cy.url().should('include', '/centreon/monitoring/resources?filter=');
        const topButtomStatuses = [
          'Critical',
          'Warning',
          'Unknown',
          'Unknown',
          'Unknown',
          'Pending',
          'Pending',
          'OK',
          'OK',
          'OK'
        ];
        for (let i = 0; i < topButtomStatuses.length; i++) {
          cy.get('[class$="chip-statusColumnChip"]')
            .eq(i)
            .should('contain.text', topButtomStatuses[i]);
        }
        break;
      default:
        break;
    }
  }
);
