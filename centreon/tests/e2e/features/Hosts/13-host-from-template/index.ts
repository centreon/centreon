import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { INTERCEPTORS } from 'fixtures/shared/constants/interceptors';
import { PAGES } from 'fixtures/shared/constants/pages';

import {
  checkHostsAreMonitored,
  checkServicesAreMonitored
} from '../../../commons';
import {
  buildEnabledResourcesOfHostQuery,
  buildHostServicesFromTemplateQuery,
  buildRealTimeCheckIntervalQuery,
  generatedHostsFile,
  hostAttributeValue
} from '../common';

// Alias of the service template: the description of the services it deploys
const deployedServiceName = 'rbt-ping';
// APPLYCFG reloads Broker a few seconds after Engine: a result sent while
// its SQL output restarts never reaches the real-time tables
const brokerReloadWaitingTime = 15_000;
const realTimeTimeout = 120_000;
const pollingInterval = 5_000;

let hostTemplateName = '';
let serviceTemplateName = '';
let hostName = '';

beforeEach(() => {
  cy.startContainers();

  hostTemplateName = '';
  serviceTemplateName = '';
  hostName = '';

  cy.intercept({
    method: 'GET',
    url: INTERCEPTORS.api.navigation_list
  }).as('getNavigationList');
  cy.intercept({
    method: 'GET',
    url: INTERCEPTORS.pages.time_zone
  }).as('getTimeZone');
});

afterEach(() => {
  cy.stopContainers();
});

const clapi = (action: string, object: string, values: string): void => {
  cy.executeActionViaClapi({ bodyContent: { action, object, values } });
};

// Wait until the real-time table holds the expected check interval
const waitForRealTimeCheckInterval = (expected: number): void => {
  cy.waitUntil(
    () =>
      cy
        .requestOnDatabase({
          database: 'centreon_storage',
          query: buildRealTimeCheckIntervalQuery(hostName)
        })
        .then(
          ([rows]) =>
            rows.length === 1 && Number(rows[0].check_interval) === expected
        ),
    {
      errorMsg: `check interval of ${hostName} is not ${expected} in the real-time database`,
      interval: pollingInterval,
      timeout: realTimeTimeout
    }
  );
};

Given('an admin user is logged in a Centreon server', () => {
  cy.loginByTypeOfUser({ jsonName: 'admin', loginViaApi: false });
});

Given(
  'a host template {string} with a normal check interval of {int} and the linked service template {string}',
  (hostTemplate: string, checkInterval: number, serviceTemplate: string) => {
    hostTemplateName = hostTemplate;
    serviceTemplateName = serviceTemplate;

    // Passive objects: their status comes from the submitted results only
    clapi(
      'ADD',
      'STPL',
      `${serviceTemplate};${deployedServiceName};generic-service`
    );
    clapi(
      'SETPARAM',
      'STPL',
      `${serviceTemplate};check_command;check_centreon_dummy`
    );
    clapi('SETPARAM', 'STPL', `${serviceTemplate};active_checks_enabled;0`);
    clapi('SETPARAM', 'STPL', `${serviceTemplate};passive_checks_enabled;1`);
    clapi('SETPARAM', 'STPL', `${serviceTemplate};max_check_attempts;1`);

    clapi('ADD', 'HTPL', `${hostTemplate};${hostTemplate};;generic-host;;`);
    clapi(
      'SETPARAM',
      'HTPL',
      `${hostTemplate};check_interval;${checkInterval}`
    );
    clapi('SETPARAM', 'HTPL', `${hostTemplate};active_checks_enabled;0`);
    clapi('SETPARAM', 'HTPL', `${hostTemplate};passive_checks_enabled;1`);
    clapi('SETPARAM', 'HTPL', `${hostTemplate};max_check_attempts;1`);

    clapi('ADDHOSTTEMPLATE', 'STPL', `${serviceTemplate};${hostTemplate}`);
  }
);

When(
  'the user adds the host {string} with the template {string} and creates the services linked to the template',
  (host: string, hostTemplate: string) => {
    hostName = host;

    cy.visit(`${PAGES.configuration.hostsLegacy}&o=a`);
    cy.wait('@getTimeZone');
    cy.waitForElementInIframe('#main-content', 'input[name="host_name"]');

    cy.getIframeBody().find('input[name="host_name"]').type(host);
    cy.getIframeBody().find('input[name="host_alias"]').type(host);
    cy.getIframeBody().find('input[name="host_address"]').type('127.0.0.1');

    cy.getIframeBody().find('#template_add').click();
    cy.getIframeBody().find('span[id^="select2-tpSelect_"]').last().click();
    cy.getIframeBody().find(`div[title="${hostTemplate}"]`).click();

    // "Create Services linked to the Template too"
    cy.getIframeBody()
      .find('input[name="dupSvTplAssoc[dupSvTplAssoc]"][value="1"]')
      .check({ force: true });

    cy.getIframeBody()
      .find('input.btc.bt_success[name^="submit"]')
      .eq(0)
      .click();
    cy.wait('@getTimeZone');
  }
);

Then(
  'the host is listed with the service {string} deployed from the template',
  (service: string) => {
    expect(service).to.eq(deployedServiceName);

    cy.waitForElementInIframe('#main-content', 'input[name="searchH"]');
    cy.getIframeBody().contains('a', hostName).should('exist');

    cy.requestOnDatabase({
      database: 'centreon',
      query: buildHostServicesFromTemplateQuery(hostName, serviceTemplateName)
    }).then(([rows]) => {
      expect(
        rows.map(({ description }) => description),
        `services of ${hostName} deployed from ${serviceTemplateName}`
      ).to.deep.eq([service]);
    });
  }
);

When(
  'the user exports the configuration of the monitoring server with a reload',
  () => {
    cy.applyPollerConfiguration();
  }
);

Then(
  'the generated hosts.cfg declares {string} with {string}',
  (host: string, directive: string) => {
    const [attribute, ...value] = directive.split(' ');

    cy.execInContainer({
      command: `cat ${generatedHostsFile}`,
      name: 'web'
    }).then(({ output }) => {
      expect(
        hostAttributeValue(output, host, attribute),
        `${attribute} of ${host} in ${generatedHostsFile}`
      ).to.eq(value.join(' '));
    });
  }
);

Then(
  'the host is Up and its service {string} is Ok in Resources status',
  (service: string) => {
    checkHostsAreMonitored([{ name: hostName }]);
    checkServicesAreMonitored([{ name: service }]);
    cy.wait(brokerReloadWaitingTime);

    cy.submitResults([
      { host: hostName, output: 'rbt host up', status: 'up' },
      { host: hostName, output: 'rbt service ok', service, status: 'ok' }
    ]);

    checkHostsAreMonitored([
      { name: hostName, output: 'rbt host up', status: 'up' }
    ]);
    checkServicesAreMonitored([
      { name: service, output: 'rbt service ok', status: 'ok' }
    ]);
  }
);

Then(
  'the real-time database stores a check interval of {int} for the host',
  (checkInterval: number) => {
    waitForRealTimeCheckInterval(checkInterval);
  }
);

When(
  'the user sets the normal check interval of {string} to {int} and exports the configuration again',
  (hostTemplate: string, checkInterval: number) => {
    expect(hostTemplate).to.eq(hostTemplateName);

    clapi(
      'SETPARAM',
      'HTPL',
      `${hostTemplate};check_interval;${checkInterval}`
    );
    cy.applyPollerConfiguration();
  }
);

When(
  'the user deletes {string} and exports the configuration again',
  (host: string) => {
    clapi('DEL', 'HOST', host);
    cy.applyPollerConfiguration();
  }
);

Then('{string} is no longer found in Resources status', (host: string) => {
  cy.waitUntil(
    () =>
      cy
        .requestOnDatabase({
          database: 'centreon_storage',
          query: buildEnabledResourcesOfHostQuery(host)
        })
        .then(([rows]) => Number(rows[0].count) === 0),
    {
      errorMsg: `${host} or its services are still listed in Resources Status`,
      interval: pollingInterval,
      timeout: realTimeTimeout
    }
  );
});
