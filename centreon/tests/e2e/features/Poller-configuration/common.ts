import { insertFixture } from '../../commons';

const waitToExport = 10000;
const waitPollerListToLoad = 3000;
const testHostName = 'test_host';

const insertPollerConfigUserAcl = (): Cypress.Chainable => {
  return cy
    .setUserTokenApiV1()
    .executeCommandsViaClapi(
      'resources/clapi/config-ACL/poller-configuration-acl-user.json'
    );
};

const insertHost = (): Cypress.Chainable => {
  return insertFixture('resources/clapi/host1/01-add.json');
};

const getPoller = (pollerName: string): Cypress.Chainable => {
  const query = `SELECT id FROM nagios_server WHERE name = '${pollerName}'`;

  return cy
    .requestOnDatabase({
      database: 'centreon',
      query
    })
    .then(([rows]) => {
      if (rows.length) {
        return cy.wrap(Number.parseInt(rows[0].id, 10));
      }

      return cy.log('Cannot execute command on database.');
    });
};

const removeFixtures = (): Cypress.Chainable => {
  return cy.setUserTokenApiV1().then(() => {
    cy.executeActionViaClapi({
      bodyContent: {
        action: 'DEL',
        object: 'CONTACT',
        values: 'user1'
      }
    });
    cy.executeActionViaClapi({
      bodyContent: {
        action: 'DEL',
        object: 'HOST',
        values: 'test_host'
      }
    });
    cy.executeActionViaClapi({
      bodyContent: {
        action: 'DEL',
        object: 'ACLGROUP',
        values: 'ACL Group test'
      }
    });
    cy.executeActionViaClapi({
      bodyContent: {
        action: 'DEL',
        object: 'ACLMENU',
        values: 'acl_menu_test'
      }
    });
    cy.executeActionViaClapi({
      bodyContent: {
        action: 'DEL',
        object: 'ACLACTION',
        values: 'acl_action_test'
      }
    });
  });
};

const checkIfMethodIsAppliedToPollers = (method: string): void => {
  cy.log('Checking that if the method is applied to pollers');

  let logToSearch = '';
  switch (method) {
    case 'restarted':
      logToSearch = 'Centreon Engine [0-9]*.[0-9]*.[0-9]* starting ...';
      break;
    default:
      logToSearch = 'Reload configuration finished.';
      break;
  }

  cy.wait(waitToExport);

  cy.execInContainer({
    command: `grep '${logToSearch}' /var/log/centreon-engine/centengine.log | tail -1`,
    name: 'web'
  }).then(({ output }): Cypress.Chainable<null> | null => {
    if (output) {
      return null;
    }

    throw new Error('Method has not been applied to pollers');
  });
};

const clearCentengineLogs = (): Cypress.Chainable => {
  return cy
    .execInContainer({
      command: 'truncate -s 0 /var/log/centreon-engine/centengine.log',
      name: 'web'
    })
    .execInContainer({
      command: 'truncate -s 0 /etc/centreon-engine/hosts.cfg',
      name: 'web'
    });
};

const breakSomePollers = (): Cypress.Chainable => {
  return cy.execInContainer({
    command: 'chmod a-rwx /var/cache/centreon/config/engine/1/',
    name: 'web'
  });
};

const checkIfConfigurationIsNotExported = (): void => {
  cy.execInContainer({
    command: `grep '${testHostName}' /etc/centreon-engine/hosts.cfg | tail -1`,
    name: 'web'
  }).then(({ output }): Cypress.Chainable<null> | null => {
    if (!output) {
      return null;
    }

    throw new Error('The configuration has been exported');
  });
};

const pollerName = 'QA-poller';
const pollerUid = 900000001;
const legacyPollerName = 'QA-legacy-poller';
const legacyPollerUid = 900000002;
const remotePollerName = 'Poller-1';
const remotePollerInstanceId = 900000003;
const duplicatedPollerName = 'Poller-1_1';
const duplicatedPollerInstanceId = 900000004;

const buildInsertPollerQuery = (name: string, uid: number): string =>
  `INSERT INTO nagios_server (name, localhost, ns_activate, ssh_port, uid) VALUES ('${name}', '0', '1', 22, ${uid})`;

const buildInsertRunningInstanceQuery = (
  instanceId: number,
  name: string
): string =>
  `INSERT INTO instances (instance_id, name, running, last_alive, deleted) VALUES (${instanceId}, '${name}', 1, UNIX_TIMESTAMP(), 0)`;

// A poller missing from instances makes the web image restart gorgoned
const registerRunningInstance = (
  instanceId: number,
  name: string
): Cypress.Chainable => {
  return cy.requestOnDatabase({
    database: 'centreon_storage',
    query: buildInsertRunningInstanceQuery(instanceId, name)
  });
};

const buildDeleteInstanceByNameQuery = (name: string): string =>
  `DELETE FROM instances WHERE name = '${name}'`;

const buildDeletePollerByNameQuery = (name: string): string =>
  `DELETE FROM nagios_server WHERE name = '${name}'`;

const isRunningColumnIndex = 4;

const assertPollerIsRunning = (name: string): void => {
  cy.wait(waitPollerListToLoad);

  cy.getIframeBody()
    .contains('td', name)
    .parent('tr')
    .find('td')
    .eq(isRunningColumnIndex)
    .find('.service_ok')
    .should('exist');
};

// Engine of the Central started with its log file given as an argument: the
// extended configuration (-c) overrides log_file only
const engineConfigurationFile = '/etc/centreon-engine/centengine.cfg';
const engineDefaultLogFile = '/var/log/centreon-engine/centengine.log';
const engineArgumentLogFile =
  '/var/log/centreon-engine/rbt-log-argument/centengine.log';
const engineExtendedConfigurationFile =
  '/var/lib/centreon-engine/rbt-log-argument.json';

const buildEngineLogOptionsCommand = (): string =>
  `grep -E '^(log_v2_enabled|log_v2_logger|log_file|log_pid|log_file_line)=' ${engineConfigurationFile} || true`;

const parseEngineOptions = (output: string): Record<string, string> =>
  Object.fromEntries(
    output
      .split('\n')
      .map((line) => line.trim().match(/^([a-z_0-9]+)=(.*)$/))
      .filter((match): match is RegExpMatchArray => match !== null)
      .map(([, key, value]) => [key, value.trim()])
  );

const buildStartEngineWithLogFileCommand = (): string =>
  [
    `mkdir -p $(dirname ${engineArgumentLogFile})`,
    `chown centreon-engine: $(dirname ${engineArgumentLogFile})`,
    `printf '{"log_file": "%s"}\\n' '${engineArgumentLogFile}' > ${engineExtendedConfigurationFile}`,
    `chown centreon-engine: ${engineExtendedConfigurationFile}`,
    'systemctl stop centengine > /dev/null',
    'for i in $(seq 1 60); do pgrep -x centengine > /dev/null || break; sleep 1; done',
    '! pgrep -x centengine > /dev/null',
    `su centreon-engine -s /bin/bash -c 'setsid nohup /usr/sbin/centengine -c ${engineExtendedConfigurationFile} ${engineConfigurationFile} > /dev/null 2>&1 < /dev/null &'`
  ].join(' && ');

// "[time] [logger] [level] [file:line] [pid] message", the source position
// and the pid being written only when log_file_line and log_pid are set
const buildEngineLogLineRegExp = ({
  filePosition,
  pid
}: {
  filePosition: boolean;
  pid: number | null;
}): RegExp =>
  new RegExp(
    `^\\[[^\\]]+\\] \\[[a-z_]+\\] \\[[a-z]+\\]${
      filePosition ? ' \\[[^\\]\\s]+:\\d+\\]' : ''
    }${pid === null ? '' : ` \\[${pid}\\]`} \\S`
  );

export {
  assertPollerIsRunning,
  breakSomePollers,
  buildDeleteInstanceByNameQuery,
  buildDeletePollerByNameQuery,
  buildEngineLogLineRegExp,
  buildEngineLogOptionsCommand,
  buildInsertPollerQuery,
  buildInsertRunningInstanceQuery,
  buildStartEngineWithLogFileCommand,
  checkIfConfigurationIsNotExported,
  checkIfMethodIsAppliedToPollers,
  clearCentengineLogs,
  duplicatedPollerInstanceId,
  duplicatedPollerName,
  engineArgumentLogFile,
  engineDefaultLogFile,
  getPoller,
  insertHost,
  insertPollerConfigUserAcl,
  legacyPollerName,
  legacyPollerUid,
  parseEngineOptions,
  pollerName,
  pollerUid,
  registerRunningInstance,
  remotePollerInstanceId,
  remotePollerName,
  removeFixtures,
  testHostName,
  waitPollerListToLoad
};
