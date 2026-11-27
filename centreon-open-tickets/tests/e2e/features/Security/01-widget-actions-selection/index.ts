import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { INTERCEPTORS } from 'fixtures/shared/constants/interceptors';
import { PAGES } from 'fixtures/shared/constants/pages';

import {
  checkHostsAreMonitored,
  checkServicesAreMonitored
} from '../../../common';
import {
  buildAcknowledgedCountQuery,
  buildFileSizeCommand,
  buildGorgoneCommandsCommand,
  buildInjectedSelection,
  buildInsertTicketLinkQuery,
  buildInsertTicketQuery,
  buildLogSizesCommand,
  buildNewLogContentCommand,
  buildPlainSelection,
  buildRuleQuery,
  buildServiceIdsQuery,
  commandTargets,
  type ExternalCommand,
  gorgoneLogFile,
  parseGorgoneCommands,
  parseLogSizes,
  parseWidgetActionResult,
  phpWarningPattern,
  type SelectedService,
  sqlErrorPattern,
  type WidgetActionResult
} from '../common';

interface Credentials {
  login: string;
  password: string;
}

interface ActionPair {
  injected: WidgetActionResult;
  plain: WidgetActionResult;
}

const resources = [
  { host: 'rbt-ot-host-a', service: 'rbt-ot-svc-a' },
  { host: 'rbt-ot-host-b', service: 'rbt-ot-svc-b' }
];
const restrictedUser: Credentials = {
  login: 'rbt-ot-user',
  password: 'Centreon!2021RbtOt'
};
const glpiRuleAlias = 'glpi';
// Comments of the acknowledgements, used to find their commands in the logs
const ackMarker = 'rbt-ot-ack';
const plainAckMarker = 'rbt-ot-ack-plain';
const injectedAckMarker = 'rbt-ot-ack-injected';
const restrictedAckMarker = 'rbt-ot-ack-restricted';
const commandTimeout = 60_000;
const pollingInterval = 3000;
// Time for Gorgone to forward commands that would have been written
const commandWaitingTime = 15_000;
// APPLYCFG reloads Broker a few seconds after Engine: a result sent while its
// SQL output restarts never reaches the real-time tables
const brokerReloadWaitingTime = 15_000;

let selected: Array<SelectedService> = [];
let gorgoneOffset = 0;
let logSizes: Record<string, number> = {};
let lastResult: WidgetActionResult | null = null;
let actionPairs: Record<string, ActionPair> = {};
let rule = { providerId: 0, ruleId: 0 };

beforeEach(() => {
  selected = [];
  gorgoneOffset = 0;
  logSizes = {};
  lastResult = null;
  actionPairs = {};
  rule = { providerId: 0, ruleId: 0 };

  cy.intercept({
    method: 'GET',
    url: INTERCEPTORS.api.navigation_list
  }).as('getNavigationList');
  cy.intercept({
    method: 'POST',
    url: INTERCEPTORS.openTickets.ruleAction
  }).as('ruleAction');

  cy.startContainers({
    moduleName: 'centreon-open-tickets',
    profiles: ['glpi'],
    useSlim: false
  });
});

afterEach(() => {
  cy.stopContainers();
});

const clapi = (action: string, object: string, values: string): void => {
  cy.executeActionViaClapi({ bodyContent: { action, object, values } });
};

// Same request as the widget: a form field "data" holding the JSON payload
const postWidgetAction = (
  data: Record<string, unknown>
): Cypress.Chainable<WidgetActionResult> =>
  cy
    .request({
      body: { data: JSON.stringify(data) },
      form: true,
      method: 'POST',
      url: INTERCEPTORS.openTickets.widgetAction
    })
    .then(({ body }) => cy.wrap(parseWidgetActionResult(body), { log: false }));

const acknowledge = (
  selection: string,
  comment: string,
  author = 'admin'
): Cypress.Chainable<WidgetActionResult> =>
  postWidgetAction({
    action: 'service-ack',
    form: { author, comment, persistent: 'on', selection, sticky: 'on' }
  });

const scheduleCheck = (
  selection: string,
  forced: boolean
): Cypress.Chainable<WidgetActionResult> =>
  postWidgetAction({
    action: 'schedule-check',
    form: { forced, isService: true, selection }
  });

const closeTickets = (
  selection: string
): Cypress.Chainable<WidgetActionResult> =>
  postWidgetAction({
    action: 'close-ticket',
    form: {
      provider_id: String(rule.providerId),
      rule_id: String(rule.ruleId),
      selection
    },
    provider_id: String(rule.providerId),
    rule_id: String(rule.ruleId)
  });

const markGorgoneOffset = (): void => {
  cy.execInContainer({
    command: buildFileSizeCommand(gorgoneLogFile),
    name: 'web'
  }).then(({ output }) => {
    gorgoneOffset = Number(output.trim());
  });
};

const readGorgoneCommands = (): Cypress.Chainable<Array<ExternalCommand>> =>
  cy
    .execInContainer(
      { command: buildGorgoneCommandsCommand(gorgoneOffset), name: 'web' },
      { log: false }
    )
    .then(({ output }) =>
      cy.wrap(parseGorgoneCommands(output), { log: false })
    );

const waitForCommands = (
  matches: (commands: Array<ExternalCommand>) => boolean,
  errorMsg: string
): void => {
  cy.waitUntil(() => readGorgoneCommands().then(matches), {
    errorMsg,
    interval: pollingInterval,
    timeout: commandTimeout
  });
};

const withComment = (
  commands: Array<ExternalCommand>,
  comment: string
): Array<ExternalCommand> =>
  commands.filter(({ raw }) => raw.endsWith(`;${comment}`));

const markLogSizes = (): void => {
  cy.execInContainer({ command: buildLogSizesCommand(), name: 'web' }).then(
    ({ output }) => {
      logSizes = parseLogSizes(output);
    }
  );
};

const readNewLogContent = (): Cypress.Chainable<string> =>
  cy
    .execInContainer(
      { command: buildNewLogContentCommand(logSizes), name: 'web' },
      { log: false }
    )
    .then(({ output }) => cy.wrap(output, { log: false }));

const loginAs = ({ login, password }: Credentials): void => {
  cy.clearCookies();
  cy.request({
    body: { login, password },
    log: false,
    method: 'POST',
    url: INTERCEPTORS.api.localLogin
  })
    .its('status')
    .should('eq', 200);
};

Given(
  'two services on two different hosts are selected in the Open Tickets widget',
  () => {
    resources.forEach(({ host, service }) => {
      cy.addHost({
        activeCheckEnabled: false,
        checkCommand: 'check_centreon_cpu',
        name: host,
        template: 'generic-host'
      }).addService({
        activeCheckEnabled: false,
        host,
        maxCheckAttempts: 1,
        name: service,
        template: 'Ping-LAN'
      });
    });
    cy.applyPollerConfiguration();

    checkHostsAreMonitored(resources.map(({ host }) => ({ name: host })));
    checkServicesAreMonitored(
      resources.map(({ service }) => ({ name: service }))
    );
    cy.wait(brokerReloadWaitingTime);

    // Problems, so that Engine accepts the acknowledgements
    cy.submitResults(
      resources.flatMap(({ host, service }) => [
        { host, output: 'rbt ot down', status: 'down' },
        { host, output: 'rbt ot critical', service, status: 'critical' }
      ])
    );
    checkServicesAreMonitored(
      resources.map(({ service }) => ({ name: service, status: 'critical' }))
    );

    resources.forEach(({ host, service }) => {
      cy.requestOnDatabase({
        database: 'centreon_storage',
        query: buildServiceIdsQuery(host, service)
      }).then(([rows]) => {
        expect(rows, `${host} / ${service}`).to.have.length(1);

        selected.push({
          host,
          hostId: Number(rows[0].host_id),
          service,
          serviceId: Number(rows[0].service_id)
        });
      });
    });

    cy.requestOnDatabase({
      database: 'centreon',
      query: buildRuleQuery(glpiRuleAlias)
    }).then(([rows]) => {
      expect(rows, `Open Tickets rule ${glpiRuleAlias}`).to.have.length(1);

      rule = {
        providerId: Number(rows[0].provider_id),
        ruleId: Number(rows[0].rule_id)
      };
    });

    cy.loginByTypeOfUser({ jsonName: 'admin', loginViaApi: true });
  }
);

When('the user acknowledges the selection', () => {
  markGorgoneOffset();

  cy.then(() => acknowledge(buildPlainSelection(selected), ackMarker)).then(
    (result) => {
      expect(result.code).to.eq(0);
      expect(result.msg).to.eq('successfully sent acknowledgement');
    }
  );
});

Then(
  'each host is acknowledged once, with one host and one service acknowledgement per host',
  () => {
    waitForCommands(
      (commands) => withComment(commands, ackMarker).length >= 4,
      'The acknowledgements were not forwarded to Engine'
    );

    readGorgoneCommands().then((commands) => {
      const acknowledgements = withComment(commands, ackMarker);

      expect(acknowledgements).to.have.length(4);

      selected.forEach(({ host, service }) => {
        const ofHost = acknowledgements.filter(
          (command) => command.host === host
        );

        expect(
          ofHost.filter(({ name }) => name === 'ACKNOWLEDGE_HOST_PROBLEM'),
          `host acknowledgements of ${host}`
        ).to.have.length(1);
        expect(
          ofHost
            .filter(({ name }) => name === 'ACKNOWLEDGE_SVC_PROBLEM')
            .map((command) => command.service),
          `service acknowledgements of ${host}`
        ).to.deep.eq([service]);
      });
    });

    cy.waitUntil(
      () =>
        cy
          .requestOnDatabase({
            database: 'centreon_storage',
            query: buildAcknowledgedCountQuery(selected)
          })
          .then(
            ([rows]) =>
              Number(rows[0].hosts) === selected.length &&
              Number(rows[0].services) === selected.length
          ),
      {
        errorMsg: 'The selected hosts and services are not acknowledged',
        interval: pollingInterval,
        timeout: commandTimeout
      }
    );
  }
);

When('the user schedules a check then a forced check on the selection', () => {
  markGorgoneOffset();

  [false, true].forEach((forced) => {
    cy.then(() => scheduleCheck(buildPlainSelection(selected), forced)).then(
      (result) => {
        expect(result.code).to.eq(0);
        expect(result.msg).to.eq('Successfully scheduled the check');
      }
    );
  });
});

Then(
  'the check commands are sent with the right host and service names',
  () => {
    const checkNames = ['SCHEDULE_SVC_CHECK', 'SCHEDULE_FORCED_SVC_CHECK'];
    const scheduledChecks = (
      commands: Array<ExternalCommand>
    ): Array<ExternalCommand> =>
      commands.filter(({ name }) => name.startsWith('SCHEDULE_'));

    waitForCommands(
      (commands) =>
        scheduledChecks(commands).length >= checkNames.length * selected.length,
      'The check commands were not forwarded to Engine'
    );

    readGorgoneCommands().then((commands) => {
      const checks = scheduledChecks(commands);

      expect(checks).to.have.length(checkNames.length * selected.length);

      checkNames.forEach((checkName) => {
        expect(
          commandTargets(checks.filter(({ name }) => name === checkName)),
          checkName
        ).to.deep.eq(
          selected.map(({ host, service }) => `${host};${service}`).sort()
        );
      });
    });
  }
);

When('the user closes the tickets of services with an open ticket', () => {
  // A ticket is open on each selected service
  selected.forEach((resource) => {
    const ticketValue = `rbt-ot-ticket-${resource.serviceId}`;

    cy.requestOnDatabase({
      database: 'centreon_storage',
      query: buildInsertTicketQuery(ticketValue)
    });
    cy.requestOnDatabase({
      database: 'centreon_storage',
      query: buildInsertTicketLinkQuery(ticketValue, resource)
    });
  });

  markGorgoneOffset();

  cy.then(() => closeTickets(buildPlainSelection(selected))).then((result) => {
    lastResult = result;
  });
});

Then('the confirmation table is displayed', () => {
  expect(lastResult?.code).to.eq(0);
  expect(lastResult?.msg).to.contain('<table class="table">');
  expect(lastResult?.msg).to.contain('Close Tickets');
  expect(lastResult?.msg).to.contain('Tickets closed:');
  expect(lastResult?.msg).to.not.contain('Issue to close tickets');

  // The ticket macro of each ticketed service is emptied
  waitForCommands(
    (commands) =>
      commands.filter(({ name }) => name === 'CHANGE_CUSTOM_SVC_VAR').length >=
      selected.length,
    'The open tickets of the selection were not closed'
  );

  readGorgoneCommands().then((commands) => {
    expect(
      commandTargets(
        commands.filter(({ name }) => name === 'CHANGE_CUSTOM_SVC_VAR')
      )
    ).to.deep.eq(
      selected.map(({ host, service }) => `${host};${service}`).sort()
    );
  });
});

When(
  'the service-ack, schedule-check and close-ticket actions are called with a selection containing SQL injection attempts',
  () => {
    markLogSizes();
    markGorgoneOffset();

    cy.then(() => {
      const plainSelection = buildPlainSelection(selected);
      const injectedSelection = buildInjectedSelection(selected);
      const calls: Record<
        string,
        (
          selection: string,
          isInjected: boolean
        ) => Cypress.Chainable<WidgetActionResult>
      > = {
        'close-ticket': (selection) => closeTickets(selection),
        'schedule-check': (selection) => scheduleCheck(selection, false),
        'service-ack': (selection, isInjected) =>
          acknowledge(
            selection,
            isInjected ? injectedAckMarker : plainAckMarker
          )
      };

      Object.entries(calls).forEach(([action, call]) => {
        call(plainSelection, false).then((plain) => {
          call(injectedSelection, true).then((injected) => {
            actionPairs[action] = { injected, plain };
          });
        });
      });
    });
  }
);

Then('each call returns the same benign result as a plain selection', () => {
  ['service-ack', 'schedule-check', 'close-ticket'].forEach((action) => {
    expect(actionPairs, action).to.have.property(action);
    expect(actionPairs[action].plain.code, `${action} plain`).to.eq(0);
    expect(actionPairs[action].injected, action).to.deep.eq(
      actionPairs[action].plain
    );
  });

  // The injected acknowledgement targets the selection only, as the plain one
  waitForCommands(
    (commands) =>
      withComment(commands, plainAckMarker).length >= 4 &&
      withComment(commands, injectedAckMarker).length >= 4,
    'The acknowledgements were not forwarded to Engine'
  );

  readGorgoneCommands().then((commands) => {
    expect(commandTargets(withComment(commands, injectedAckMarker))).to.deep.eq(
      commandTargets(withComment(commands, plainAckMarker))
    );
  });
});

Then('no SQL error is logged', () => {
  readNewLogContent().then((content) => {
    expect(content).to.not.match(sqlErrorPattern);
  });
});

Given('a non-admin user restricted to one host', () => {
  const [allowed] = selected;
  const aclGroup = `${restrictedUser.login}-group`;
  const aclResource = `${restrictedUser.login}-resource`;

  clapi(
    'ADD',
    'CONTACT',
    `${restrictedUser.login};${restrictedUser.login};${restrictedUser.login}@localhost;${restrictedUser.password};0;1;en_US;local`
  );
  clapi('ADD', 'ACLGROUP', `${aclGroup};${aclGroup}`);
  clapi('SETCONTACT', 'ACLGROUP', `${aclGroup};${restrictedUser.login}`);
  clapi('ADD', 'ACLRESOURCE', `${aclResource};${aclResource}`);
  clapi('GRANT_HOST', 'ACLRESOURCE', `${aclResource};${allowed.host}`);
  clapi('ADDRESOURCE', 'ACLGROUP', `${aclGroup};${aclResource}`);
  clapi('RELOAD', 'ACL', '');

  // Compute the centreon_acl table now rather than waiting for the cron
  cy.applyAcl();

  loginAs(restrictedUser);
});

When('the user selects that host and another one', () => {
  markGorgoneOffset();

  cy.then(() =>
    acknowledge(
      buildPlainSelection(selected),
      restrictedAckMarker,
      restrictedUser.login
    )
  ).then((result) => {
    expect(result.code).to.eq(0);
  });
});

Then('only the commands for the allowed host reach the engine', () => {
  const [allowed, denied] = selected;

  waitForCommands(
    (commands) =>
      withComment(commands, restrictedAckMarker).some(
        ({ host }) => host === allowed.host
      ),
    `The acknowledgement of ${allowed.host} was not forwarded to Engine`
  );

  readGorgoneCommands().then((commands) => {
    const acknowledgements = withComment(commands, restrictedAckMarker);

    expect(commandTargets(acknowledgements)).to.deep.eq(
      [`${allowed.host};`, `${allowed.host};${allowed.service}`].sort()
    );
    expect(
      acknowledgements.filter(({ host }) => host === denied.host)
    ).to.have.length(0);
  });
});

When('a selection without service id is sent', () => {
  markLogSizes();
  markGorgoneOffset();

  cy.then(() => scheduleCheck(String(selected[0].hostId), false)).then(
    (result) => {
      lastResult = result;
    }
  );
});

Then('no resource matches and no PHP warning is logged', () => {
  expect(lastResult?.code).to.eq(0);

  cy.wait(commandWaitingTime);

  readGorgoneCommands().then((commands) => {
    expect(
      commands.filter(({ name }) => name.startsWith('SCHEDULE_')),
      'scheduled checks'
    ).to.have.length(0);
  });

  readNewLogContent().then((content) => {
    expect(content).to.not.match(phpWarningPattern);
    expect(content).to.not.match(sqlErrorPattern);
  });
});

When('the user opens an existing GLPI rule', () => {
  // The rules page is an administration page: the restricted user has no access to it
  cy.loginByTypeOfUser({ jsonName: 'admin', loginViaApi: false });

  cy.then(() => {
    cy.visit(
      `${PAGES.configuration.openTicketsRulesLegacy}&o=c&rule_id=${rule.ruleId}`
    );
  });
});

Then('the rule form is displayed', () => {
  cy.wait('@ruleAction').then(({ response }) => {
    expect(parseWidgetActionResult(response?.body).code).to.eq(0);
  });

  cy.waitForElementInIframe('#main-content', 'input[name="address"]');
  cy.getIframeBody()
    .find('input[name="rule_alias"]')
    .should('have.value', glpiRuleAlias);
  cy.getIframeBody()
    .find('select#provider_id')
    .should('have.value', String(rule.providerId));
  cy.getIframeBody().find('input[name="address"]').should('exist');
});
