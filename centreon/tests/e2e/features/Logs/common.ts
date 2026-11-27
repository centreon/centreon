import { PAGES } from 'fixtures/shared/constants/pages';

const restrictedUserFixture = 'event-logs-restricted-user';

const openEventLogsPageAsRestrictedUser = (): void => {
  cy.logout();
  cy.loginByTypeOfUser({ jsonName: restrictedUserFixture, loginViaApi: false });
  cy.visit(PAGES.monitoring.eventLogsLegacy);
  cy.wait('@getTimeZone');
  cy.waitForElementInIframe('#main-content', 'select#host_filter');
};

const openHostFilterDropdown = (): void => {
  cy.getIframeBody()
    .find('select#host_filter')
    .siblings('span.select2-container')
    .click();
};

const runQueryButton = (): Cypress.Chainable<JQuery<HTMLElement>> =>
  cy.getIframeBody().find('#div1 input[name="graph"]');

// Waits for a search intercepted as "@eventLogsSearch" to end. cy.wait() returns
// when the response arrives, before the list is rendered and the controls are
// enabled again: "Run query" is enabled again only once the search ends.
// Yields the interception, as cy.wait() does.
const waitForSearch = (): Cypress.Chainable<
  import('cypress/types/net-stubbing').Interception
> =>
  cy.wait('@eventLogsSearch').then((interception) => {
    runQueryButton().should('not.be.disabled');
    cy.wrap(interception, { log: false });
  });

interface EventLogsRowsProps {
  count: number;
  // 0 is a service alert, 4 an engine message.
  msgType: 0 | 4;
  output: string;
}

// Inserts the rows straight in the logs table: generating that many events
// through the monitoring engine would be slow. The newest row is the first one.
const buildInsertEventLogsQuery = ({
  count,
  msgType,
  output
}: EventLogsRowsProps): string => {
  const rows = Array.from(
    { length: count },
    (_, index) =>
      `(UNIX_TIMESTAMP() - ${index + 1}, 999999, 'e2e-host', 'Central', ${msgType}, ` +
      `'${output} ${index + 1}', 1, 'e2e-service', 999999, 2, 1, '', '')`
  );

  return (
    'INSERT INTO logs (ctime, host_id, host_name, instance_name, msg_type, output, retry, ' +
    'service_description, service_id, status, type, notification_cmd, notification_contact) ' +
    `VALUES ${rows.join(', ')}`
  );
};

// Engine messages of a poller, as listed by the System Logs page
const buildEngineLogOutputs = (
  marker: string,
  pollerName: string,
  count: number
): Array<string> =>
  Array.from(
    { length: count },
    (_, index) => `${marker} ${pollerName} message ${index + 1}`
  );

// Engine messages (msg_type 4) have no host: they are linked to the poller by
// its instance name only
const buildInsertEngineLogsQuery = (
  instanceName: string,
  outputs: Array<string>
): string => {
  const rows = outputs.map(
    (output, index) =>
      `(UNIX_TIMESTAMP() - ${(index + 1) * 60}, '${instanceName}', 4, '${output}')`
  );

  return `INSERT INTO logs (ctime, instance_name, msg_type, output) VALUES ${rows.join(', ')}`;
};

const buildCentralPollerQuery = (): string =>
  `SELECT id, name FROM nagios_server WHERE localhost = '1' AND ns_activate = '1' ORDER BY id LIMIT 1`;

const buildInsertPollerQuery = (name: string): string =>
  `INSERT INTO nagios_server (name, localhost, ns_activate, ssh_port) VALUES ('${name}', '0', '1', 22)`;

const buildPollerIdQuery = (name: string): string =>
  `SELECT id FROM nagios_server WHERE name = '${name}'`;

// A poller missing from instances makes the web image restart Gorgone
const buildInsertRunningInstanceQuery = (
  instanceId: number,
  name: string
): string =>
  `INSERT INTO instances (instance_id, name, running, last_alive, deleted) VALUES (${instanceId}, '${name}', 1, UNIX_TIMESTAMP(), 0)`;

const buildInstanceIdQuery = (name: string): string =>
  `SELECT instance_id FROM instances WHERE name = '${name}' AND deleted = 0`;

const sortedCopy = (values: Array<string>): Array<string> => [...values].sort();

const centreonLogDirectory = '/var/log/centreon';

// Error files of the legacy logger and log directory of the new kernel,
// replaced by the channel files
const legacyLogArtifacts = [
  'sql-error.log',
  'centreon-error.log',
  'symfony.new'
];

// Names of the artifacts above with some content in the log directory
const buildWrittenLegacyArtifactsCommand = (): string =>
  `cd ${centreonLogDirectory} && for f in ${legacyLogArtifacts.join(' ')}; do if [ -d "$f" ] || [ -s "$f" ]; then echo "$f"; fi; done`;

const buildTruncateLogFilesCommand = (): string =>
  `find ${centreonLogDirectory} -maxdepth 1 -type f -exec truncate -s 0 {} +`;

// Content of the channel files of an environment, each one after a "==> path <==" header
const buildReadChannelFilesCommand = (environment: string): string =>
  `tail -v -n +1 ${centreonLogDirectory}/${environment}.*.log`;

// Files of the log directory (emptied by the scenario) holding one of the values
const buildFilesContainingValuesCommand = (values: Array<string>): string =>
  `grep -lF ${values.map((value) => `-e '${value}'`).join(' ')} ${centreonLogDirectory}/* 2>/dev/null || true`;

// "<environment>.<channel>.log", as written by the Monolog channels
const channelFileNamePattern = /^[a-z]+\.[a-z-]+\.log$/;

// [RFC3339 date] channel.LEVEL: message context extra
const logLinePattern =
  /^\[\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:[+-]\d{2}:\d{2}|Z)\] [\w.-]+\.(DEBUG|INFO|NOTICE|WARNING|ERROR|CRITICAL|ALERT|EMERGENCY): /;

// Correlation id added to every entry by Monolog's UidProcessor
const correlationIdPattern = /"uid":"[0-9a-f]+"/;

interface ChannelFileLines {
  file: string;
  lines: Array<string>;
}

// Splits the output of buildReadChannelFilesCommand() by file
const parseChannelFiles = (output: string): Array<ChannelFileLines> =>
  output.split('\n').reduce<Array<ChannelFileLines>>((files, line) => {
    const header = /^==> (.+) <==$/.exec(line);

    if (header) {
      return [...files, { file: header[1], lines: [] }];
    }

    if (line.trim() !== '' && files.length > 0) {
      files[files.length - 1].lines.push(line);
    }

    return files;
  }, []);

export {
  buildCentralPollerQuery,
  buildEngineLogOutputs,
  buildFilesContainingValuesCommand,
  buildInsertEngineLogsQuery,
  buildInsertEventLogsQuery,
  buildInsertPollerQuery,
  buildInsertRunningInstanceQuery,
  buildInstanceIdQuery,
  buildPollerIdQuery,
  buildReadChannelFilesCommand,
  buildTruncateLogFilesCommand,
  buildWrittenLegacyArtifactsCommand,
  centreonLogDirectory,
  channelFileNamePattern,
  correlationIdPattern,
  legacyLogArtifacts,
  logLinePattern,
  openEventLogsPageAsRestrictedUser,
  openHostFilterDropdown,
  parseChannelFiles,
  runQueryButton,
  sortedCopy,
  waitForSearch
};
