import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { INTERCEPTORS } from 'fixtures/shared/constants/interceptors';
import { PAGES } from 'fixtures/shared/constants/pages';

import {
  brokerConfigurationFiles,
  brokerConfigurationNames,
  brokerEndpoints,
  brokerLogFiles,
  brokerStatsFiles,
  buildBrokerBbdoVersionsQuery,
  buildCountProcessCommand,
  buildLineCountCommand,
  buildLinesAfterCommand,
  buildServiceCommand,
  buildSetBbdoLogLevelQuery,
  connectedPeers,
  connectedState,
  endpointStats,
  isOutputConnectedWithoutBacklog,
  listeningState,
  maxQueuedEvents,
  parseHandshakes
} from '../common';

interface GeneratedBrokerFile {
  centreonBroker: {
    bbdo_version: string;
    log: { loggers: Record<string, string> };
  };
}

const brokerReloadWaitingTime = 15_000;
const statisticsRefreshWaitingTime = 60_000;
const pollingOptions = { interval: 5_000, timeout: 120_000 };

let logLineCounts: Record<string, number> = {};
let bbdoVersions: Array<string> = [];
let lastUpdates: Array<string> = [];

beforeEach(() => {
  cy.startContainers();

  logLineCounts = {};
  bbdoVersions = [];
  lastUpdates = [];

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

const readGeneratedFile = (
  file: string
): Cypress.Chainable<GeneratedBrokerFile> =>
  cy
    .execInContainer({ command: `cat ${file}`, name: 'web' }, { log: false })
    .then(({ output }) =>
      cy.wrap(JSON.parse(output) as GeneratedBrokerFile, { log: false })
    );

const countLogLines = (file: string): void => {
  cy.execInContainer({
    command: buildLineCountCommand(file),
    name: 'web'
  }).then(({ output }) => {
    logLineCounts[file] = Number.parseInt(output.trim(), 10) || 0;
  });
};

const logWrittenSinceRestart = (file: string): Cypress.Chainable<string> =>
  cy
    .execInContainer(
      {
        command: buildLinesAfterCommand(file, logLineCounts[file] ?? 0),
        name: 'web'
      },
      { log: false }
    )
    .then(({ output }) => cy.wrap(String(output), { log: false }));

const openBrokerStatistics = (): void => {
  cy.visit(PAGES.configuration.brokerStatisticsLegacy);
  cy.waitForElementInIframe('#main-content', '.broker_stats_cntr');
};

// State and queued events of one endpoint block of the statistics page
const checkStatisticsBlock = (endpoint: string, state: string): void => {
  cy.getIframeBody()
    .find(`#${endpoint}`)
    .should('have.length', 1)
    .within(() => {
      cy.get('.badge.service_ok').should('have.text', state);
      cy.contains('.informations', 'Queued events')
        .find('.info_value')
        .invoke('text')
        .then((queued) => {
          expect(
            Number(queued.trim()),
            `queued events of ${endpoint}`
          ).to.be.lessThan(maxQueuedEvents);
        });
    });
};

const readLastUpdates = (): Cypress.Chainable<Array<string>> =>
  cy
    .getIframeBody()
    .find('.broker_stats_lastmodif .isTimestamp')
    .should('have.length.at.least', 2)
    .then((dates) =>
      cy.wrap(
        dates.toArray().map((date) => date.textContent?.trim() ?? ''),
        { log: false }
      )
    );

Given('an administrator is logged in a Centreon server', () => {
  cy.loginByTypeOfUser({ jsonName: 'admin', loginViaApi: false });
});

Given(
  'the central Engine and the two central Broker instances run the installed Broker configuration',
  () => {
    // The bbdo logger is at "error" in the installed configuration: raise it
    // to "info", the level of the handshake message, and restart the Brokers
    // so that both sides of each BBDO connection log their handshake
    cy.requestOnDatabase({
      database: 'centreon',
      query: buildSetBbdoLogLevelQuery(
        [brokerConfigurationNames.broker, brokerConfigurationNames.rrd],
        'info'
      )
    });
    cy.applyPollerConfiguration();
    cy.wait(brokerReloadWaitingTime);

    cy.waitUntil(
      () =>
        readGeneratedFile(brokerConfigurationFiles.broker).then(
          ({ centreonBroker }) => centreonBroker.log.loggers.bbdo === 'info'
        ),
      { ...pollingOptions, errorMsg: 'The bbdo logger is not exported at info' }
    );

    countLogLines(brokerLogFiles.broker);
    countLogLines(brokerLogFiles.rrd);
    cy.execInContainer({
      command: buildServiceCommand('restart', 'cbd'),
      name: 'web'
    });

    cy.waitUntil(
      () =>
        cy
          .execInContainer(
            {
              command: [
                buildCountProcessCommand('(^|/)cbd .*central-broker\\.json'),
                buildCountProcessCommand('(^|/)cbd .*central-rrd\\.json'),
                buildCountProcessCommand('(^|/)centengine .*centengine\\.cfg')
              ].join('; '),
              name: 'web'
            },
            { log: false }
          )
          .then(({ output }) => {
            const [broker, rrd, engine] = output
              .trim()
              .split('\n')
              .map((count) => Number.parseInt(count, 10));

            return broker === 1 && rrd === 1 && engine === 1;
          }),
      {
        ...pollingOptions,
        errorMsg:
          'The two cbd processes (central-broker.json, central-rrd.json) and centengine are not all running'
      }
    );
  }
);

When(
  'the user reads the BBDO version of the Broker configurations and of the generated Broker files',
  () => {
    cy.requestOnDatabase({
      database: 'centreon',
      query: buildBrokerBbdoVersionsQuery()
    }).then(([rows]) => {
      expect(rows, 'the three Broker configurations').to.have.length(3);

      rows.forEach(({ bbdo_version: version }) => {
        bbdoVersions.push(String(version));
      });
    });

    Object.values(brokerConfigurationFiles).forEach((file) => {
      readGeneratedFile(file).then(({ centreonBroker }) => {
        bbdoVersions.push(String(centreonBroker.bbdo_version));
      });
    });
  }
);

Then(
  'the module, the Broker and the RRD Broker use the same BBDO version',
  () => {
    cy.then(() => {
      expect(bbdoVersions, 'configurations and generated files').to.have.length(
        6
      );
      expect(bbdoVersions[0]).to.match(/^\d+\.\d+\.\d+$/);
      expect(new Set(bbdoVersions).size, `BBDO versions ${bbdoVersions}`).to.eq(
        1
      );
    });
  }
);

Then('the logs show the BBDO handshake with that version on both sides', () => {
  // central-broker-master accepts the Engine module and connects to
  // central-rrd-master: each side logs the version of its peer and its own
  cy.waitUntil(
    () =>
      logWrittenSinceRestart(brokerLogFiles.broker).then((brokerLog) =>
        logWrittenSinceRestart(brokerLogFiles.rrd).then(
          (rrdLog) =>
            parseHandshakes(brokerLog).length > 0 &&
            parseHandshakes(rrdLog).length > 0
        )
      ),
    {
      ...pollingOptions,
      errorMsg:
        'No BBDO handshake logged by central-broker-master and central-rrd-master'
    }
  );

  [brokerLogFiles.broker, brokerLogFiles.rrd].forEach((file) => {
    logWrittenSinceRestart(file).then((log) => {
      expect(log, `${file}`).to.not.contain('invalid protocol header');

      parseHandshakes(log).forEach(({ ours, peer }) => {
        expect(peer, `peer version in ${file}`).to.eq(bbdoVersions[0]);
        expect(ours, `own version in ${file}`).to.eq(bbdoVersions[0]);
      });
    });
  });
});

When('the user opens the Broker statistics of the Central', () => {
  openBrokerStatistics();
});

Then('the Broker input lists the Engine module as a connected peer', () => {
  cy.getIframeBody()
    .find(`#${brokerEndpoints.brokerInput}`)
    .find('.badge.service_ok')
    .should('have.text', listeningState);

  // The page does not list the peers of an input: its statistics file does,
  // one connected sub-entry per peer, the Engine module being the only one
  // on a single-server platform
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
    expect(
      endpointStats(stats, brokerEndpoints.moduleOutput)?.state,
      'Engine module output'
    ).to.eq(connectedState);
  });
});

Then('the database and RRD outputs are connected without backlog', () => {
  checkStatisticsBlock(brokerEndpoints.unifiedSqlOutput, connectedState);
  checkStatisticsBlock(brokerEndpoints.rrdForwardOutput, connectedState);

  cy.readBrokerStats(brokerStatsFiles.broker).then((stats) => {
    [
      brokerEndpoints.unifiedSqlOutput,
      brokerEndpoints.rrdForwardOutput
    ].forEach((endpoint) => {
      expect(
        isOutputConnectedWithoutBacklog(stats, endpoint),
        `${endpoint} connected without backlog`
      ).to.eq(true);
    });
  });
});

Then('the RRD Broker input and output are connected', () => {
  checkStatisticsBlock(brokerEndpoints.rrdInput, listeningState);
  checkStatisticsBlock(brokerEndpoints.rrdOutput, connectedState);

  cy.readBrokerStats(brokerStatsFiles.rrd).then((stats) => {
    expect(
      connectedPeers(stats, brokerEndpoints.rrdInput),
      'peers of the RRD Broker input'
    ).to.have.length(1);
    expect(endpointStats(stats, brokerEndpoints.rrdOutput)?.state).to.eq(
      connectedState
    );
  });
});

When('the user waits one minute and reloads the statistics', () => {
  readLastUpdates().then((dates) => {
    lastUpdates = dates;
  });

  cy.wait(statisticsRefreshWaitingTime);
  openBrokerStatistics();
});

Then('their last update has moved forward', () => {
  readLastUpdates().then((dates) => {
    expect(dates, 'one last update per Broker configuration').to.have.length(
      lastUpdates.length
    );

    dates.forEach((date, index) => {
      expect(date, 'last update').to.not.eq('');
      expect(date, 'last update after one minute').to.not.eq(
        lastUpdates[index]
      );
    });
  });

  checkStatisticsBlock(brokerEndpoints.brokerInput, listeningState);
  checkStatisticsBlock(brokerEndpoints.unifiedSqlOutput, connectedState);
  checkStatisticsBlock(brokerEndpoints.rrdForwardOutput, connectedState);
});
