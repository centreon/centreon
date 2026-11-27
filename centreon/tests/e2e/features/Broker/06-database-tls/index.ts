import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { INTERCEPTORS } from 'fixtures/shared/constants/interceptors';

import {
  brokerConfigurationFiles,
  brokerEndpoints,
  brokerLogFiles,
  brokerStatsFiles,
  buildBrokerPidsCommand,
  buildExportCentralCommand,
  buildLineCountCommand,
  buildLinesAfterCommand,
  buildServiceCommand,
  buildSessionsConnectionTypeCommand,
  buildStageDatabaseTlsCommand,
  connectedState,
  databaseTlsCaPath,
  endpointStats,
  parseMysqlRows
} from '../common';

interface DatabaseOutput {
  db_ssl_ca?: string;
  db_ssl_enabled?: boolean;
  db_ssl_verify_cert?: boolean;
  name: string;
  type: string;
}

const databaseOutputTypes = ['sql', 'storage', 'unified_sql'];
const tlsConnectionType = 'SSL/TLS';
// Warning printed by CLAPI when it cannot chown the generated files
const ignoredExportMessage = 'Cannot set configuration file owner';
const pollingOptions = { interval: 5_000, timeout: 120_000 };

let exportOutput = '';
let brokerLogLineCount = 0;

beforeEach(() => {
  cy.startContainers();

  exportOutput = '';
  brokerLogLineCount = 0;

  cy.intercept({
    method: 'GET',
    url: INTERCEPTORS.api.navigation_list
  }).as('getNavigationList');
});

afterEach(() => {
  cy.stopContainers();
});

Given('an administrator is logged in', () => {
  cy.loginByTypeOfUser({ jsonName: 'admin', loginViaApi: false });
});

Given('TLS is enabled on the central database connection', () => {
  cy.requestOnDatabase({
    database: 'centreon',
    query: "SHOW GLOBAL VARIABLES LIKE 'have_ssl'"
  }).then(([rows]) => {
    expect(rows[0]?.Value, 'TLS offered by the database').to.eq('YES');
  });

  cy.execInContainer({
    command: buildStageDatabaseTlsCommand(databaseTlsCaPath),
    name: 'web'
  }).then(({ output }) => {
    expect(
      Number.parseInt(output.trim(), 10),
      'DATABASE_SSL_ENABLED staged'
    ).to.eq(1);
  });
});

When(
  'the administrator exports the configuration of the central and restarts Broker',
  () => {
    cy.execInContainer({
      command: buildExportCentralCommand(),
      name: 'web'
    }).then(({ output }) => {
      exportOutput = output;
    });

    cy.execInContainer({
      command: buildLineCountCommand(brokerLogFiles.broker),
      name: 'web'
    }).then(({ output }) => {
      brokerLogLineCount = Number.parseInt(output.trim(), 10) || 0;
    });
    cy.execInContainer({
      command: buildServiceCommand('restart', 'cbd'),
      name: 'web'
    });

    cy.waitUntil(
      () =>
        cy
          .readBrokerStats(brokerStatsFiles.broker)
          .then(
            (stats) =>
              endpointStats(stats, brokerEndpoints.unifiedSqlOutput)?.state ===
              connectedState
          ),
      { ...pollingOptions, errorMsg: 'The database output does not connect' }
    );
  }
);

Then('the export ends with no error', () => {
  const lines = exportOutput
    .split('\n')
    .filter(
      (line) => line.trim() !== '' && !line.includes(ignoredExportMessage)
    );

  expect(lines.join('\n'), 'export output').to.not.match(/error/i);
});

Then('the Broker configuration has the database TLS options enabled', () => {
  cy.execInContainer({
    command: `cat ${brokerConfigurationFiles.broker}`,
    name: 'web'
  }).then(({ output }) => {
    const outputs = (
      JSON.parse(output).centreonBroker.output as Array<DatabaseOutput>
    ).filter(({ type }) => databaseOutputTypes.includes(type));

    expect(outputs, 'database outputs').to.have.length.at.least(1);
    outputs.forEach(({ db_ssl_ca: ca, db_ssl_enabled: enabled, name }) => {
      expect(enabled, `db_ssl_enabled of ${name}`).to.eq(true);
      expect(ca, `db_ssl_ca of ${name}`).to.eq(databaseTlsCaPath);
    });
  });
});

Then('all Broker sessions on the database use TLS', () => {
  cy.execInContainer({ command: buildBrokerPidsCommand(), name: 'web' }).then(
    ({ output: pids }) => {
      expect(pids.trim(), 'cbd processes').to.match(/^\d+(,\d+)*$/);

      cy.execInContainer({
        command: buildSessionsConnectionTypeCommand(pids.trim()),
        name: 'db'
      }).then(({ output }) => {
        const sessions = parseMysqlRows(output);

        expect(
          sessions.length,
          'database sessions of Broker'
        ).to.be.greaterThan(0);
        sessions.forEach(([id, connectionType]) => {
          expect(connectionType, `connection type of session ${id}`).to.eq(
            tlsConnectionType
          );
        });
      });
    }
  );
});

Then('the Broker logs show no database connection error', () => {
  cy.execInContainer({
    command: buildLinesAfterCommand(brokerLogFiles.broker, brokerLogLineCount),
    name: 'web'
  }).then(({ output }) => {
    expect(
      output.trim(),
      'central-broker-master log since the restart'
    ).to.not.eq('');
    expect(output, 'database errors').to.not.match(
      /\[sql\] \[(error|critical)\]/
    );
  });

  cy.waitUntil(
    () =>
      cy
        .readBrokerStats(brokerStatsFiles.broker)
        .then(
          (stats) =>
            endpointStats(stats, brokerEndpoints.unifiedSqlOutput)?.state ===
            connectedState
        ),
    { ...pollingOptions, errorMsg: 'The database output is not connected' }
  );
});
