/* eslint-disable cypress/no-unnecessary-waiting */
interface ActionClapi {
  action: string;
  object?: string;
  values: string;
}

let servicesFoundStepCount = 0;
let hostsFoundStepCount = 0;

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

const apiBase = '/centreon/api';
const apiActionV1 = `${apiBase}/index.php`;
const versionApi = 'latest';
const apiLogout = '/centreon/api/latest/authentication/logout';

const applyConfigurationViaClapi = (): Cypress.Chainable => {
  return cy.executeActionViaClapi({
    bodyContent: {
      action: 'APPLYCFG',
      values: '1'
    }
  });
};

const insertFixture = (file: string): Cypress.Chainable => {
  return cy
    .fixture(file)
    .then((fixture) => cy.executeActionViaClapi({ bodyContent: fixture }));
};

const logout = (): Cypress.Chainable => cy.visit(apiLogout);

interface MonitoredHost {
  name: string;
  output?: string;
  status?: string;
  statusType?: string;
}

const checkHostsAreMonitored = (hosts: Array<MonitoredHost>): void => {
  cy.log('Checking hosts in database');

  let query =
    'SELECT COUNT(h.host_id) AS count_hosts from hosts as h WHERE h.enabled=1 AND (';
  const conditions: Array<string> = [];
  hosts.forEach(({ name, output = '', status = '', statusType = '' }) => {
    let condition = `(h.name = '${name}'`;
    if (output !== '') {
      condition += ` AND h.output LIKE '%${output}%'`;
    }
    if (status !== '') {
      condition += ` AND h.state = ${getStatusNumberFromString(status)}`;
    }
    if (statusType !== '') {
      condition += ` AND h.state_type = ${getStatusTypeNumberFromString(
        statusType
      )}`;
    }
    condition += ')';
    conditions.push(condition);
  });
  query += conditions.join(' OR ');
  query += ')';
  cy.log(query);

  cy.requestOnDatabase({
    database: 'centreon_storage',
    query
  }).then(([rows]) => {
    hostsFoundStepCount += 1;

    const foundHostCount = rows.length ? rows[0].count_hosts : 0;

    cy.log('Host count in database', foundHostCount);
    cy.log('Host database check step count', hostsFoundStepCount);

    if (foundHostCount >= hosts.length) {
      hostsFoundStepCount = 0;

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
  acknowledged?: boolean | null;
  inDowntime?: boolean | null;
  name: string;
  output?: string;
  status?: string;
  statusType?: string;
}

const checkServicesAreMonitored = (services: Array<MonitoredService>): void => {
  cy.log('Checking services in database');

  let query =
    'SELECT COUNT(s.service_id) AS count_services from services as s WHERE s.enabled=1 AND (';
  const conditions: Array<string> = [];
  services.forEach(
    ({
      acknowledged = null,
      name,
      output = '',
      status = '',
      inDowntime = null,
      statusType = ''
    }) => {
      let condition = `(s.description = '${name}'`;
      if (output !== '') {
        condition += ` AND s.output LIKE '%${output}%'`;
      }
      if (status !== '') {
        condition += ` AND s.state = ${getStatusNumberFromString(status)}`;
      }
      if (acknowledged !== null) {
        condition += ` AND s.acknowledged = ${acknowledged === true ? 1 : 0}`;
      }
      if (inDowntime !== null) {
        condition += ` AND s.scheduled_downtime_depth = ${
          inDowntime === true ? 1 : 0
        }`;
      }
      if (statusType !== '') {
        condition += ` AND s.state_type = ${getStatusTypeNumberFromString(
          statusType
        )}`;
      }
      condition += ')';
      conditions.push(condition);
    }
  );
  query += conditions.join(' OR ');
  query += ')';
  cy.log(query);

  cy.requestOnDatabase({
    database: 'centreon_storage',
    query
  }).then(([rows]) => {
    servicesFoundStepCount += 1;

    const foundServiceCount = rows.length ? rows[0].count_services : 0;

    cy.log('Service count in database', foundServiceCount);
    cy.log('Service database check step count', servicesFoundStepCount);

    if (foundServiceCount >= services.length) {
      servicesFoundStepCount = 0;

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

const getStatusNumberFromString = (status: string): number => {
  const statuses = {
    critical: '2',
    down: '1',
    ok: '0',
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

const getStatusTypeNumberFromString = (statusType: string): number => {
  const statusesType = {
    hard: '1',
    soft: '0'
  };

  if (statusType in statusesType) {
    return statusesType[statusType];
  }

  throw new Error(`Status type ${statusType} does not exist`);
};

export {
  type ActionClapi,
  type SubmitResult,
  checkHostsAreMonitored,
  checkServicesAreMonitored,
  getStatusNumberFromString,
  getStatusTypeNumberFromString,
  apiBase,
  apiActionV1,
  applyConfigurationViaClapi,
  versionApi,
  insertFixture,
  logout
};
