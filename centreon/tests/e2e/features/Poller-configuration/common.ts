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

// Engine configuration of a poller: its id and the file it is exported to
const buildEngineConfigurationQuery = (pollerId: number): string =>
  `SELECT nagios_id, cfg_dir, cfg_file, service_check_timeout FROM cfg_nagios WHERE nagios_server_id = ${pollerId} ORDER BY nagios_id LIMIT 1`;

// Broker module (not the cbd daemon) of a poller and the file it is exported to
const buildBrokerModuleQuery = (pollerId: number): string =>
  `SELECT b.config_id, b.config_filename, s.centreonbroker_cfg_path AS cfg_path
    FROM cfg_centreonbroker b
    INNER JOIN nagios_server s ON s.id = b.ns_nagios_server
    WHERE b.ns_nagios_server = ${pollerId} AND b.daemon = 0
    ORDER BY b.config_id LIMIT 1`;

const buildBrokerLogLevelQuery = (configId: number, logName: string): string =>
  `SELECT lvl.name AS level
    FROM cfg_centreonbroker_log r
    INNER JOIN cb_log l ON l.id = r.id_log
    INNER JOIN cb_log_level lvl ON lvl.id = r.id_level
    WHERE r.id_centreonbroker = ${configId} AND l.name = '${logName}'`;

const buildInstanceProcessQuery = (pollerName: string): string =>
  `SELECT pid, running FROM instances WHERE name = '${pollerName}' AND deleted = 0`;

// Columns of the pollers list
const pidColumnIndex = 6;

// Logger level of a Broker JSON configuration file
const brokerLoggerLevel = (
  brokerFile: string,
  logger: string
): string | undefined => {
  const configuration = JSON.parse(brokerFile) as {
    centreonBroker: { log: { loggers: Record<string, string> } };
  };

  return configuration.centreonBroker.log.loggers[logger];
};

export {
  assertPollerIsRunning,
  breakSomePollers,
  brokerLoggerLevel,
  buildBrokerLogLevelQuery,
  buildBrokerModuleQuery,
  buildDeleteInstanceByNameQuery,
  buildDeletePollerByNameQuery,
  buildEngineConfigurationQuery,
  buildInsertPollerQuery,
  buildInsertRunningInstanceQuery,
  buildInstanceProcessQuery,
  checkIfConfigurationIsNotExported,
  checkIfMethodIsAppliedToPollers,
  clearCentengineLogs,
  duplicatedPollerInstanceId,
  duplicatedPollerName,
  getPoller,
  insertHost,
  insertPollerConfigUserAcl,
  legacyPollerName,
  legacyPollerUid,
  pidColumnIndex,
  pollerName,
  pollerUid,
  registerRunningInstance,
  remotePollerInstanceId,
  remotePollerName,
  removeFixtures,
  testHostName,
  waitPollerListToLoad
};
