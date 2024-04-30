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
const waitToExport = 5000;

const apiBase = '/centreon/api';
const apiActionV1 = `${apiBase}/index.php`;
const versionApi = 'latest';
const apiLogout = '/centreon/api/latest/authentication/logout';

let servicesFoundStepCount = 0;
let hostsFoundStepCount = 0;
let metricsFoundStepCount = 0;

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

interface MonitoredMetric {
  host: string;
  name: string;
  service: string;
}

const checkMetricsAreMonitored = (metrics: Array<MonitoredMetric>): void => {
  cy.log('Checking metrics in database');

  let query =
    'SELECT COUNT(m.metric_id) AS count_metrics FROM metrics m, index_data idata WHERE m.index_id = idata.id AND (';
  const conditions: Array<string> = [];
  metrics.forEach(({ host, name, service }) => {
    conditions.push(
      `(idata.host_name = '${host}' AND idata.service_description = '${service}' AND m.metric_name = '${name}')`
    );
  });
  query += conditions.join(' OR ');
  query += ')';
  cy.log(query);

  cy.requestOnDatabase({
    database: 'centreon_storage',
    query
  }).then(([rows]) => {
    metricsFoundStepCount += 1;

    const foundMetricCount = rows.length ? rows[0].count_metrics : 0;

    cy.log('Metric count in database', foundMetricCount);
    cy.log('Metric database check step count', metricsFoundStepCount);

    if (foundMetricCount >= metrics.length) {
      metricsFoundStepCount = 0;

      return null;
    }

    if (metricsFoundStepCount < maxSteps) {
      cy.wait(stepWaitingTime);

      return cy.wrap(null).then(() => checkMetricsAreMonitored(metrics));
    }

    throw new Error(
      `Metrics ${metrics
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

  cy.execInContainer({
    command: 'date -r /etc/centreon-engine/hosts.cfg',
    name: 'web'
  }).then(({ output }): Cypress.Chainable<null> | null => {
    cy.log(output);
    configurationExportedCheckStepCount += 1;

    const configurationExported = now < new Date(output).getTime();

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

    throw new Error(
      `Configuration not exported after ${pollingCheckTimeout}ms`
    );
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
    .execInContainer({
      command: `grep '${testHostName}' /etc/centreon-engine/hosts.cfg | tail -1`,
      name: 'web'
    })
    .then(({ output }): boolean => {
      if (output) {
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

  cy.execInContainer({
    command: 'date -r /etc/centreon-engine/hosts.cfg',
    name: 'web'
  }).then(({ output }): Cypress.Chainable<null> | null => {
    const configurationExported = now < new Date(output).getTime();

    if (configurationExported && checkExportedFileContent(hostName)) {
      return null;
    }

    throw new Error(`No configuration has been exported`);
  });
};

const getUserContactId = (userName: string): Cypress.Chainable => {
  return cy
    .requestOnDatabase({
      database: 'centreon',
      query: `SELECT contact_id FROM contact WHERE contact_alias = '${userName}'`
    })
    .then(([rows]) => {
      if (rows.length === 0) {
        throw new Error(`Contact id not found for contact alias ${userName}`);
      }

      cy.log(`Contact id found: ${rows[0].contact_id}`);

      return cy.wrap(rows[0].contact_id);
    });
};

const getAccessGroupId = (accessGroupName: string): Cypress.Chainable => {
  return cy
    .requestOnDatabase({
      database: 'centreon',
      query: `SELECT acl_group_id FROM acl_groups WHERE acl_group_name = '${accessGroupName}'`
    })
    .then(([rows]) => {
      if (rows.length === 0) {
        throw new Error(
          `Acl group id not found for acl group name ${accessGroupName}`
        );
      }

      cy.log(`Acl group id found: ${rows[0].acl_group_id}`);

      return cy.wrap(rows[0].acl_group_id);
    });
};

const configureProviderAcls = (): Cypress.Chainable => {
  return cy
    .fixture('resources/clapi/config-ACL/provider-acl.json')
    .then((fixture: Array<ActionClapi>) => {
      fixture.forEach((action) =>
        cy.executeActionViaClapi({ bodyContent: action })
      );
    });
};

const configureACLGroups = (path: string): Cypress.Chainable => {
  cy.getByLabel({ label: 'Roles mapping' }).click();

  cy.getByLabel({
    label: 'Enable automatic management',
    tag: 'input'
  })
    .eq(0)
    .check();

  cy.getByLabel({
    label: 'Roles attribute path',
    tag: 'input'
  }).type(`{selectAll}{backspace}${path}`);

  cy.getByLabel({
    label: 'Role value',
    tag: 'input'
  }).type('{selectAll}{backspace}centreon-editor');

  cy.getByLabel({
    label: 'ACL access group',
    tag: 'input'
  })
    .should('be.visible')
    .click();

  cy.wait('@getListAccessGroup');

  cy.get('div[role="presentation"] ul li').contains('ACL Group test').click();

  return cy
    .getByLabel({
      label: 'ACL access group',
      tag: 'input'
    })
    .should('have.value', 'ACL Group test');
};

export {
  ActionClapi,
  SubmitResult,
  checkThatConfigurationIsExported,
  checkHostsAreMonitored,
  checkMetricsAreMonitored,
  checkServicesAreMonitored,
  getStatusNumberFromString,
  getStatusTypeNumberFromString,
  submitResultsViaClapi,
  updateFixturesResult,
  apiBase,
  apiActionV1,
  applyConfigurationViaClapi,
  versionApi,
  insertFixture,
  logout,
  checkIfConfigurationIsExported,
  getUserContactId,
  getAccessGroupId,
  configureProviderAcls,
  configureACLGroups
};
