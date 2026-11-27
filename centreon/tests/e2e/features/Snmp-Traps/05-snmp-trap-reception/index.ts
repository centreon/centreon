import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { INTERCEPTORS } from 'fixtures/shared/constants/interceptors';

import {
  checkHostsAreMonitored,
  checkServicesAreMonitored
} from '../../../commons';
import {
  buildOtherResourcesWithOutputQuery,
  engineLogFile,
  generateTrapDatabase,
  sendTrap,
  startTrapDaemons,
  testTrapOidBase,
  waitForServiceOutput
} from '../common';

// The Central is the poller of the CI stack: traps are sent from the web
// container to its own snmptrapd, so the trap host has the address 127.0.0.1
const trapHostName = 'qa-trap-host';
const trapHostAddress = '127.0.0.1';
const trapName = 'qa-trap-214700';
const trapOid = `${testTrapOidBase}.214700`;
// Directory written by the commands of the varbinds if they were run: open to
// every user, so that a command run by centreontrapd would create its file
const spoolDirectory = '/var/spool/centreon';
const scriptArgumentsFile = '/tmp/qa-trap-214700-arguments';

let trapServiceName = '';
let sentVarbinds: Array<string> = [];

beforeEach(() => {
  cy.startContainers();

  trapServiceName = '';
  sentVarbinds = [];

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

const sendVarbinds = (varbinds: Array<string>): void => {
  sentVarbinds = varbinds;
  sendTrap({ oid: trapOid, varbinds });
};

const outputOfTrapService = (
  matches: (output: string) => boolean
): Cypress.Chainable<string> =>
  waitForServiceOutput({
    host: trapHostName,
    matches,
    service: trapServiceName
  });

Given(
  'a passive service {string} on a host monitored by a poller',
  (service: string) => {
    trapServiceName = service;

    cy.addHost({
      activeCheckEnabled: false,
      address: trapHostAddress,
      checkCommand: 'check_centreon_cpu',
      name: trapHostName,
      template: 'generic-host'
    }).addService({
      activeCheckEnabled: false,
      host: trapHostName,
      maxCheckAttempts: 1,
      name: service,
      template: 'Ping-LAN'
    });
  }
);

Given(
  'a trap definition linked to {string} with the output {string} and "Submit result" enabled',
  (service: string, output: string) => {
    clapi('ADD', 'TRAP', `${trapName};${trapOid}`);
    clapi('SETPARAM', 'TRAP', `${trapName};vendor;Generic`);
    clapi('SETPARAM', 'TRAP', `${trapName};output;${output}`);
    clapi('SETPARAM', 'TRAP', `${trapName};status;warning`);
    clapi('SETPARAM', 'TRAP', `${trapName};submit_result_enable;1`);
    clapi('ADDTRAP', 'SERVICE', `${trapHostName};${service};${trapName}`);
  }
);

Given(
  'the trap database is generated and exported and centreontrapd is restarted on the poller',
  () => {
    cy.applyPollerConfiguration();
    checkHostsAreMonitored([{ name: trapHostName }]);
    checkServicesAreMonitored([{ name: trapServiceName }]);
    // Broker reloads after Engine: let it settle before results are submitted
    cy.wait(15000);

    cy.loginByTypeOfUser({ jsonName: 'admin', loginViaApi: true });
    generateTrapDatabase({ apply: true });

    cy.execInContainer({
      command: `install -d -m 1777 ${spoolDirectory}`,
      name: 'web'
    });
    startTrapDaemons();
  }
);

When(
  /^a trap with the varbind "(.*)" is sent to the poller$/,
  (varbind: string) => {
    sendVarbinds([varbind]);
  }
);

When(
  'a trap with a varbind holding {string}, a line break and {string} is sent to the poller',
  (firstPart: string, secondPart: string) => {
    sendVarbinds([`${firstPart}\n${secondPart}`]);
  }
);

When(
  'a trap with the varbinds {string} and {string} is sent to the poller',
  (first: string, second: string) => {
    sendVarbinds([first, second]);
  }
);

Then(
  /^the output of "([^"]*)" is "(.*)"$/,
  (service: string, shown: string) => {
    expect(service).to.eq(trapServiceName);

    outputOfTrapService((output) => output === shown);
  }
);

Then('no file {string} exists on the poller', (path: string) => {
  cy.execInContainer({
    command: `test -e '${path}' && echo present || echo absent`,
    name: 'web'
  }).then(({ output }) => {
    expect(output.trim(), path).to.eq('absent');
  });
});

Then(
  'the output of {string} holds both parts on one line',
  (service: string) => {
    expect(service).to.eq(trapServiceName);

    const [firstPart, secondPart] = sentVarbinds[0].split('\n');
    // The Engine shows ";" as ":" in a passive output: only the last field of
    // the second part is looked for as is
    const secondPartEnd = secondPart.split(';').pop() ?? secondPart;

    outputOfTrapService(
      (output) => output.includes(firstPart) && output.includes(secondPartEnd)
    ).then((output) => {
      expect(output, 'output on one line').to.not.contain('\n');
      expect(output.indexOf(firstPart)).to.be.lessThan(
        output.indexOf(secondPartEnd)
      );
    });
  }
);

Then('no other service gets the result {string}', (result: string) => {
  cy.requestOnDatabase({
    database: 'centreon_storage',
    params: [`%${result}%`, trapServiceName, trapHostName],
    query: buildOtherResourcesWithOutputQuery()
  }).then(([rows]) => {
    expect(rows, `resources other than ${trapServiceName}`).to.have.length(0);
  });

  // A split command would reach the Engine as an external command of its own
  cy.execInContainer({
    command: `grep -cF 'COMMAND: PROCESS_SERVICE_CHECK_RESULT;qa;qa;' ${engineLogFile} || true`,
    name: 'web'
  }).then(({ output }) => {
    expect(Number(output.trim()), 'external commands for qa;qa').to.eq(0);
  });
});

Given('the output of the trap definition is {string}', (output: string) => {
  clapi('SETPARAM', 'TRAP', `${trapName};output;${output}`);
});

Then(
  'the output of {string} is the same as before the fix',
  (service: string) => {
    expect(service).to.eq(trapServiceName);

    // "$*" is all the varbinds joined with the centreontrapd separator, a space
    const expectedOutput = sentVarbinds.join(' ');

    outputOfTrapService((output) => output === expectedOutput);
  }
);

Given(
  /^the special command of the trap definition is "(.*)" and the script writes its arguments to a file$/,
  (command: string) => {
    const [scriptPath] = command.split(' ');

    // The script writes its number of arguments, then each argument on a line
    cy.execInContainer({
      command: [
        `printf '%s\\n' '#!/bin/sh' 'printf "%s\\n" "$#" "$@" > ${scriptArgumentsFile}' > ${scriptPath}`,
        `chmod 755 ${scriptPath}`
      ],
      name: 'web'
    });

    clapi('SETPARAM', 'TRAP', `${trapName};execution_command_enable;1`);
    clapi('SETPARAM', 'TRAP', `${trapName};execution_command;${command}`);
  }
);

Then(
  /^the script has received "(.*)" as its only argument$/,
  (argument: string) => {
    cy.waitUntil(
      () =>
        cy
          .execInContainer({
            command: `test -s ${scriptArgumentsFile} && echo written || echo missing`,
            name: 'web'
          })
          .then(({ output }) => output.trim() === 'written'),
      {
        errorMsg: `${scriptArgumentsFile} was not written by the special command`,
        interval: 2000,
        timeout: 60000
      }
    );

    cy.execInContainer({
      command: `cat ${scriptArgumentsFile}`,
      name: 'web'
    }).then(({ output }) => {
      expect(output.replace(/\n$/, '').split('\n')).to.deep.eq(['1', argument]);
    });
  }
);
