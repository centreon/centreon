import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { INTERCEPTORS } from 'fixtures/shared/constants/interceptors';

import {
  exitCodes,
  hostCheckCommandName,
  processServiceCheckResult
} from '../common';

const criticalOutput = 'CRITICAL - D:\\Data is 95% full';
const criticalPerfdata = 'used=95%;80;90;0;100';
const okOutput = 'OK - rbt passive result back to normal';
const missingService = 'rbt-missing-svc';

let hostName = '';
let serviceName = '';
let logMark = 0;
let enginePid = 0;

const toNumber = (value: string | number | null | undefined): number =>
  Number(value ?? 0);

const firstLine = (value: string | number | null | undefined): string =>
  String(value ?? '')
    .split('\n')[0]
    .trim();

const writeResult = (service: string, exitCode: number, output: string) => {
  cy.getEngineLogLineCount().then((count) => {
    logMark = count;
  });
  cy.writeEngineExternalCommand(
    processServiceCheckResult(hostName, service, exitCode, output)
  );
};

beforeEach(() => {
  cy.startContainers();

  hostName = '';
  serviceName = '';
  logMark = 0;
  enginePid = 0;

  cy.intercept({
    method: 'GET',
    url: INTERCEPTORS.api.navigation_list
  }).as('getNavigationList');
});

afterEach(() => {
  cy.stopContainers();
});

Given('an administrator is logged in a Centreon server', () => {
  cy.loginByTypeOfUser({ jsonName: 'admin', loginViaApi: true });
});

Given(
  'a host {string} with a passive service {string} exported to the poller',
  (host: string, service: string) => {
    hostName = host;
    serviceName = service;

    cy.installTestCheckCommands();
    cy.addHost({
      checkCommand: hostCheckCommandName,
      name: host,
      template: 'generic-host'
    })
      .addService({
        activeCheckEnabled: false,
        // Engine needs a check command, even for a passive service
        checkCommand: hostCheckCommandName,
        host,
        maxCheckAttempts: 1,
        name: service,
        passiveCheckEnabled: true,
        template: 'generic-service'
      })
      .applyPollerConfiguration();

    cy.waitForServiceStatus({
      condition: (row) => toNumber(row.enabled) === 1,
      description: 'monitored',
      host,
      service
    });
  }
);

Given('the Engine external command file of the poller exists', () => {
  cy.getEngineSettings().then(({ commandFile }) => {
    cy.execInContainer({ command: `test -p ${commandFile}`, name: 'web' });
  });
});

When(
  'a Critical result whose output contains a backslash is written to the command file for {string}',
  (service: string) => {
    writeResult(
      service,
      exitCodes.critical,
      `${criticalOutput}|${criticalPerfdata}`
    );
  }
);

Then(
  'centengine.log reports the external command and the passive service check',
  () => {
    cy.then(() =>
      cy.waitForEngineLog({
        fromLine: logMark,
        text: `EXTERNAL COMMAND: ${processServiceCheckResult(hostName, serviceName, exitCodes.critical, '')}`
      })
    ).then((lines) => {
      expect(lines.join('\n'), 'external command logged').to.contain(
        `${criticalOutput}|${criticalPerfdata}`
      );
    });

    cy.then(() =>
      cy.waitForEngineLog({
        fromLine: logMark,
        text: `PASSIVE SERVICE CHECK: ${hostName};${serviceName};${exitCodes.critical};`
      })
    ).then((lines) => {
      expect(lines.join('\n'), 'passive service check logged').to.contain(
        criticalOutput
      );
    });
  }
);

Then(
  '{string} is Critical with the full output and its perfdata',
  (service: string) => {
    cy.waitForServiceStatus({
      condition: (row) =>
        toNumber(row.checked) === 1 &&
        toNumber(row.state) === exitCodes.critical,
      description: 'Critical',
      host: hostName,
      service
    }).then((row) => {
      expect(toNumber(row.check_type), 'passive check type').to.eq(1);
      expect(firstLine(row.output), 'output').to.eq(criticalOutput);
      expect(String(row.perfdata ?? '').trim(), 'perfdata').to.eq(
        criticalPerfdata
      );
    });
  }
);

When('an Ok result is written to the command file', () => {
  writeResult(serviceName, exitCodes.ok, okOutput);
});

Then('{string} is Ok with the new output', (service: string) => {
  cy.waitForServiceStatus({
    condition: (row) =>
      toNumber(row.state) === exitCodes.ok &&
      firstLine(row.output) === okOutput,
    description: 'Ok with the new output',
    host: hostName,
    service
  });
});

When('a result is written for a service that does not exist', () => {
  cy.getEngineProcess().then(({ pid, running }) => {
    expect(running, 'Engine running').to.eq(true);

    enginePid = pid;
  });
  writeResult(missingService, exitCodes.critical, 'CRITICAL - rbt no service');
});

Then('Engine logs it and keeps running with the same PID', () => {
  cy.then(() =>
    cy.waitForEngineLog({
      fromLine: logMark,
      text: `Passive check result was received for service '${missingService}' on host '${hostName}', but the service could not be found!`
    })
  );

  cy.getEngineProcess().then(({ pid, running }) => {
    expect(running, 'Engine running').to.eq(true);
    expect(pid, 'Engine PID').to.eq(enginePid);

    cy.checkEngineProcessIsAlive(pid);
  });
});
