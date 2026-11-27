import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { INTERCEPTORS } from 'fixtures/shared/constants/interceptors';
import { PAGES } from 'fixtures/shared/constants/pages';

import { checkServicesAreMonitored } from '../../../commons';
import {
  brokerConfigurationFiles,
  brokerConfigurationNames,
  brokerEndpoints,
  brokerLogFiles,
  brokerStatsFiles,
  buildCountProcessCommand,
  buildLineCountCommand,
  buildLinesAfterCommand,
  buildServiceAlertsQuery,
  buildServiceCommand,
  buildServiceResourceQuery,
  buildSetBbdoLogLevelQuery,
  buildStateChanges,
  buildSubmitResultsCommand,
  connectedPeers,
  connectedState,
  endpointStats,
  listeningState,
  parseHandshakes,
  parseSubmittedTimes,
  passiveHost,
  passiveService
} from '../common';

const outputPrefix = 'rbt-restart';
const baselineOutput = `${outputPrefix}-baseline`;
const firstChanges = buildStateChanges(outputPrefix, 10, 1);
const moreChanges = buildStateChanges(outputPrefix, 10, 11);
// The Broker restarts while the second batch is being submitted
const restartDelaySeconds = 4;

const brokerProcessPattern = '(^|/)cbd .*central-(broker|rrd)\\.json';
const engineProcessPattern = '(^|/)centengine .*centengine\\.cfg';
const pollingOptions = { interval: 5_000, timeout: 180_000 };

let brokerLogLineCount = 0;

beforeEach(() => {
  cy.startContainers();

  brokerLogLineCount = 0;

  cy.intercept({
    method: 'GET',
    url: INTERCEPTORS.api.navigation_list
  }).as('getNavigationList');
});

afterEach(() => {
  cy.stopContainers();
});

const countProcesses = (pattern: string): Cypress.Chainable<number> =>
  cy
    .execInContainer(
      { command: buildCountProcessCommand(pattern), name: 'web' },
      { log: false }
    )
    .then(({ output }) =>
      cy.wrap(Number.parseInt(output.trim(), 10), { log: false })
    );

const generatedBbdoVersion = (file: string): Cypress.Chainable<string> =>
  cy
    .execInContainer({ command: `cat ${file}`, name: 'web' }, { log: false })
    .then(({ output }) =>
      cy.wrap(String(JSON.parse(output).centreonBroker.bbdo_version), {
        log: false
      })
    );

const serviceAlertCounts = (): Cypress.Chainable<[number, number]> =>
  cy
    .requestOnDatabase({
      database: 'centreon_storage',
      query: buildServiceAlertsQuery(passiveHost, passiveService, outputPrefix)
    })
    .then(([rows]) =>
      cy.wrap(
        [Number(rows[0]?.count ?? 0), Number(rows[0]?.distinct_count ?? 0)] as [
          number,
          number
        ],
        { log: false }
      )
    );

const checkStateChangesWritten = (count: number, lastOutput: string): void => {
  cy.waitUntil(
    () => serviceAlertCounts().then(([written]) => written >= count),
    {
      ...pollingOptions,
      errorMsg: `The ${count} state changes are not all in the logs table`
    }
  );
  serviceAlertCounts().then(([written, distinct]) => {
    expect(written, 'service alerts written').to.eq(count);
    expect(distinct, 'distinct outputs').to.eq(count);
  });

  cy.waitUntil(
    () =>
      cy
        .requestOnDatabase({
          database: 'centreon_storage',
          query: buildServiceResourceQuery(passiveHost, passiveService)
        })
        .then(([rows]) => rows.length === 1 && rows[0].output === lastOutput),
    {
      ...pollingOptions,
      errorMsg: `${passiveService} does not show ${lastOutput}`
    }
  );
};

Given(
  'a passive service is exported and Ok on a single-server platform',
  () => {
    // The bbdo logger of central-broker-master is at "error" in the installed
    // configuration: raise it to "info" to read the handshake once restarted
    cy.requestOnDatabase({
      database: 'centreon',
      query: buildSetBbdoLogLevelQuery(
        [brokerConfigurationNames.broker],
        'info'
      )
    });
    cy.addExportedPassiveService(passiveHost, passiveService);
    cy.submitResultsToEngine(passiveHost, passiveService, [
      { output: baselineOutput, status: 0 }
    ]);
    checkServicesAreMonitored([
      { name: passiveService, output: baselineOutput, status: 'ok' }
    ]);
  }
);

When('the central Broker is stopped', () => {
  cy.execInContainer({
    command: buildServiceCommand('stop', 'cbd'),
    name: 'web'
  });

  cy.waitUntil(
    () => countProcesses(brokerProcessPattern).then((count) => count === 0),
    {
      ...pollingOptions,
      errorMsg: 'The cbd processes are still running'
    }
  );
});

Then('the Engine keeps running and its Broker module holds the events', () => {
  countProcesses(engineProcessPattern).then((count) => {
    expect(count, 'centengine process').to.eq(1);
  });

  cy.waitUntil(
    () =>
      cy
        .readBrokerStats(brokerStatsFiles.module)
        .then(
          (stats) =>
            endpointStats(stats, brokerEndpoints.moduleOutput)?.state !==
            connectedState
        ),
    {
      ...pollingOptions,
      errorMsg: 'The Engine module output is still connected'
    }
  );
});

When('ten state changes are submitted and the Broker is started again', () => {
  cy.submitResultsToEngine(passiveHost, passiveService, firstChanges);

  // The module holds them while the Broker is down
  cy.readBrokerStats(brokerStatsFiles.module).then((stats) => {
    const output = endpointStats(stats, brokerEndpoints.moduleOutput);

    expect(output?.state, 'Engine module output').to.not.eq(connectedState);
    expect(
      Number(output?.queued_events ?? 0),
      'events held by the module'
    ).to.be.at.least(firstChanges.length);
  });

  cy.execInContainer({
    command: buildLineCountCommand(brokerLogFiles.broker),
    name: 'web'
  }).then(({ output }) => {
    brokerLogLineCount = Number.parseInt(output.trim(), 10) || 0;
  });
  cy.execInContainer({
    command: buildServiceCommand('start', 'cbd'),
    name: 'web'
  });
});

Then('the module reconnects with the same BBDO version', () => {
  cy.waitUntil(
    () =>
      cy
        .readBrokerStats(brokerStatsFiles.module)
        .then(
          (stats) =>
            endpointStats(stats, brokerEndpoints.moduleOutput)?.state ===
            connectedState
        ),
    {
      ...pollingOptions,
      errorMsg: 'The Engine module output does not reconnect'
    }
  );

  generatedBbdoVersion(brokerConfigurationFiles.module).then(
    (moduleVersion) => {
      generatedBbdoVersion(brokerConfigurationFiles.broker).then(
        (brokerVersion) => {
          expect(
            moduleVersion,
            'BBDO version of the module and of the Broker'
          ).to.eq(brokerVersion);

          // central-broker-master logs the version of the module it accepted
          cy.waitUntil(
            () =>
              cy
                .execInContainer(
                  {
                    command: buildLinesAfterCommand(
                      brokerLogFiles.broker,
                      brokerLogLineCount
                    ),
                    name: 'web'
                  },
                  { log: false }
                )
                .then(({ output }) => parseHandshakes(output).length > 0),
            {
              ...pollingOptions,
              errorMsg: 'No BBDO handshake logged since the start'
            }
          );
          cy.execInContainer({
            command: buildLinesAfterCommand(
              brokerLogFiles.broker,
              brokerLogLineCount
            ),
            name: 'web'
          }).then(({ output }) => {
            parseHandshakes(output).forEach(({ ours, peer }) => {
              expect(peer, 'version of the peer').to.eq(moduleVersion);
              expect(ours, 'version of the Broker').to.eq(brokerVersion);
            });
          });
        }
      );
    }
  );
});

Then('the ten state changes are written to the real-time database', () => {
  checkStateChangesWritten(
    firstChanges.length,
    firstChanges[firstChanges.length - 1].output
  );
});

When('ten more state changes are submitted while the Broker restarts', () => {
  cy.execInContainer({
    command: `(${buildSubmitResultsCommand(passiveHost, passiveService, moreChanges)}) & sleep ${restartDelaySeconds}; ${buildServiceCommand('restart', 'cbd')}; wait`,
    name: 'web'
  }).then(({ output }) => {
    expect(
      parseSubmittedTimes(output),
      'results written during the restart'
    ).to.have.length(moreChanges.length);
  });
});

Then('the twenty state changes are written once each', () => {
  checkStateChangesWritten(
    firstChanges.length + moreChanges.length,
    moreChanges[moreChanges.length - 1].output
  );
});

Then('the Broker statistics list the Engine module as a connected peer', () => {
  cy.waitUntil(
    () =>
      cy
        .readBrokerStats(brokerStatsFiles.broker)
        .then(
          (stats) =>
            connectedPeers(stats, brokerEndpoints.brokerInput).length === 1
        ),
    { ...pollingOptions, errorMsg: 'The Broker input has no connected peer' }
  );
  cy.readBrokerStats(brokerStatsFiles.module).then((stats) => {
    expect(endpointStats(stats, brokerEndpoints.moduleOutput)?.state).to.eq(
      connectedState
    );
  });

  cy.loginByTypeOfUser({ jsonName: 'admin', loginViaApi: false });
  cy.visit(PAGES.configuration.brokerStatisticsLegacy);
  cy.waitForElementInIframe('#main-content', '.broker_stats_cntr');
  cy.getIframeBody()
    .find(`#${brokerEndpoints.brokerInput}`)
    .find('.badge.service_ok')
    .should('have.text', listeningState);
});
