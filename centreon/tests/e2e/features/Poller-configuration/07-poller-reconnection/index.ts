import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { INTERCEPTORS } from 'fixtures/shared/constants/interceptors';
import { PAGES } from 'fixtures/shared/constants/pages';

import { checkHostsAreMonitored } from '../../../commons';
import {
  assertPollerIsRunning,
  buildConfiguredPollerHostsQuery,
  buildDatabaseTimeQuery,
  buildRealtimePollerHostGroupsQuery,
  buildRealtimePollerHostsQuery,
  buildRealtimePollerResourcesQuery,
  buildRealtimePollerTagsQuery,
  buildRegisteredPollerQuery,
  buildRunningInstanceQuery,
  hostCategoryTagType,
  hostGroupTagType,
  sortedNames,
  sortedPairs
} from '../common';

interface NamedResource {
  name: string;
}

const hostPrefix = 'rbt-reco-host';
const hostNames = [`${hostPrefix}-1`, `${hostPrefix}-2`];
const hostGroup = 'rbt-reco-hg';
const hostCategory = 'rbt-reco-tag';
const pollingInterval = 5_000;
// The poller container registers itself once the central is up
const registrationTimeout = 600_000;
const reconnectionTimeout = 300_000;
const realtimeTimeout = 120_000;
const exportRetryInterval = 30_000;

let pollerName = '';
let instanceId = '';
let restartTime = 0;

beforeEach(() => {
  cy.startContainers({ profiles: ['poller'] });

  pollerName = '';
  instanceId = '';
  restartTime = 0;

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

const queryRows = (
  database: string,
  query: string
): Cypress.Chainable<Array<Record<string, unknown>>> =>
  cy
    .requestOnDatabase({ database, query })
    .then(([rows]) =>
      cy.wrap(rows as Array<Record<string, unknown>>, { log: false })
    );

// Waits for the running instance of the poller, started after "since"
const waitForRunningInstance = (since: number, timeout: number): void => {
  cy.waitUntil(
    () =>
      queryRows('centreon_storage', buildRunningInstanceQuery(pollerName)).then(
        (rows) => rows.length === 1 && Number(rows[0].start_time ?? 0) >= since
      ),
    {
      errorMsg: `Poller ${pollerName} is not running`,
      interval: pollingInterval,
      timeout
    }
  );

  queryRows('centreon_storage', buildRunningInstanceQuery(pollerName)).then(
    (rows) => {
      instanceId = String(rows[0].instance_id);
    }
  );
};

// Expected pairs of every host with one group or tag
const expectedPairs = (value: string): Array<string> =>
  hostNames.map((host) => `${host} / ${value}`).sort();

Given('an administrator is logged in', () => {
  cy.loginByTypeOfUser({ jsonName: 'admin', loginViaApi: true });
});

Given('a poller is monitored with hosts, host groups and tags', () => {
  cy.waitUntil(
    () =>
      queryRows('centreon', buildRegisteredPollerQuery()).then(
        (rows) => rows.length === 1
      ),
    {
      errorMsg: 'No poller is registered on the central',
      interval: pollingInterval,
      timeout: registrationTimeout
    }
  );
  queryRows('centreon', buildRegisteredPollerQuery()).then((rows) => {
    pollerName = String(rows[0].name);
  });

  clapi('ADD', 'HG', `${hostGroup};${hostGroup}`);
  clapi('ADD', 'HC', `${hostCategory};${hostCategory}`);
  cy.wrap(null).then(() => {
    hostNames.forEach((host) => {
      clapi(
        'ADD',
        'HOST',
        `${host};${host};127.0.0.1;generic-host;${pollerName};${hostGroup}`
      );
      clapi('SETPARAM', 'HOST', `${host};active_checks_enabled;0`);
      clapi('SETPARAM', 'HOST', `${host};passive_checks_enabled;1`);
      clapi('ADDMEMBER', 'HC', `${hostCategory};${host}`);
    });
  });
});

Given('the poller configuration is exported and monitoring is running', () => {
  // The engine of the poller starts with its first configuration, once
  // Gorgone reaches the poller: the export is sent again until it runs
  cy.wrap(null).then(() => {
    cy.waitUntil(
      () =>
        cy
          .executeActionViaClapi({
            bodyContent: { action: 'APPLYCFG', values: pollerName },
            failOnError: false
          })
          .then(() =>
            queryRows('centreon_storage', buildRunningInstanceQuery(pollerName))
          )
          .then((rows) => rows.length === 1),
      {
        errorMsg: `Poller ${pollerName} does not run its configuration`,
        interval: exportRetryInterval,
        timeout: registrationTimeout
      }
    );
  });
  cy.wrap(null).then(() => waitForRunningInstance(0, realtimeTimeout));
  checkHostsAreMonitored(hostNames.map((name) => ({ name })));

  // The tags of the hosts are written after the hosts
  cy.wrap(null).then(() => {
    cy.waitUntil(
      () =>
        queryRows(
          'centreon_storage',
          buildRealtimePollerTagsQuery(instanceId, hostCategoryTagType)
        ).then((rows) => rows.length === hostNames.length),
      {
        errorMsg: `The tags of ${pollerName} are not in the real-time database`,
        interval: pollingInterval,
        timeout: realtimeTimeout
      }
    );
  });
});

When('the poller is restarted and reconnects to the central', () => {
  queryRows('centreon_storage', buildDatabaseTimeQuery()).then((rows) => {
    restartTime = Number(rows[0].now);
  });

  cy.execInContainer({
    command: 'systemctl restart centengine || service centengine restart',
    name: 'poller'
  });

  cy.wrap(null).then(() =>
    waitForRunningInstance(restartTime, reconnectionTimeout)
  );
});

Then('the poller is shown as running', () => {
  cy.visit(PAGES.configuration.pollersLegacy);
  cy.waitForElementInIframe('#main-content', 'table.ListTable');
  cy.wrap(null).then(() => assertPollerIsRunning(pollerName));
});

Then('the hosts of the poller are listed in the real-time monitoring', () => {
  const search = JSON.stringify({
    $and: [{ name: { $rg: `^${hostPrefix}-` } }]
  });

  // Resources of the Resources Status page
  cy.request({
    method: 'GET',
    qs: { limit: 100, search, types: JSON.stringify(['host']) },
    url: INTERCEPTORS.api.monitor_resources
  }).then(({ body }) => {
    const names = (body.result as Array<NamedResource>)
      .map(({ name }) => name)
      .sort();

    expect(names).to.deep.eq([...hostNames].sort());
  });
});

Then(
  'the host groups of the poller are present in the real-time database',
  () => {
    queryRows(
      'centreon_storage',
      buildRealtimePollerHostGroupsQuery(instanceId)
    ).then((rows) => {
      expect(sortedPairs(rows, ['host', 'host_group'])).to.deep.eq(
        expectedPairs(hostGroup)
      );
    });

    queryRows(
      'centreon_storage',
      buildRealtimePollerTagsQuery(instanceId, hostGroupTagType)
    ).then((rows) => {
      expect(sortedPairs(rows, ['host', 'tag'])).to.deep.eq(
        expectedPairs(hostGroup)
      );
    });
  }
);

Then('the tags of the poller are present in the real-time database', () => {
  queryRows(
    'centreon_storage',
    buildRealtimePollerTagsQuery(instanceId, hostCategoryTagType)
  ).then((rows) => {
    expect(sortedPairs(rows, ['host', 'tag'])).to.deep.eq(
      expectedPairs(hostCategory)
    );
  });
});

Then('no entry of the poller is missing or left over', () => {
  queryRows('centreon', buildConfiguredPollerHostsQuery(pollerName)).then(
    (configuredRows) => {
      const configured = sortedNames(configuredRows);

      expect(configured).to.include.members(hostNames);

      queryRows(
        'centreon_storage',
        buildRealtimePollerHostsQuery(instanceId)
      ).then((rows) => {
        expect(sortedNames(rows), 'real-time hosts').to.deep.eq(configured);
      });

      queryRows(
        'centreon_storage',
        buildRealtimePollerResourcesQuery(instanceId)
      ).then((rows) => {
        expect(sortedNames(rows), 'real-time host resources').to.deep.eq(
          configured
        );
      });
    }
  );
});
