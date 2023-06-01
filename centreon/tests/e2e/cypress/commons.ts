/* eslint-disable cypress/no-unnecessary-waiting */
interface ActionClapi {
  action: string;
  object?: string;
  values: string;
}

interface DateBeforeLoginProps {
  dateBeforeLogin: Date;
}

interface SubmitResult {
  host: string;
  output: string;
  service?: string;
  status: string;
  updatetime?: string;
}

const stepWaitingTime = 250;
const pollingCheckTimeout = 60000;
const maxSteps = pollingCheckTimeout / stepWaitingTime;
const waitToExport = 3000;

const apiBase = '/centreon/api';
const apiActionV1 = `${apiBase}/index.php`;
const versionApi = 'latest';
const apiLoginV2 = '/centreon/authentication/providers/configurations/local';
const apiLogout = '/centreon/api/latest/authentication/logout';

let servicesFoundStepCount = 0;
let hostsFoundStepCount = 0;

const getStatusNumberFromString = (status: string): number => {
  const statuses = {
    critical: '2',
    down: '1',
    unknown: '3',
    unreachable: '2',
    up: '0',
    warning: '1'
  };

  if (status in statuses) {
    return statuses[status];
  }

  throw new Error(`Status ${status} does not exist`);
};

interface MonitoredHost {
  name: string;
  output?: string;
  status?: string;
}

const checkHostsAreMonitored = (hosts: Array<MonitoredHost>): void => {
  cy.log('Checking hosts in database');

  let query = 'SELECT COUNT(h.host_id) from hosts as h WHERE h.enabled=1';
  const conditions: Array<string> = [];
  hosts.forEach(({ name, output = '', status = '' }) => {
    let condition = `(h.name = '${name}'`;
    if (output !== '') {
      condition += ` AND h.output LIKE '%${output}%'`;
    }
    if (status !== '') {
      condition += ` AND h.state = ${getStatusNumberFromString(status)}`;
    }
    condition += ')';
    conditions.push(condition);
  });
  query += conditions.join(' OR ');
  query += ')';
  cy.log(query);

  const command = `docker exec -i ${Cypress.env(
    'dockerName'
  )} mysql -ucentreon -pcentreon centreon_storage -e "${query}"`;

  cy.exec(command).then(({ stdout }): Cypress.Chainable<null> | null => {
    hostsFoundStepCount += 1;

    const output = stdout || '0';
    const foundHostCount = parseInt(output.split('\n')[1], 10);

    cy.log('Host count in database', foundHostCount);
    cy.log('Host database check step count', hostsFoundStepCount);

    if (foundHostCount >= hosts.length) {
      return null;
    }

    if (hostsFoundStepCount < maxSteps) {
      cy.wait(stepWaitingTime);

      return cy.wrap(null).then(() => checkHostsAreMonitored(hosts));
    }

    throw new Error(
      `Hosts ${hosts
        .map(({ name }) => name)
        .join()} are not monitored after ${pollingCheckTimeout}ms`
    );
  });
};

interface MonitoredService {
  name: string;
  output?: string;
  status?: string;
}

const checkServicesAreMonitored = (services: Array<MonitoredService>): void => {
  cy.log('Checking services in database');

  let query =
    'SELECT COUNT(s.service_id) from services as s WHERE s.enabled=1 AND (';
  const conditions: Array<string> = [];
  services.forEach(({ name, output = '', status = '' }) => {
    let condition = `(s.description = '${name}'`;
    if (output !== '') {
      condition += ` AND s.output LIKE '%${output}%'`;
    }
    if (status !== '') {
      condition += ` AND s.state = ${getStatusNumberFromString(status)}`;
    }
    condition += ')';
    conditions.push(condition);
  });
  query += conditions.join(' OR ');
  query += ')';
  cy.log(query);

  const command = `docker exec -i ${Cypress.env(
    'dockerName'
  )} mysql -ucentreon -pcentreon centreon_storage -e "${query}"`;

  cy.exec(command).then(({ stdout }): Cypress.Chainable<null> | null => {
    servicesFoundStepCount += 1;

    const output = stdout || '0';
    const foundServiceCount = parseInt(output.split('\n')[1], 10);

    cy.log('Service count in database', foundServiceCount);
    cy.log('Service database check step count', servicesFoundStepCount);

    if (foundServiceCount >= services.length) {
      return null;
    }

    if (servicesFoundStepCount < maxSteps) {
      cy.wait(stepWaitingTime);

      return cy.wrap(null).then(() => checkServicesAreMonitored(services));
    }

    throw new Error(
      `Services ${services
        .map(({ name }) => name)
        .join()} are not monitored after ${pollingCheckTimeout}ms`
    );
  });
};

let configurationExportedCheckStepCount = 0;

const checkThatConfigurationIsExported = ({
  dateBeforeLogin
}: DateBeforeLoginProps): void => {
  const now = dateBeforeLogin.getTime();

  cy.log('Checking that configuration is exported');

  cy.exec(
    `docker exec -i ${Cypress.env(
      'dockerName'
    )} date -r /etc/centreon-engine/hosts.cfg`
  ).then(({ stdout }): Cypress.Chainable<null> | null => {
    configurationExportedCheckStepCount += 1;

    const configurationExported = now < new Date(stdout).getTime();

    if (configurationExported) {
      return null;
    }

    if (configurationExportedCheckStepCount < maxSteps) {
      cy.wait(stepWaitingTime);

      return cy
        .wrap(null)
        .then(() => applyConfigurationViaClapi())
        .then(() => checkThatConfigurationIsExported({ dateBeforeLogin }));
    }

    throw new Error(`No configuration export after ${pollingCheckTimeout}ms`);
  });
};

const applyConfigurationViaClapi = (): Cypress.Chainable => {
  return cy.executeActionViaClapi({
    bodyContent: {
      action: 'APPLYCFG',
      values: '1'
    }
  });
};

const updateFixturesResult = (): Cypress.Chainable => {
  return cy
    .fixture('resources/clapi/submit-results.json')
    .then(({ results }) => {
      const timestampNow = Math.floor(Date.now() / 1000) - 15;

      const submitResults = results.map((submittedResult) => {
        return { ...submittedResult, updatetime: timestampNow.toString() };
      });

      return submitResults;
    });
};

const submitResultsViaClapi = (
  submitResult: Array<SubmitResult>
): Cypress.Chainable => {
  return cy.request({
    body: { results: submitResult },
    headers: {
      'Content-Type': 'application/json',
      'centreon-auth-token': window.localStorage.getItem('userTokenApiV1')
    },
    method: 'POST',
    url: `${apiActionV1}?action=submit&object=centreon_submit_results`
  });
};

const loginAsAdminViaApiV2 = (): Cypress.Chainable => {
  return cy.fixture('users/admin.json').then((userAdmin) => {
    return cy.request({
      body: {
        login: userAdmin.login,
        password: userAdmin.password
      },
      method: 'POST',
      url: apiLoginV2
    });
  });
};

const insertFixture = (file: string): Cypress.Chainable => {
  return cy
    .fixture(file)
    .then((fixture) => cy.executeActionViaClapi({ bodyContent: fixture }));
};

const logout = (): Cypress.Chainable => cy.visit(apiLogout);

const checkExportedFileContent = (
  testHostName: string
): Cypress.Chainable<boolean> => {
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

const checkIfConfigurationIsExported = ({
  dateBeforeLogin,
  hostName
}): void => {
  cy.log('Checking that configuration is exported');
  const now = dateBeforeLogin.getTime();

  cy.wait(waitToExport);

  cy.exec(
    `docker exec -i ${Cypress.env(
      'dockerName'
    )} date -r /etc/centreon-engine/hosts.cfg`
  ).then(({ stdout }): Cypress.Chainable<null> | null => {
    const configurationExported = now < new Date(stdout).getTime();

    if (configurationExported && checkExportedFileContent(hostName)) {
      return null;
    }

    throw new Error(`No configuration has been exported`);
  });
};

export {
  ActionClapi,
  SubmitResult,
  checkThatConfigurationIsExported,
  checkHostsAreMonitored,
  checkServicesAreMonitored,
  getStatusNumberFromString,
  submitResultsViaClapi,
  updateFixturesResult,
  apiBase,
  apiActionV1,
  applyConfigurationViaClapi,
  versionApi,
  loginAsAdminViaApiV2,
  insertFixture,
  logout,
  checkIfConfigurationIsExported
};
