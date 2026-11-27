import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';

import { apiActionV1, checkServicesAreMonitored } from '../../../commons';
import {
  apiRightParameters,
  buildHostQuery,
  commonPrefix,
  contactPassword,
  curveHasNumericValue,
  entryHasNumericValue,
  type MetricEntry,
  nowInSeconds,
  parseApiBody,
  type RealtimeHost,
  type RealtimeService
} from '../common';

interface Credentials {
  login: string;
  password: string;
}

const pollingInterval = 10_000;
const realtimeTimeout = 120_000;
// The scenario needs a few RRD points: the service is checked every minute
const metricTimeout = 300_000;
const oneHourInSeconds = 3600;
// APPLYCFG reloads Broker a few seconds after Engine: a result sent while
// its SQL output restarts never reaches the real-time tables
const brokerReloadWaitingTime = 15_000;

let admin: Credentials = { login: '', password: '' };
let adminToken = '';
let lastResponse: Cypress.Response<unknown> | null = null;
let lastToken = '';
let currentHost = '';
let currentService = '';
let submittedPerfdata = '';
let metricServiceIds = '';
let monitoredServices: Record<string, string> = {};

beforeEach(() => {
  cy.startContainers();

  admin = { login: '', password: '' };
  adminToken = '';
  lastResponse = null;
  lastToken = '';
  currentHost = '';
  currentService = '';
  submittedPerfdata = '';
  metricServiceIds = '';
  monitoredServices = {};

  cy.fixture('users/admin.json').then((credentials: Credentials) => {
    admin = credentials;
  });
});

afterEach(() => {
  cy.stopContainers();
});

const responseBody = (): unknown => parseApiBody(lastResponse?.body);

const storeResponse = (response: Cypress.Response<unknown>): void => {
  lastResponse = response;
};

// API v1 only reads the form fields: a missing password must not be sent at all.
// "log: false" keeps the credentials out of the Cypress command log.
const authenticate = (
  login: string,
  password?: string
): Cypress.Chainable<Cypress.Response<unknown>> => {
  const body: Record<string, string> = { username: login };

  if (password !== undefined) {
    body.password = password;
  }

  cy.clearCookies();

  return cy
    .request({
      body,
      failOnStatusCode: false,
      form: true,
      log: false,
      method: 'POST',
      url: `${apiActionV1}?action=authenticate`
    })
    .then((response) => {
      storeResponse(response);

      return cy.wrap(response, { log: false });
    });
};

// The token, when given, must be the only credential of the call
const requestLegacyApi = ({
  body,
  method = 'GET',
  query,
  token
}: {
  body?: object;
  method?: 'GET' | 'POST';
  query: string;
  token?: string;
}): Cypress.Chainable<Cypress.Response<unknown>> => {
  const headers: Record<string, string> = {
    'Content-Type': 'application/json'
  };

  if (token !== undefined) {
    headers['centreon-auth-token'] = token;
  }

  cy.clearCookies();

  return cy
    .request({
      body,
      failOnStatusCode: false,
      headers,
      log: false,
      method,
      url: `${apiActionV1}?${query}`
    })
    .then((response) => {
      storeResponse(response);

      return cy.wrap(response, { log: false });
    });
};

const clapi = (action: string, object: string, values: string): void => {
  requestLegacyApi({
    body: { action, object, values },
    method: 'POST',
    query: 'action=action&object=centreon_clapi',
    token: adminToken
  }).then((response) => {
    expect(response.status, `CLAPI ${action} ${object}`).to.eq(200);
  });
};

const applyConfiguration = (): void => {
  requestLegacyApi({
    body: { action: 'APPLYCFG', values: '1' },
    method: 'POST',
    query: 'action=action&object=centreon_clapi',
    token: adminToken
  }).then((response) => {
    expect(response.status, 'APPLYCFG').to.eq(200);
  });
};

const createHostWithService = ({
  host,
  service,
  template
}: {
  host: string;
  service: string;
  template: string;
}): void => {
  clapi('ADD', 'HOST', buildHostQuery(host, 'generic-host'));
  clapi('ADD', 'SERVICE', `${host};${service};${template}`);
  clapi('SETPARAM', 'SERVICE', `${host};${service};max_check_attempts;1`);
  monitoredServices[host] = service;
};

const listRealtimeServices = (
  query: string,
  token: string
): Cypress.Chainable<Array<RealtimeService>> =>
  requestLegacyApi({
    query: `object=centreon_realtime_services&action=list&${query}`,
    token
  }).then((response) => {
    expect(response.status, 'real-time services listing').to.eq(200);

    return cy.wrap(response.body as Array<RealtimeService>, { log: false });
  });

const listRealtimeHosts = (
  search: string,
  token: string
): Cypress.Chainable<Array<RealtimeHost>> =>
  requestLegacyApi({
    query: `object=centreon_realtime_hosts&action=list&search=${search}`,
    token
  }).then((response) => {
    expect(response.status, 'real-time hosts listing').to.eq(200);

    return cy.wrap(response.body as Array<RealtimeHost>, { log: false });
  });

const getServiceIds = (host: string): Cypress.Chainable<string> =>
  listRealtimeServices(`searchHost=${host}`, adminToken).then((services) => {
    const service = services.find(({ name }) => name === host);

    expect(service, `service of ${host} in the real-time listing`).to.not.eq(
      undefined
    );

    return cy.wrap(`${service?.host_id}_${service?.service_id}`, {
      log: false
    });
  });

const requestMetrics = ({
  ids,
  token,
  withPeriod = true
}: {
  ids: string;
  token: string;
  withPeriod?: boolean;
}): Cypress.Chainable<Cypress.Response<unknown>> => {
  const end = nowInSeconds();
  const period = withPeriod
    ? `&start=${end - oneHourInSeconds}&end=${end}`
    : '';

  return requestLegacyApi({
    query: `object=centreon_metric&action=metricsDataByService&ids=${ids}${period}`,
    token
  });
};

const submitServiceResult = ({
  output,
  perfdata,
  service,
  status
}: {
  output: string;
  perfdata: string;
  service: string;
  status: string;
}): Cypress.Chainable<Cypress.Response<unknown>> =>
  requestLegacyApi({
    body: {
      results: [
        {
          host: currentHost,
          output,
          perfdata,
          service,
          status,
          updatetime: nowInSeconds()
        }
      ]
    },
    method: 'POST',
    query: 'action=submit&object=centreon_submit_results',
    token: adminToken
  });

Given(
  'an administrator account and a shell with curl reaching the central',
  () => {
    expect(admin.login).to.not.eq('');

    // The API v1 entry point answers before any authentication
    requestLegacyApi({ query: 'object=centreon_realtime_hosts&action=list' })
      .its('status')
      .should('eq', 403);
  }
);

When(
  'the administrator posts its login and password to {string}',
  (path: string) => {
    expect(`/centreon/${path}`).to.eq(`${apiActionV1}?action=authenticate`);

    authenticate(admin.login, admin.password);
  }
);

Then(
  'the response is HTTP {int} with a non-empty {string}',
  (status: number, field: string) => {
    expect(lastResponse?.status).to.eq(status);

    const body = responseBody() as Record<string, unknown>;

    expect(body[field]).to.be.a('string').and.not.be.empty;

    lastToken = String(body[field]);
  }
);

Then(
  'a real-time hosts listing sent with this token in the {string} header returns HTTP {int} and a JSON array',
  (header: string, status: number) => {
    expect(header).to.eq('centreon-auth-token');

    requestLegacyApi({
      query: 'object=centreon_realtime_hosts&action=list',
      token: lastToken
    }).then((response) => {
      expect(response.status).to.eq(status);
      expect(response.body).to.be.an('array');
    });
  }
);

When('the authenticate action receives a wrong password', () => {
  authenticate(admin.login, `${admin.password}-wrong`);
});

When('the authenticate action receives no password', () => {
  authenticate(admin.login);
});

Then(
  'the response is HTTP {int} {string}',
  (status: number, message: string) => {
    expect(lastResponse?.status).to.eq(status);
    expect(responseBody()).to.eq(message);
  }
);

When('the listing is sent without the {string} header', (header: string) => {
  expect(header).to.eq('centreon-auth-token');

  requestLegacyApi({ query: 'object=centreon_realtime_hosts&action=list' });
});

When('the listing is sent with an unknown token', () => {
  requestLegacyApi({
    query: 'object=centreon_realtime_hosts&action=list',
    token: `rbt-unknown-token-${Date.now()}`
  });
});

Then(
  'the response is HTTP {int} {string} and lists no host',
  (status: number, message: string) => {
    expect(lastResponse?.status).to.eq(status);
    expect(responseBody()).to.eq(message);
    expect(responseBody()).to.not.be.an('array');
  }
);

Given('an administrator token from the API v1 authenticate action', () => {
  authenticate(admin.login, admin.password).then((response) => {
    expect(response.status).to.eq(200);

    adminToken = String(
      (parseApiBody(response.body) as Record<string, unknown>).authToken
    );
  });
});

Given(
  'a host {string} with a passive service {string} created through the API v1 CLAPI action and exported with APPLYCFG',
  (host: string, service: string) => {
    currentHost = host;
    currentService = service;

    createHostWithService({ host, service, template: 'Ping-LAN' });
    clapi('SETPARAM', 'SERVICE', `${host};${service};active_checks_enabled;0`);
    clapi('SETPARAM', 'SERVICE', `${host};${service};passive_checks_enabled;1`);
    applyConfiguration();

    checkServicesAreMonitored([{ name: service }]);
    cy.wait(brokerReloadWaitingTime);
  }
);

When(
  'a critical result with output {string} and perfdata {string} is submitted for the service through API v1',
  (output: string, perfdata: string) => {
    submittedPerfdata = perfdata;

    submitServiceResult({
      output,
      perfdata,
      service: currentService,
      status: 'critical'
    }).then((response) => {
      expect(response.status).to.eq(200);

      const { results } = parseApiBody(response.body) as {
        results: Array<{ code: number }>;
      };

      expect(results).to.have.length(1);
      expect(results[0].code).to.eq(202);
    });
  }
);

Then(
  'the real-time services listing searched on {string} returns {string} with state {int}, output {string} and that perfdata',
  (host: string, service: string, state: number, output: string) => {
    cy.waitUntil(
      () =>
        listRealtimeServices(`searchHost=${host}`, adminToken).then(
          (services) =>
            services.some(
              ({ description, state: currentState }) =>
                description === service && Number(currentState) === state
            )
        ),
      {
        errorMsg: `${service} did not reach the state ${state}`,
        interval: pollingInterval,
        timeout: realtimeTimeout
      }
    );

    listRealtimeServices(`searchHost=${host}`, adminToken).then((services) => {
      const found = services.find(({ description }) => description === service);

      expect(found?.output.trim()).to.eq(output);
      expect(found?.perfdata.trim()).to.eq(submittedPerfdata);
    });
  }
);

Then(
  'the listing filtered on status {string} contains the service and the listing filtered on status {string} does not',
  (includedStatus: string, excludedStatus: string) => {
    listRealtimeServices(
      `searchHost=${currentHost}&status=${includedStatus}`,
      adminToken
    ).then((services) => {
      expect(services.map(({ description }) => description)).to.include(
        currentService
      );
    });

    listRealtimeServices(
      `searchHost=${currentHost}&status=${excludedStatus}`,
      adminToken
    ).then((services) => {
      expect(services).to.be.an('array');
      expect(services.map(({ description }) => description)).to.not.include(
        currentService
      );
    });
  }
);

Then(
  'a result for an unknown service is answered with code {int} {string}',
  (code: number, message: string) => {
    submitServiceResult({
      output: 'rbt unknown service',
      perfdata: '',
      service: 'rbt-api-unknown-service',
      status: 'critical'
    }).then(() => {
      const { results } = responseBody() as {
        results: Array<{ code: number; message: string }>;
      };

      expect(results).to.have.length(1);
      expect(results[0].code).to.eq(code);
      expect(results[0].message).to.eq(message);
    });
  }
);

Given(
  'a host {string} with the service {string} from the template {string} checked every minute, created through the API v1 CLAPI action and exported',
  (host: string, service: string, template: string) => {
    currentHost = host;
    currentService = service;

    createHostWithService({ host, service, template });
    clapi('SETPARAM', 'SERVICE', `${host};${service};normal_check_interval;1`);
    clapi('SETPARAM', 'SERVICE', `${host};${service};retry_check_interval;1`);
    applyConfiguration();

    checkServicesAreMonitored([{ name: service }]);
  }
);

Given('the service has been checked for 5 minutes', () => {
  getServiceIds(currentHost).then((ids) => {
    metricServiceIds = ids;
  });

  // Up to 5 minutes of checks, until the RRD files hold numeric values
  cy.waitUntil(
    () =>
      requestMetrics({ ids: metricServiceIds, token: adminToken }).then(
        (response) =>
          response.status === 200 &&
          (response.body as Array<MetricEntry>).some(entryHasNumericValue)
      ),
    {
      errorMsg: `No metric value for ${currentService} after 5 minutes`,
      interval: pollingInterval,
      timeout: metricTimeout
    }
  );
});

When(
  'the metric data of the service over the last hour is requested from {string} with the action {string}',
  (object: string, action: string) => {
    expect(`${object}/${action}`).to.eq('centreon_metric/metricsDataByService');

    requestMetrics({ ids: metricServiceIds, token: adminToken });
  }
);

Then(
  'the response is HTTP {int} with one entry for {string}',
  (status: number, idPattern: string) => {
    expect(idPattern).to.eq('<host id>_<service id>');
    expect(lastResponse?.status).to.eq(status);

    const entries = responseBody() as Array<MetricEntry>;

    expect(entries).to.have.length(1);
    expect(entries[0].service_id).to.eq(metricServiceIds);
  }
);

Then(
  'each curve has a label and as many values as there are timestamps, with at least one numeric value',
  () => {
    const [entry] = responseBody() as Array<MetricEntry>;

    expect(entry.data).to.not.be.empty;

    entry.data.forEach((curve) => {
      expect(curve.label).to.be.a('string').and.not.be.empty;
      expect(curve.data).to.have.length(entry.times.length);
    });

    expect(entry.data.some(curveHasNumericValue)).to.eq(true);
  }
);

When('the start and end parameters are missing', () => {
  requestMetrics({
    ids: metricServiceIds,
    token: adminToken,
    withPeriod: false
  });
});

When('an unknown service is requested', () => {
  requestMetrics({ ids: '999999_999999', token: adminToken });
});

Given(
  'hosts {string} and {string} with a {string} service each, created through the API v1 CLAPI action and exported',
  (firstHost: string, secondHost: string, template: string) => {
    [firstHost, secondHost].forEach((host) => {
      createHostWithService({ host, service: template, template });
    });
    applyConfiguration();

    // One service per host, both named after the template
    checkServicesAreMonitored([{ name: template }, { name: template }]);
  }
);

Given(
  'a non-admin user {string} with only {string}, member of an access group granted {string} only',
  (user: string, apiRight: string, host: string) => {
    const aclGroup = `${user}-group`;
    const aclResource = `${user}-resource`;

    expect(apiRightParameters).to.have.property(apiRight);

    clapi(
      'ADD',
      'CONTACT',
      `${user};${user};${user}@localhost;${contactPassword};0;1;en_US;local`
    );
    clapi('SETPARAM', 'CONTACT', `${user};${apiRightParameters[apiRight]};1`);
    clapi('ADD', 'ACLGROUP', `${aclGroup};${aclGroup}`);
    clapi('SETCONTACT', 'ACLGROUP', `${aclGroup};${user}`);
    clapi('ADD', 'ACLRESOURCE', `${aclResource};${aclResource}`);
    clapi('GRANT_HOST', 'ACLRESOURCE', `${aclResource};${host}`);
    clapi('ADDRESOURCE', 'ACLGROUP', `${aclGroup};${aclResource}`);
    clapi('RELOAD', 'ACL', '');

    // Compute the centreon_acl table now rather than waiting for the cron
    cy.applyAcl();
  }
);

Given('a non-admin user {string} without any API access', (user: string) => {
  clapi(
    'ADD',
    'CONTACT',
    `${user};${user};${user}@localhost;${contactPassword};0;1;en_US;local`
  );
});

When('{string} authenticates on API v1', (user: string) => {
  authenticate(user, contactPassword);
});

Then('the response is HTTP {int} with an authToken', (status: number) => {
  expect(lastResponse?.status).to.eq(status);

  const { authToken } = responseBody() as { authToken?: string };

  expect(authToken).to.be.a('string').and.not.be.empty;

  lastToken = String(authToken);
});

Then(
  'with this token a CLAPI action is refused with HTTP {int} {string}',
  (status: number, message: string) => {
    requestLegacyApi({
      body: { action: 'SHOW', object: 'HOST', values: '' },
      method: 'POST',
      query: 'action=action&object=centreon_clapi',
      token: lastToken
    }).then((response) => {
      expect(response.status).to.eq(status);
      expect(parseApiBody(response.body)).to.eq(message);
    });
  }
);

Then(
  'the real-time hosts listing searched on {string} returns {string} only while the administrator gets both hosts',
  (search: string, allowedHost: string) => {
    listRealtimeHosts(search, lastToken).then((hosts) => {
      expect(hosts.map(({ name }) => name)).to.deep.eq([allowedHost]);
    });

    listRealtimeHosts(search, adminToken).then((hosts) => {
      expect(hosts.map(({ name }) => name).sort()).to.deep.eq(
        Object.keys(monitoredServices).sort()
      );
    });
  }
);

Then(
  'the real-time services listing returns only the service of {string}',
  (allowedHost: string) => {
    const search = commonPrefix(Object.keys(monitoredServices));

    listRealtimeServices(`searchHost=${search}`, lastToken).then((services) => {
      expect(services.map(({ name }) => name)).to.deep.eq([allowedHost]);
      expect(services[0].description).to.eq(monitoredServices[allowedHost]);
    });
  }
);

Then(
  'the metric data of the service of {string} is refused with HTTP {int} {string}',
  (host: string, status: number, message: string) => {
    getServiceIds(host).then((ids) => {
      requestMetrics({ ids, token: lastToken }).then((response) => {
        expect(response.status).to.eq(status);
        expect(parseApiBody(response.body)).to.eq(message);
      });
    });
  }
);
