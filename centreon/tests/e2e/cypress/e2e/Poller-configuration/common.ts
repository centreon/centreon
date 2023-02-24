/* eslint-disable cypress/no-unnecessary-waiting */
import { executeActionViaClapi, insertFixture } from '../../commons';

const dateBeforeLogin = new Date();
const waitToExport = 10000;
const waitPollerListToLoad = 3000;
const testHostName = 'test_host';

const insertPollerConfigAclUser = (): Cypress.Chainable => {
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
  const command = `docker exec -i ${Cypress.env(
    'dockerName'
  )} mysql -ucentreon -pcentreon centreon -e "${query}"`;

  return cy
    .exec(command, { failOnNonZeroExit: true, log: true })
    .then(({ code, stdout, stderr }) => {
      if (!stderr && code === 0) {
        const pollerId = parseInt(stdout.split('\n')[1], 10);

        return cy.wrap(pollerId || '0');
      }

      return cy.log(`Can't execute command on database.`);
    });
};

const removeFixtures = (): Cypress.Chainable => {
  return cy.setUserTokenApiV1().then(() => {
    executeActionViaClapi({
      action: 'DEL',
      object: 'CONTACT',
      values: 'user1'
    });
    executeActionViaClapi({
      action: 'DEL',
      object: 'HOST',
      values: 'test_host'
    });
    executeActionViaClapi({
      action: 'DEL',
      object: 'ACLGROUP',
      values: 'ACL Group test'
    });
    executeActionViaClapi({
      action: 'DEL',
      object: 'ACLMENU',
      values: 'acl_menu_test'
    });
    executeActionViaClapi({
      action: 'DEL',
      object: 'ACLACTION',
      values: 'acl_action_test'
    });
  });
};

const checkExportedFileContent = (): Cypress.Chainable<boolean> => {
  return cy
    .exec(
      `docker exec -i ${Cypress.env(
        'dockerName'
      )} sh -c "grep '${testHostName}' /etc/centreon-engine/hosts.cfg | tail -1"`
    )
    .then(({ stdout }): boolean => {
      if (stdout) {
        return true;
      }

      return false;
    });
};

const checkIfConfigurationIsExported = (): void => {
  cy.log('Checking that configuration is exported');
  const now = dateBeforeLogin.getTime();

  cy.wait(waitToExport);

  cy.exec(
    `docker exec -i ${Cypress.env(
      'dockerName'
    )} date -r /etc/centreon-engine/hosts.cfg`
  ).then(({ stdout }): Cypress.Chainable<null> | null => {
    const configurationExported = now < new Date(stdout).getTime();

    if (configurationExported && checkExportedFileContent()) {
      return null;
    }

    throw new Error(`No configuration has been exported`);
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

  cy.exec(
    `docker exec -i ${Cypress.env(
      'dockerName'
    )} sh -c "grep '${logToSearch}' /var/log/centreon-engine/centengine.log | tail -1"`
  ).then(({ stdout }): Cypress.Chainable<null> | null => {
    if (stdout) {
      return null;
    }

    throw new Error(`Method has not been applied to pollers`);
  });
};

const clearCentengineLogs = (): Cypress.Chainable => {
  return cy
    .exec(
      `docker exec -i ${Cypress.env(
        'dockerName'
      )} truncate -s 0 /var/log/centreon-engine/centengine.log`
    )
    .exec(
      `docker exec -i ${Cypress.env(
        'dockerName'
      )} rm -rf /etc/centreon-engine/hosts.cfg`
    );
};

export {
  insertPollerConfigAclUser,
  getPoller,
  insertHost,
  removeFixtures,
  checkIfConfigurationIsExported,
  checkIfMethodIsAppliedToPollers,
  clearCentengineLogs,
  waitPollerListToLoad
};
