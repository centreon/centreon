import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { INTERCEPTORS } from 'fixtures/shared/constants/interceptors';

import {
  exitCodes,
  hostCheckCommandName,
  processServiceCheckResult
} from '../common';

const checkCommand = 'rbt-fresh-check';
const okOutput = 'OK - rbt passive result';
const passiveCheckType = 1;
const activeCheckType = 0;
// Margin kept before the freshness threshold expires
const thresholdMargin = 5;

let hostName = '';
let serviceName = '';
let freshnessThreshold = 0;
let logMark = 0;
let staleWaitTimeout = 0;
let okWrittenAt = 0;

const toNumber = (value: string | number | null | undefined): number =>
  Number(value ?? 0);

const staleWarning = (): string =>
  `Warning: The results of service '${serviceName}' on host '${hostName}' are stale by`;

const setServiceParameter = (parameter: string, value: string | number) =>
  cy.executeActionViaClapi({
    bodyContent: {
      action: 'SETPARAM',
      object: 'SERVICE',
      values: `${hostName};${serviceName};${parameter};${value}`
    }
  });

const writeOkResult = () => {
  cy.getEngineLogLineCount().then((count) => {
    logMark = count;
  });
  cy.then(() => {
    okWrittenAt = Date.now();
  });
  cy.writeEngineExternalCommand(
    processServiceCheckResult(hostName, serviceName, exitCodes.ok, okOutput)
  );
};

const waitForPassiveOk = (service: string) =>
  cy.waitForServiceStatus({
    condition: (row) =>
      toNumber(row.state) === exitCodes.ok &&
      toNumber(row.check_type) === passiveCheckType &&
      String(row.output ?? '').startsWith(okOutput),
    description: 'Ok from a passive check',
    host: hostName,
    service,
    timeout: 60000
  });

beforeEach(() => {
  cy.startContainers();

  hostName = '';
  serviceName = '';
  freshnessThreshold = 0;
  logMark = 0;
  staleWaitTimeout = 0;
  okWrittenAt = 0;

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
  "the service freshness check is enabled in the poller's Engine configuration with an interval of {int} seconds",
  (interval: number) => {
    cy.setEngineParameter({
      parameter: 'check_service_freshness',
      value: '1'
    }).setEngineParameter({
      parameter: 'service_freshness_check_interval',
      value: String(interval)
    });
  }
);

Given(
  'a host {string} with a passive service {string} whose freshness is checked with a threshold of {int} seconds',
  (host: string, service: string, threshold: number) => {
    hostName = host;
    serviceName = service;
    freshnessThreshold = threshold;

    cy.installTestCheckCommands();
    cy.addHost({
      checkCommand: hostCheckCommandName,
      name: host,
      template: 'generic-host'
    }).addService({
      activeCheckEnabled: false,
      // Engine needs a check command, even for a passive service
      checkCommand: hostCheckCommandName,
      host,
      maxCheckAttempts: 1,
      name: service,
      passiveCheckEnabled: true,
      template: 'generic-service'
    });
    setServiceParameter('check_freshness', 1);
    setServiceParameter('freshness_threshold', threshold);
  }
);

Given('the check command of {string} returns Unknown', (service: string) => {
  cy.addTestCheckCommand(checkCommand);
  setServiceParameter('check_command', checkCommand);
  cy.setTestCheckBehaviour({ exitCode: exitCodes.unknown, name: service });
});

When('the configuration of the poller is exported and Engine restarted', () => {
  cy.exportConfigurationAndRestartEngine();
  cy.waitForServiceStatus({
    condition: (row) => toNumber(row.enabled) === 1,
    description: 'monitored',
    host: hostName,
    service: serviceName,
    timeout: 60000
  });
});

When(
  'an Ok result is written to the Engine command file for {string}',
  (service: string) => {
    expect(service).to.eq(serviceName);

    writeOkResult();
  }
);

Then('{string} is Ok from a passive check', (service: string) => {
  waitForPassiveOk(service);
});

When('no result is received for {int} minutes', (minutes: number) => {
  staleWaitTimeout = minutes * 60000;
});

Then(
  'centengine.log reports the results of {string} as stale and forces a check',
  (service: string) => {
    expect(service).to.eq(serviceName);

    cy.then(() =>
      cy.waitForEngineLog({
        fromLine: logMark,
        text: staleWarning(),
        timeout: staleWaitTimeout
      })
    ).then((lines) => {
      expect(lines.join('\n'), 'stale warning').to.contain(
        "I'm forcing an immediate check of the service."
      );
    });
  }
);

Then('{string} is Unknown from an active check', (service: string) => {
  cy.waitForServiceStatus({
    condition: (row) =>
      toNumber(row.state) === exitCodes.unknown &&
      toNumber(row.check_type) === activeCheckType &&
      String(row.output ?? '').startsWith(`UNKNOWN - rbt check of ${service}`),
    description: 'Unknown from an active check',
    host: hostName,
    service,
    timeout: 60000
  });
});

When('an Ok result is written again', () => {
  writeOkResult();
});

Then(
  '{string} is Ok and no new stale warning is logged within its threshold',
  (service: string) => {
    waitForPassiveOk(service);

    // Stay until the threshold is almost over, then look for a stale warning
    cy.then(() => {
      const remaining =
        okWrittenAt +
        (freshnessThreshold - thresholdMargin) * 1000 -
        Date.now();

      cy.wait(Math.max(remaining, 0));
    });

    cy.then(() =>
      cy.getEngineLogLines({ fromLine: logMark, text: staleWarning() })
    ).then((lines) => {
      expect(lines, 'stale warnings since the Ok result').to.have.length(0);
    });

    waitForPassiveOk(service);
  }
);
