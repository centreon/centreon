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

// First remote poller registered by the "poller" profile of the stack
const buildRegisteredPollerQuery = (): string =>
  `SELECT id, name FROM nagios_server WHERE localhost = '0' AND ns_activate = '1' ORDER BY id LIMIT 1`;

const buildRunningInstanceQuery = (name: string): string =>
  `SELECT instance_id, start_time, UNIX_TIMESTAMP() AS now FROM instances WHERE name = '${name}' AND running = 1 AND deleted = 0`;

const buildDatabaseTimeQuery = (): string => 'SELECT UNIX_TIMESTAMP() AS now';

// Hosts linked to the poller in the configuration
const buildConfiguredPollerHostsQuery = (pollerName: string): string =>
  `SELECT h.host_name AS name
    FROM host h
    INNER JOIN ns_host_relation nhr ON nhr.host_host_id = h.host_id
    INNER JOIN nagios_server ns ON ns.id = nhr.nagios_server_id
    WHERE ns.name = '${pollerName}' AND h.host_register = '1' AND h.host_activate = '1'`;

// Hosts of the poller in the real-time database
const buildRealtimePollerHostsQuery = (instanceId: string): string =>
  `SELECT name FROM hosts WHERE instance_id = ${instanceId} AND enabled = 1`;

const buildRealtimePollerResourcesQuery = (instanceId: string): string =>
  `SELECT name FROM resources WHERE poller_id = ${instanceId} AND type = 1 AND enabled = 1`;

const buildRealtimePollerHostGroupsQuery = (instanceId: string): string =>
  `SELECT h.name AS host, hg.name AS host_group
    FROM hosts h
    INNER JOIN hosts_hostgroups hhg ON hhg.host_id = h.host_id
    INNER JOIN hostgroups hg ON hg.hostgroup_id = hhg.hostgroup_id
    WHERE h.instance_id = ${instanceId} AND h.enabled = 1`;

// Tags of the hosts of the poller, as used by Resources Status:
// type 1 for a host group, type 3 for a host category
const buildRealtimePollerTagsQuery = (
  instanceId: string,
  tagType: number
): string =>
  `SELECT r.name AS host, t.name AS tag
    FROM resources r
    INNER JOIN resources_tags rt ON rt.resource_id = r.resource_id
    INNER JOIN tags t ON t.tag_id = rt.tag_id
    WHERE r.poller_id = ${instanceId} AND r.type = 1 AND r.enabled = 1 AND t.type = ${tagType}`;

const hostGroupTagType = 1;
const hostCategoryTagType = 3;

const sortedPairs = (
  rows: Array<Record<string, unknown>>,
  keys: [string, string]
): Array<string> =>
  rows.map((row) => `${row[keys[0]]} / ${row[keys[1]]}`).sort();

const sortedNames = (rows: Array<Record<string, unknown>>): Array<string> =>
  rows.map((row) => String(row.name)).sort();

export {
  assertPollerIsRunning,
  breakSomePollers,
  buildConfiguredPollerHostsQuery,
  buildDatabaseTimeQuery,
  buildDeleteInstanceByNameQuery,
  buildDeletePollerByNameQuery,
  buildInsertPollerQuery,
  buildInsertRunningInstanceQuery,
  buildRealtimePollerHostGroupsQuery,
  buildRealtimePollerHostsQuery,
  buildRealtimePollerResourcesQuery,
  buildRealtimePollerTagsQuery,
  buildRegisteredPollerQuery,
  buildRunningInstanceQuery,
  checkIfConfigurationIsNotExported,
  checkIfMethodIsAppliedToPollers,
  clearCentengineLogs,
  duplicatedPollerInstanceId,
  duplicatedPollerName,
  getPoller,
  hostCategoryTagType,
  hostGroupTagType,
  insertHost,
  insertPollerConfigUserAcl,
  legacyPollerName,
  legacyPollerUid,
  pollerName,
  pollerUid,
  registerRunningInstance,
  remotePollerInstanceId,
  remotePollerName,
  removeFixtures,
  sortedNames,
  sortedPairs,
  testHostName,
  waitPollerListToLoad
};
