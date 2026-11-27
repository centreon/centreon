import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { INTERCEPTORS } from 'fixtures/shared/constants/interceptors';

import {
  checkHostsAreMonitored,
  checkServicesAreMonitored
} from '../../../commons';
import {
  buildHostAndServiceResourcesQuery,
  buildHostGroupTagsQuery
} from '../common';

interface ResourceRow {
  enabled: number;
  name: string;
  parent_name: string | null;
  type: number;
}

interface MonitoredResource {
  name: string;
  parent: { name: string } | null;
  status: { name: string };
  type: string;
}

const hostGroup = 'rbt-bbdo-hg';
const host = 'rbt-bbdo-cfg-host';
const service = 'rbt-bbdo-cfg-svc';

// APPLYCFG reloads Broker a few seconds after Engine: a result sent while
// its SQL output restarts never reaches the real-time tables
const brokerReloadWaitingTime = 15_000;
const pollingOptions = { interval: 5_000, timeout: 120_000 };
const resourcesStatusTimeout = 180_000;

beforeEach(() => {
  cy.startContainers();

  cy.intercept({
    method: 'GET',
    url: INTERCEPTORS.api.navigation_list
  }).as('getNavigationList');
});

afterEach(() => {
  cy.stopContainers();
});

const clapi = (action: string, object: string, values: string): void => {
  cy.executeActionViaClapi({ bodyContent: { action, object, values } });
};

const resourceRows = (): Cypress.Chainable<Array<ResourceRow>> =>
  cy
    .requestOnDatabase({
      database: 'centreon_storage',
      query: buildHostAndServiceResourcesQuery(host)
    })
    .then(([rows]) => cy.wrap(rows as Array<ResourceRow>, { log: false }));

const hostGroupTags = (): Cypress.Chainable<Array<string>> =>
  cy
    .requestOnDatabase({
      database: 'centreon_storage',
      query: buildHostGroupTagsQuery(host)
    })
    .then(([rows]) =>
      cy.wrap(
        (rows as Array<{ name: string }>).map(({ name }) => name),
        { log: false }
      )
    );

// The request Resources Status sends for the given filter
const findResources = (
  parameters: string
): Cypress.Chainable<Array<MonitoredResource>> =>
  cy
    .request({
      method: 'GET',
      url: `${INTERCEPTORS.api.monitor_resources}?page=1&limit=30&${parameters}`
    })
    .then(({ body, status }) => {
      expect(status, 'Resources Status listing').to.eq(200);
      expect(body.result, 'Resources Status result').to.be.an('array');

      return cy.wrap(body.result as Array<MonitoredResource>, { log: false });
    });

const byHostGroup = `hostgroup_names=${encodeURIComponent(JSON.stringify([hostGroup]))}`;

const byHostOrParentName = `search=${encodeURIComponent(
  JSON.stringify({
    $or: [{ name: { $eq: host } }, { parent_name: { $eq: host } }]
  })
)}`;

Given('an administrator is logged in a Centreon server', () => {
  cy.loginByTypeOfUser({ jsonName: 'admin', loginViaApi: false });
});

Given(
  'a host group and a host member of it with one service are created and exported',
  () => {
    cy.addHostGroup({ name: hostGroup });
    cy.addHost({
      activeCheckEnabled: false,
      address: '127.0.0.1',
      hostGroup,
      name: host,
      template: 'generic-host'
    });
    cy.addService({
      activeCheckEnabled: false,
      host,
      maxCheckAttempts: 1,
      name: service,
      template: 'Ping-LAN'
    });
    cy.applyPollerConfiguration();
  }
);

When('the Engine has reloaded the configuration', () => {
  checkHostsAreMonitored([{ name: host }]);
  checkServicesAreMonitored([{ name: service }]);
  cy.wait(brokerReloadWaitingTime);

  // Passive checks: give the host and its service a known state
  cy.submitResults([
    { host, output: 'rbt-bbdo-cfg up', status: 'up' },
    { host, output: 'rbt-bbdo-cfg ok', service, status: 'ok' }
  ]);
});

Then(
  'the host and its service are enabled resources of the real-time database',
  () => {
    resourceRows().then((rows) => {
      const enabled = rows.filter(({ enabled }) => Number(enabled) === 1);

      expect(
        enabled.map(({ name, type }) => `${type}:${name}`).sort(),
        'enabled resources of the host'
      ).to.deep.eq([`0:${service}`, `1:${host}`]);
    });
  }
);

Then('the host is linked to its host group in the real-time database', () => {
  cy.waitUntil(() => hostGroupTags().then((tags) => tags.length > 0), {
    ...pollingOptions,
    errorMsg: `${host} is not linked to any host group`
  });
  hostGroupTags().then((tags) => {
    expect(tags, `host groups of ${host}`).to.deep.eq([hostGroup]);
  });
});

Then(
  'filtering Resources Status on the host group lists the host Up and its service Ok',
  () => {
    cy.waitUntil(
      () =>
        findResources(byHostGroup).then((resources) => {
          const hostStatus = resources.find(
            ({ name, type }) => type === 'host' && name === host
          )?.status.name;
          const serviceStatus = resources.find(
            ({ name, parent, type }) =>
              type === 'service' && name === service && parent?.name === host
          )?.status.name;

          return (
            hostStatus?.toUpperCase() === 'UP' &&
            serviceStatus?.toUpperCase() === 'OK'
          );
        }),
      {
        errorMsg: `${host} Up and ${service} Ok are not listed for ${hostGroup}`,
        interval: 5_000,
        timeout: resourcesStatusTimeout
      }
    );

    // The search by name used once they are deleted finds them meanwhile
    findResources(byHostOrParentName).then((resources) => {
      expect(
        resources.map(({ name }) => name).sort(),
        `resources named ${host} or children of it`
      ).to.deep.eq([host, service].sort());
    });
  }
);

When(
  'the host is removed from the host group and the configuration is exported',
  () => {
    clapi('DELMEMBER', 'HG', `${hostGroup};${host}`);
    cy.applyPollerConfiguration();
  }
);

Then('the real-time database no longer links the host to the group', () => {
  cy.waitUntil(() => hostGroupTags().then((tags) => tags.length === 0), {
    ...pollingOptions,
    errorMsg: `${host} is still linked to ${hostGroup}`
  });

  // The host itself is still there: only its membership is gone
  resourceRows().then((rows) => {
    expect(
      rows.filter(
        ({ enabled, type }) => Number(type) === 1 && Number(enabled) === 1
      ),
      `enabled host ${host}`
    ).to.have.length(1);
  });
});

When('the host is deleted and the configuration is exported', () => {
  clapi('DEL', 'HOST', host);
  cy.applyPollerConfiguration();
});

Then('the host and its service are no longer enabled resources', () => {
  cy.waitUntil(
    () =>
      resourceRows().then(
        (rows) =>
          rows.filter(({ enabled }) => Number(enabled) === 1).length === 0
      ),
    { ...pollingOptions, errorMsg: `${host} or ${service} is still enabled` }
  );
});

Then('Resources Status no longer finds them', () => {
  findResources(byHostOrParentName).then((resources) => {
    expect(
      resources.map(({ name }) => name),
      `resources named ${host} or children of it`
    ).to.deep.eq([]);
  });
  findResources(byHostGroup).then((resources) => {
    expect(
      resources.map(({ name }) => name),
      `resources of ${hostGroup}`
    ).to.deep.eq([]);
  });
});
