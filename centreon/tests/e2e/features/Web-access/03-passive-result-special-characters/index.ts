import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { INTERCEPTORS } from 'fixtures/shared/constants/interceptors';
import { PAGES } from 'fixtures/shared/constants/pages';

import {
  checkHostsAreMonitored,
  checkServicesAreMonitored
} from '../../../commons';
import {
  buildCentralPollerQuery,
  buildCommentsCountQuery,
  buildEngineLogCommandCountCommand,
  buildFilesContainingCommand,
  buildPerfdataWithCommand,
  buildPersonaClapiActions,
  buildRealtimeServiceIdsQuery,
  buildServiceNameWithDefinitions,
  generatedConfigurationDirectories,
  injectedCommandName,
  injectedComment,
  injectedServiceName,
  isExportErrorLine,
  isPersona,
  nowInSeconds,
  type Persona,
  parseApiBody,
  personaPassword
} from '../common';

interface Credentials {
  login: string;
  password: string;
}

interface SubmitResultAnswer {
  code: number;
  message: string;
}

const hostName = 'rbt-passive-host';
const serviceName = 'rbt-passive-svc';
const normalOutput = 'rbt normal result';
// APPLYCFG reloads Broker a few seconds after Engine: a result sent while
// its SQL output restarts never reaches the real-time tables
const brokerReloadWaitingTime = 15_000;
// Time for Gorgone and Engine to process the command files written by the web
const commandProcessingWaitingTime = 15_000;

let central = { id: 0, name: '' };
let realtimeIds = { hostId: 0, serviceId: 0 };
let serviceNameResponse: Cypress.Response<unknown> | null = null;
let perfdataResponse: Cypress.Response<unknown> | null = null;
let exportResponse: Cypress.Response<unknown> | null = null;

beforeEach(() => {
  cy.startContainers();

  central = { id: 0, name: '' };
  realtimeIds = { hostId: 0, serviceId: 0 };
  serviceNameResponse = null;
  perfdataResponse = null;
  exportResponse = null;

  cy.intercept({
    method: 'GET',
    url: INTERCEPTORS.api.navigation_list
  }).as('getNavigationList');
  cy.intercept({
    method: 'GET',
    url: `${INTERCEPTORS.api.monitor_resources}?*`
  }).as('getResources');
});

afterEach(() => {
  cy.stopContainers();
});

const clapi = (action: string, object: string, values: string): void => {
  cy.executeActionViaClapi({ bodyContent: { action, object, values } });
};

const createPersona = (persona: Persona): void => {
  const actions = buildPersonaClapiActions(persona);

  if (actions.length === 0) {
    return;
  }

  actions.forEach((bodyContent) => {
    cy.executeActionViaClapi({ bodyContent });
  });
  clapi('RELOAD', 'ACL', '');

  // Compute the centreon_acl table now rather than waiting for the cron
  cy.applyAcl();
};

// Session of the persona, as the login page opens it
const loginAsPersona = (persona: Persona): void => {
  const credentials: Cypress.Chainable<Credentials> =
    persona === 'super_admin'
      ? cy.fixture('users/admin.json')
      : cy.wrap<Credentials>(
          { login: String(persona), password: personaPassword },
          { log: false }
        );

  credentials.then(({ login, password }) => {
    cy.clearCookies();
    cy.request({
      body: { login, password },
      log: false,
      method: 'POST',
      url: INTERCEPTORS.api.local_authentication
    })
      .its('status')
      .should('eq', 200);
  });
};

Given('a host monitored by the central poller', () => {
  cy.requestOnDatabase({
    database: 'centreon',
    query: buildCentralPollerQuery()
  }).then(([rows]) => {
    expect(rows, 'central poller').to.have.length(1);

    central = { id: Number(rows[0].id), name: String(rows[0].name) };

    clapi(
      'ADD',
      'HOST',
      `${hostName};${hostName};127.0.0.1;generic-host;${central.name};`
    );
    clapi('SETPARAM', 'HOST', `${hostName};active_checks_enabled;0`);
    clapi('SETPARAM', 'HOST', `${hostName};passive_checks_enabled;1`);
    clapi('SETPARAM', 'HOST', `${hostName};max_check_attempts;1`);
    cy.addService({
      activeCheckEnabled: false,
      host: hostName,
      maxCheckAttempts: 1,
      name: serviceName,
      template: 'Ping-LAN'
    });
    cy.applyPollerConfiguration(central.name);
  });

  checkHostsAreMonitored([{ name: hostName }]);
  checkServicesAreMonitored([{ name: serviceName }]);
  cy.wait(brokerReloadWaitingTime);

  cy.requestOnDatabase({
    database: 'centreon_storage',
    query: buildRealtimeServiceIdsQuery(hostName, serviceName)
  }).then(([rows]) => {
    expect(rows, `service ${serviceName} in real-time`).to.have.length(1);

    realtimeIds = {
      hostId: Number(rows[0].host_id),
      serviceId: Number(rows[0].service_id)
    };
  });
});

Given('the {word} user is logged in', (persona: string) => {
  if (!isPersona(persona)) {
    throw new Error(`Unknown persona "${persona}"`);
  }

  createPersona(persona);
  loginAsPersona(persona);
});

When(
  'the user submits a passive result with line breaks, braces and a command definition in the service name',
  () => {
    // Submit results entry point of the web session (internal API)
    cy.request({
      body: {
        results: [
          {
            host: hostName,
            output: 'rbt special characters',
            perfdata: '',
            service: buildServiceNameWithDefinitions(hostName, serviceName),
            status: 'ok',
            updatetime: nowInSeconds()
          }
        ]
      },
      failOnStatusCode: false,
      headers: { 'Content-Type': 'application/json' },
      method: 'POST',
      url: INTERCEPTORS.api.internal_submit_results
    }).then((response) => {
      serviceNameResponse = response;
    });
  }
);

When(
  'the user submits a passive result whose performance data holds a line break followed by an external command',
  () => {
    // "Submit a status" of Resources Status
    cy.request({
      body: {
        resources: [
          {
            id: realtimeIds.serviceId,
            output: 'rbt plain output',
            parent: { id: realtimeIds.hostId },
            performance_data: buildPerfdataWithCommand({
              hostName,
              pollerId: central.id,
              timestamp: nowInSeconds()
            }),
            status: 0,
            type: 'service'
          }
        ]
      },
      failOnStatusCode: false,
      method: 'POST',
      url: INTERCEPTORS.api.submit_results_resources
    }).then((response) => {
      perfdataResponse = response;
    });
  }
);

When(
  'an administrator exports and reloads the configuration of the central poller',
  () => {
    // CLAPI with the API v1 token of the admin opened by cy.startContainers()
    cy.executeActionViaClapi({
      bodyContent: { action: 'APPLYCFG', values: central.name },
      failOnError: false
    }).then((response) => {
      exportResponse = response;
    });
  }
);

Then('the submissions are refused or the text is kept as plain data', () => {
  // The service name: refused for its line breaks
  // (CentreonWebService::assertSingleLineValues), or refused to the users
  // without the real-time API access (CentreonSubmitResults::authorize)
  if (serviceNameResponse?.status === 403) {
    expect(parseApiBody(serviceNameResponse.body)).to.eq('Forbidden');
  } else {
    expect(serviceNameResponse?.status).to.be.oneOf([200, 206]);

    const { results } = parseApiBody(serviceNameResponse?.body) as {
      results: Array<SubmitResultAnswer>;
    };

    expect(results).to.have.length(1);
    expect(results[0].code).to.eq(400);
    expect(results[0].message).to.eq('Invalid characters in field "service".');
  }

  // The performance data: refused, or accepted as the data of one result
  // (what the result became is checked by the next steps)
  expect(perfdataResponse?.status).to.be.oneOf([204, 400, 401, 403]);
});

Then('the configuration export ends with no error', () => {
  expect(exportResponse?.status).to.eq(200);

  const lines = (
    (parseApiBody(exportResponse?.body) as { result?: Array<string> }).result ??
    []
  ).map(String);

  expect(lines, 'APPLYCFG output').to.not.be.empty;
  expect(lines.filter(isExportErrorLine), 'APPLYCFG errors').to.deep.eq([]);
});

Then(
  'no injected service or command appears in the generated configuration',
  () => {
    // Positive control: the generated configuration holds the host
    cy.execInContainer({
      command: buildFilesContainingCommand(generatedConfigurationDirectories, [
        hostName
      ]),
      name: 'web'
    })
      .its('output')
      .should('not.be.empty');

    cy.execInContainer({
      command: buildFilesContainingCommand(generatedConfigurationDirectories, [
        injectedCommandName,
        injectedServiceName
      ]),
      name: 'web'
    }).then(({ output }) => {
      expect(String(output).trim(), 'files holding an injected object').to.eq(
        ''
      );
    });
  }
);

Then('no injected external command is executed', () => {
  cy.wait(commandProcessingWaitingTime);

  cy.requestOnDatabase({
    database: 'centreon_storage',
    query: buildCommentsCountQuery(injectedComment)
  }).then(([rows]) => {
    expect(rows).to.have.length(1);
    expect(Number(rows[0].count), 'injected comments').to.eq(0);
  });

  cy.execInContainer({
    command: buildEngineLogCommandCountCommand(hostName),
    name: 'web'
  }).then(({ output }) => {
    expect(String(output).trim(), 'injected commands in centengine.log').to.eq(
      '0'
    );
  });
});

Then(
  'a normal passive result is still shown on the resource in Resources Status',
  () => {
    cy.submitResults([
      {
        host: hostName,
        output: normalOutput,
        service: serviceName,
        status: 'critical'
      }
    ]);
    checkServicesAreMonitored([
      { name: serviceName, output: normalOutput, status: 'critical' }
    ]);

    // A critical service is an unhandled problem: the default filter lists it
    cy.clearCookies();
    cy.loginByTypeOfUser({ jsonName: 'admin', loginViaApi: true });
    cy.visit(PAGES.monitoring.resourcesStatus);
    cy.wait('@getResources');
    cy.contains(normalOutput).should('be.visible');
    cy.contains(serviceName).should('be.visible');
  }
);
