import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { INTERCEPTORS } from 'fixtures/shared/constants/interceptors';

import {
  checkHostsAreMonitored,
  checkServicesAreMonitored
} from '../../../commons';
import {
  brokerLogDirectory,
  buildLogErrorCountCommand,
  buildLogLineCountsCommand,
  engineLogFile,
  parseLogLineCounts
} from '../common';

const flowHostName = 'rbt-flow-host';
const flowServiceName = 'rbt-flow-service';

// Broker log files of cbd; central-module-master.log is written by the cbmod
// of the Engine, which logs the loss of its Broker connection
const brokerLogFiles = `$(ls ${brokerLogDirectory}/*.log | grep -v module)`;

let logLineCounts: Record<string, number> = {};

beforeEach(() => {
  cy.startContainers();

  logLineCounts = {};

  cy.intercept({
    method: 'GET',
    url: INTERCEPTORS.api.navigation_list
  }).as('getNavigationList');
});

afterEach(() => {
  cy.stopContainers();
});

const serviceStates = (): Cypress.Chainable<Array<string>> =>
  cy
    .execInContainer({
      command: 'systemctl is-active cbd centengine || true',
      name: 'web'
    })
    .then(({ output }) =>
      output
        .trim()
        .split('\n')
        .map((state: string) => state.trim())
    );

const waitForServicesState = (state: string): void => {
  cy.waitUntil(
    () =>
      serviceStates().then(
        (states) =>
          states.length === 2 && states.every((current) => current === state)
      ),
    {
      errorMsg: `cbd and centengine are not both ${state}`,
      interval: 2000,
      timeout: 120000
    }
  );
};

const recordLogLineCounts = (files: string): void => {
  cy.execInContainer({
    command: buildLogLineCountsCommand(files),
    name: 'web'
  }).then(({ output }) => {
    logLineCounts = { ...logLineCounts, ...parseLogLineCounts(output) };
  });
};

const submitAndCheckResult = (status: string, output: string): void => {
  cy.submitResults([
    { host: flowHostName, output, service: flowServiceName, status }
  ]);
  checkServicesAreMonitored([{ name: flowServiceName, output, status }]);
};

Given(
  'a platform is running with the Broker and the Engine agent services started',
  () => {
    waitForServicesState('active');
  }
);

Given('monitoring events are flowing through the platform', () => {
  cy.addHost({
    activeCheckEnabled: false,
    checkCommand: 'check_centreon_cpu',
    name: flowHostName,
    template: 'generic-host'
  })
    .addService({
      activeCheckEnabled: false,
      host: flowHostName,
      maxCheckAttempts: 1,
      name: flowServiceName,
      template: 'Ping-LAN'
    })
    .applyPollerConfiguration();

  checkHostsAreMonitored([{ name: flowHostName }]);
  checkServicesAreMonitored([{ name: flowServiceName }]);
  // Broker reloads after Engine and drops a result sent meanwhile
  cy.wait(15000);

  submitAndCheckResult('critical', 'rbt flow before the restart');
});

When('the administrator stops the Broker service', () => {
  recordLogLineCounts(brokerLogFiles);
  cy.execInContainer({ command: 'systemctl stop cbd', name: 'web' });
});

When('the administrator stops the Engine agent service', () => {
  recordLogLineCounts(engineLogFile);
  cy.execInContainer({ command: 'systemctl stop centengine', name: 'web' });
});

Then(
  'each service has exited cleanly with no crash and no error in its log',
  () => {
    // "failed" instead of "inactive" means the process did not exit cleanly
    waitForServicesState('inactive');

    ['cbd', 'centengine'].forEach((processName) => {
      cy.execInContainer({
        command: `pgrep -x ${processName} || true`,
        name: 'web'
      }).then(({ output }) => {
        expect(output.trim(), `${processName} processes`).to.eq('');
      });
    });

    cy.then(() => {
      // Fails closed: the log files of both services were found
      expect(Object.keys(logLineCounts), 'checked log files').to.include(
        engineLogFile
      );
      expect(
        Object.keys(logLineCounts).filter((file) =>
          file.startsWith(brokerLogDirectory)
        ),
        'checked Broker log files'
      ).to.have.length.of.at.least(1);

      Object.entries(logLineCounts).forEach(([file, lineCount]) => {
        cy.execInContainer({
          command: buildLogErrorCountCommand(file, lineCount),
          name: 'web'
        }).then(({ output }) => {
          expect(
            output.trim(),
            `errors or crash written in ${file} since the stop`
          ).to.eq('');
        });
      });
    });
  }
);

When(
  'the administrator starts the Broker and the Engine agent services again',
  () => {
    cy.execInContainer({
      command: ['systemctl start cbd', 'systemctl start centengine'],
      name: 'web'
    });
  }
);

Then('both services are running', () => {
  waitForServicesState('active');
});

Then('the monitoring events are processed again', () => {
  // Let the Engine and the Broker connect again before submitting a result
  cy.wait(15000);

  submitAndCheckResult('ok', 'rbt flow after the restart');
});
