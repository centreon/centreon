import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { INTERCEPTORS } from 'fixtures/shared/constants/interceptors';

import {
  checkHostsAreMonitored,
  checkServicesAreMonitored
} from '../../../commons';
import {
  buildInsertPreexecQuery,
  generateTrapDatabase,
  sendTrap,
  startTrapDaemons,
  testTrapOidBase,
  trapdLogFile,
  waitForServiceOutput
} from '../common';

// Traps are sent from the web container to its own snmptrapd: their source
// address, 127.0.0.1, is the one the trap host must have to be matched
const trapHostAddress = '127.0.0.1';

const macroHostName = 'rbt-macro-host';
const macroServiceName = 'rbt-macro-service';
const macroTrapName = 'rbt-macro-trap';

let trapOids: Record<string, string> = {};
let trapServices: Record<string, { host: string; service: string }> = {};

beforeEach(() => {
  cy.startContainers();

  trapOids = {};
  trapServices = {};

  cy.intercept({
    method: 'GET',
    url: INTERCEPTORS.api.navigation_list
  }).as('getNavigationList');
  cy.intercept({
    method: 'GET',
    url: INTERCEPTORS.pages.time_zone
  }).as('getTimeZone');
});

afterEach(() => {
  cy.stopContainers();
});

const clapi = (action: string, object: string, values: string): void => {
  cy.executeActionViaClapi({ bodyContent: { action, object, values } });
};

// A trap with its own OID, writing its output to the passive service it is
// linked to through "Submit result"
const createTrap = (trapName: string, output: string): void => {
  trapOids[trapName] = `${testTrapOidBase}.${Object.keys(trapOids).length + 1}`;

  clapi('ADD', 'TRAP', `${trapName};${trapOids[trapName]}`);
  clapi('SETPARAM', 'TRAP', `${trapName};vendor;Generic`);
  clapi('SETPARAM', 'TRAP', `${trapName};output;${output}`);
  clapi('SETPARAM', 'TRAP', `${trapName};status;unknown`);
  clapi('SETPARAM', 'TRAP', `${trapName};submit_result_enable;1`);
};

const addPassiveService = (host: string, service: string): void => {
  cy.addHost({
    activeCheckEnabled: false,
    address: trapHostAddress,
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
};

const exportCentralConfiguration = (host: string, service: string): void => {
  cy.applyPollerConfiguration();
  checkHostsAreMonitored([{ name: host }]);
  checkServicesAreMonitored([{ name: service }]);
  // Broker reloads after Engine: let it settle before results are submitted
  cy.wait(15000);
};

const readTrapdLogLines = (pattern: string): Cypress.Chainable<Array<string>> =>
  cy
    .execInContainer({
      command: `grep -F '${pattern}' ${trapdLogFile} || true`,
      name: 'web'
    })
    .then(({ output }) =>
      output.split('\n').filter((line: string) => line.trim() !== '')
    );

const waitForTrapdLogLine = (pattern: string, value: string): void => {
  cy.waitUntil(
    () =>
      readTrapdLogLines(pattern).then((lines) =>
        lines.some((line) => line.includes(value))
      ),
    {
      errorMsg: `No "${pattern}" line holding "${value}" in ${trapdLogFile}`,
      interval: 2000,
      timeout: 60000
    }
  );
};

Given('an admin user is logged in Centreon', () => {
  cy.loginByTypeOfUser({ jsonName: 'admin', loginViaApi: true });
});

Given('an administrator is logged in', () => {
  cy.loginByTypeOfUser({ jsonName: 'admin', loginViaApi: true });
});

Given(
  'the SNMP trap {string} has an advanced matching rule',
  (trapName: string) => {
    createTrap(trapName, `${trapName} $1`);

    // Advanced matching: the output decides the status of the service
    clapi('SETPARAM', 'TRAP', `${trapName};matching_mode;1`);
    clapi('ADDMATCHING', 'TRAP', `${trapName};@OUTPUT@;/up/;warning`);
    clapi('ADDMATCHING', 'TRAP', `${trapName};@OUTPUT@;/down/;critical`);
  }
);

Given('snmptrapd and centreontrapd are running on the Central', () => {
  startTrapDaemons();
});

When(
  'the user links the trap {string} to the passive service {string} of the host {string}',
  (trapName: string, service: string, host: string) => {
    addPassiveService(host, service);
    clapi('ADDTRAP', 'SERVICE', `${host};${service};${trapName}`);
    trapServices[trapName] = { host, service };
  }
);

When('the user exports the configuration of the Central', () => {
  const [{ host, service }] = Object.values(trapServices);

  exportCentralConfiguration(host, service);
});

When(
  'the user generates the trap database of the Central with {string} and the {string} signal',
  (action: string, signal: 'Reload' | 'Restart') => {
    expect(action).to.eq('Apply configurations');

    generateTrapDatabase({ apply: true, signal });
  }
);

When(
  'a trap {string} is sent with the value {string}',
  (trapName: string, value: string) => {
    sendTrap({ oid: trapOids[trapName], varbinds: [value] });
  }
);

Then(
  'the service {string} is {string} with the output {string} in Resources status',
  (service: string, status: string, output: string) => {
    const { host } = Object.values(trapServices).find(
      (linked) => linked.service === service
    ) ?? { host: '' };

    waitForServiceOutput({
      host,
      matches: (currentOutput) => currentOutput === output,
      service
    });
    checkServicesAreMonitored([
      { name: service, output, status: status.toLowerCase() }
    ]);
  }
);

Given(
  'a host with the custom macro {string} set to {string} is monitored by the Central',
  (macroName: string, macroValue: string) => {
    cy.addHost({
      activeCheckEnabled: false,
      address: trapHostAddress,
      checkCommand: 'check_centreon_cpu',
      name: macroHostName,
      template: 'generic-host'
    });
    clapi(
      'SETMACRO',
      'HOST',
      `${macroHostName};${macroName};${macroValue};0;rbt host macro`
    );
  }
);

Given(
  'a passive service of this host is linked to a trap whose PREEXEC command and special command use the host macro {string}',
  (macro: string) => {
    cy.addService({
      activeCheckEnabled: false,
      host: macroHostName,
      maxCheckAttempts: 1,
      name: macroServiceName,
      template: 'Ping-LAN'
    });

    createTrap(macroTrapName, `${macroTrapName} $1`);
    clapi('SETPARAM', 'TRAP', `${macroTrapName};execution_command_enable;1`);
    clapi(
      'SETPARAM',
      'TRAP',
      `${macroTrapName};execution_command;/bin/echo special ${macro}`
    );
    // CLAPI has no action for the PREEXEC commands of a trap
    cy.requestOnDatabase({
      database: 'centreon',
      params: [`/bin/echo preexec ${macro}`, macroTrapName],
      query: buildInsertPreexecQuery()
    });
    clapi(
      'ADDTRAP',
      'SERVICE',
      `${macroHostName};${macroServiceName};${macroTrapName}`
    );
  }
);

Given(
  'the SNMP trap configuration is generated and applied on the Central',
  () => {
    exportCentralConfiguration(macroHostName, macroServiceName);
    generateTrapDatabase({ apply: true, signal: 'Reload' });
    startTrapDaemons();
  }
);

When(
  'the Central receives an SNMP trap matching this trap definition from the host',
  () => {
    sendTrap({ oid: trapOids[macroTrapName], varbinds: ['rbt-macro-check'] });
    // The trap has been processed once its output reached the service
    waitForServiceOutput({
      host: macroHostName,
      matches: (output) => output === `${macroTrapName} rbt-macro-check`,
      service: macroServiceName
    });
  }
);

Then(
  'the PREEXEC command output in the centreontrapd log contains {string}',
  (value: string) => {
    waitForTrapdLogLine('EXEC preexec: Output :', value);
  }
);

Then(
  'the special command output in the centreontrapd log contains {string}',
  (value: string) => {
    waitForTrapdLogLine('EXEC: Output :', value);
  }
);

Then(
  'the centreontrapd log does not contain the literal macro {string} in any executed command',
  (macro: string) => {
    readTrapdLogLines('EXEC').then((lines) => {
      // Fails closed: the commands must have been logged at all
      expect(
        lines.filter((line) => line.includes('Output :')),
        'EXEC output lines'
      ).to.have.length.of.at.least(2);

      lines.forEach((line) => {
        expect(line, 'executed command line').to.not.contain(macro);
      });
    });
  }
);
