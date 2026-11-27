import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { INTERCEPTORS } from 'fixtures/shared/constants/interceptors';

import { checkServicesAreMonitored } from '../../../commons';
import {
  brokerEndpoints,
  brokerStatsFiles,
  buildCountProcessCommand,
  buildEngineAlertCountCommand,
  buildMetricQuery,
  buildMetricValuesQuery,
  buildMysqlCommand,
  buildQueueFileCountCommand,
  buildServiceAlertsQuery,
  buildServiceResourceQuery,
  buildStateChanges,
  endpointStats,
  isOutputConnectedWithoutBacklog,
  metricName,
  parseMysqlRows,
  passiveHost,
  passiveService,
  perfdataOf
} from '../common';

interface MonitoredResource {
  information: string;
  status: { name: string };
}

const outputPrefix = 'rbt-retention';
const baselineOutput = `${outputPrefix}-baseline`;
const stateChangeCount = 20;
const stateChanges = buildStateChanges(outputPrefix, stateChangeCount, 1, true);
const lastOutput = `${outputPrefix}-${stateChangeCount}`;

const databaseService = 'db';
const containerTaskTimeout = 180_000;
const pollingOptions = { interval: 5_000, timeout: 180_000 };
const recoveryTimeout = 300_000;

let metricId = 0;
let startTime = 0;

beforeEach(() => {
  cy.startContainers();

  metricId = 0;
  startTime = 0;

  cy.intercept({
    method: 'GET',
    url: INTERCEPTORS.api.navigation_list
  }).as('getNavigationList');
});

afterEach(() => {
  cy.stopContainers();
});

// Once stopped, the database may come back on another host port: query it
// from the web container, which reaches it by its service name
const storageRows = (query: string): Cypress.Chainable<Array<Array<string>>> =>
  cy
    .execInContainer(
      {
        command: `${buildMysqlCommand('centreon_storage', query)} || true`,
        name: 'web'
      },
      { log: false }
    )
    .then(({ output }) => cy.wrap(parseMysqlRows(output), { log: false }));

const isDatabaseReachable = (): Cypress.Chainable<boolean> =>
  cy
    .execInContainer(
      {
        command: `mysqladmin -h "\${MYSQL_HOST:-db}" -u centreon -pcentreon --connect-timeout=3 ping > /dev/null 2>&1 && echo reachable || echo unreachable`,
        name: 'web'
      },
      { log: false }
    )
    .then(({ output }) =>
      cy.wrap(output.trim() === 'reachable', { log: false })
    );

Given(
  'a passive service is exported and Ok on a single-server platform',
  () => {
    cy.addExportedPassiveService(passiveHost, passiveService);
    cy.submitResultsToEngine(passiveHost, passiveService, [
      { output: `${baselineOutput}|${perfdataOf(0)}`, status: 0 }
    ]);
    checkServicesAreMonitored([
      { name: passiveService, output: baselineOutput, status: 'ok' }
    ]);

    cy.waitUntil(
      () =>
        cy
          .requestOnDatabase({
            database: 'centreon_storage',
            query: buildMetricQuery(passiveHost, passiveService, metricName)
          })
          .then(([rows]) => {
            metricId = rows.length === 1 ? Number(rows[0].metric_id) : 0;

            return metricId > 0;
          }),
      {
        ...pollingOptions,
        errorMsg: `No metric ${metricName} for ${passiveService}`
      }
    );

    cy.execInContainer({ command: 'date +%s', name: 'web' }).then(
      ({ output }) => {
        startTime = Number.parseInt(output.trim(), 10);
      }
    );
  }
);

Given('the database of the central is stopped', () => {
  cy.task(
    'stopComposeService',
    { name: databaseService },
    { timeout: containerTaskTimeout }
  );

  cy.waitUntil(() => isDatabaseReachable().then((reachable) => !reachable), {
    ...pollingOptions,
    errorMsg: 'The database still answers'
  });
});

When(
  'twenty state changes with performance data are submitted for the service',
  () => {
    cy.submitResultsToEngine(passiveHost, passiveService, stateChanges);

    // The Engine processed them all: one service alert per state change
    cy.waitUntil(
      () =>
        cy
          .execInContainer(
            {
              command: buildEngineAlertCountCommand(
                passiveHost,
                passiveService,
                outputPrefix
              ),
              name: 'web'
            },
            { log: false }
          )
          .then(
            ({ output }) =>
              Number.parseInt(output.trim(), 10) >= stateChangeCount
          ),
      {
        ...pollingOptions,
        errorMsg: 'The Engine did not process the twenty results'
      }
    );
  }
);

Then(
  'the Broker keeps running and holds the events for its database output',
  () => {
    cy.execInContainer({
      command: buildCountProcessCommand('(^|/)cbd .*central-broker\\.json'),
      name: 'web'
    }).then(({ output }) => {
      expect(
        Number.parseInt(output.trim(), 10),
        'central-broker-master process'
      ).to.eq(1);
    });

    cy.waitUntil(
      () =>
        cy.readBrokerStats(brokerStatsFiles.broker).then((stats) => {
          const output = endpointStats(stats, brokerEndpoints.unifiedSqlOutput);

          return Number(output?.queued_events ?? 0) > 0;
        }),
      { ...pollingOptions, errorMsg: 'The database output holds no event' }
    );
  }
);

When('the database is started again', () => {
  cy.task(
    'startComposeService',
    { name: databaseService },
    { timeout: containerTaskTimeout }
  );

  cy.waitUntil(() => isDatabaseReachable(), {
    ...pollingOptions,
    errorMsg: 'The database does not answer after its start'
  });
});

Then('the database output reconnects and its backlog is emptied', () => {
  cy.waitUntil(
    () =>
      cy
        .readBrokerStats(brokerStatsFiles.broker)
        .then((stats) =>
          isOutputConnectedWithoutBacklog(
            stats,
            brokerEndpoints.unifiedSqlOutput
          )
        ),
    {
      errorMsg: 'The database output is not connected without backlog',
      interval: 5_000,
      timeout: recoveryTimeout
    }
  );
});

Then('the twenty state changes are in the event log table', () => {
  const query = buildServiceAlertsQuery(
    passiveHost,
    passiveService,
    outputPrefix
  );

  cy.waitUntil(
    () =>
      storageRows(query).then(
        (rows) => Number(rows[0]?.[0] ?? 0) >= stateChangeCount
      ),
    {
      ...pollingOptions,
      errorMsg: 'The state changes are not all in the logs table'
    }
  );
  storageRows(query).then(([[count, distinctCount]]) => {
    expect(Number(count), 'service alerts written').to.eq(stateChangeCount);
    expect(Number(distinctCount), 'distinct outputs').to.eq(stateChangeCount);
  });
});

Then('the twenty values are in the performance data table', () => {
  storageRows(buildMetricValuesQuery(metricId, startTime)).then((rows) => {
    expect(
      rows.map(([, value]) => Number(value)),
      `values of ${metricName} since the outage`
    ).to.deep.eq(stateChanges.map((_, index) => index + 1));
  });
});

Then('the service shows the last submitted result', () => {
  storageRows(buildServiceResourceQuery(passiveHost, passiveService)).then(
    (rows) => {
      expect(rows, `resource ${passiveService}`).to.have.length(1);
      expect(Number(rows[0][0]), 'status').to.eq(0);
      expect(rows[0][1], 'output').to.eq(lastOutput);
    }
  );

  cy.loginAsAdminViaApiV2();
  cy.request({
    method: 'GET',
    url: `${INTERCEPTORS.api.monitor_resources}?page=1&limit=30&search=${encodeURIComponent(
      JSON.stringify({
        $and: [
          { name: { $eq: passiveService } },
          { parent_name: { $eq: passiveHost } }
        ]
      })
    )}`
  }).then(({ body }) => {
    const resources = body.result as Array<MonitoredResource>;

    expect(resources, `${passiveService} in Resources Status`).to.have.length(
      1
    );
    expect(resources[0].status.name.toUpperCase()).to.eq('OK');
    expect(resources[0].information).to.eq(lastOutput);
  });
});

Then('no retention file is left in the Broker directory', () => {
  cy.waitUntil(
    () =>
      cy
        .execInContainer(
          { command: buildQueueFileCountCommand(), name: 'web' },
          { log: false }
        )
        .then(({ output }) => Number.parseInt(output.trim(), 10) === 0),
    {
      ...pollingOptions,
      errorMsg: 'Queue files are left in /var/lib/centreon-broker'
    }
  );
});
