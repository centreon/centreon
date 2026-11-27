import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { INTERCEPTORS } from 'fixtures/shared/constants/interceptors';

import { exitCodes, hostCheckCommandName } from '../common';

type StatusRow = Record<string, string | number | null>;

const secondsPerMinute = 60;
// Gap accepted between the planned and the measured check interval
const scheduleTolerance = 15;

let checkCommand = '';
let hostName = '';
let serviceName = '';
let previousLastCheck = 0;

const toNumber = (value: string | number | null | undefined): number =>
  Number(value ?? 0);

const expectCheckedEvery = (row: StatusRow, minutes: number): void => {
  const interval = toNumber(row.next_check) - toNumber(row.last_check);

  expect(interval, 'seconds until the next check').to.be.within(
    minutes * secondsPerMinute - scheduleTolerance,
    minutes * secondsPerMinute + scheduleTolerance
  );
};

const setServiceParameter = (parameter: string, value: string | number) =>
  cy.executeActionViaClapi({
    bodyContent: {
      action: 'SETPARAM',
      object: 'SERVICE',
      values: `${hostName};${serviceName};${parameter};${value}`
    }
  });

beforeEach(() => {
  cy.startContainers();

  checkCommand = '';
  hostName = '';
  serviceName = '';
  previousLastCheck = 0;

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
  'the test check command {string} returns the exit code written for each service',
  (command: string) => {
    checkCommand = command;

    cy.installTestCheckCommands().addTestCheckCommand(command);
  }
);

Given(
  'a host {string} with an active service {string} checked every {int} minutes, retried every minute, with {int} max check attempts',
  (host: string, service: string, interval: number, attempts: number) => {
    hostName = host;
    serviceName = service;

    cy.setTestCheckBehaviour({ exitCode: exitCodes.ok, name: service });
    cy.addHost({
      checkCommand: hostCheckCommandName,
      name: host,
      template: 'generic-host'
    }).addService({
      activeCheckEnabled: true,
      checkCommand,
      host,
      maxCheckAttempts: attempts,
      name: service,
      passiveCheckEnabled: false,
      template: 'generic-service'
    });
    setServiceParameter('normal_check_interval', interval);
    setServiceParameter('retry_check_interval', 1);
  }
);

When('the configuration of the poller is exported and reloaded', () => {
  cy.applyPollerConfiguration();
});

Then(
  '{string} is Ok in a hard state and is checked every {int} minutes',
  (service: string, interval: number) => {
    cy.waitForServiceStatus({
      condition: (row) =>
        toNumber(row.checked) === 1 &&
        toNumber(row.state) === exitCodes.ok &&
        toNumber(row.state_type) === 1 &&
        toNumber(row.next_check) > toNumber(row.last_check),
      description: 'Ok in a hard state',
      host: hostName,
      service,
      timeout: 300000
    }).then((row) => {
      expectCheckedEvery(row, interval);
    });
  }
);

When('the check of {string} starts returning Critical', (service: string) => {
  cy.getServiceStatus(hostName, service).then((row) => {
    previousLastCheck = toNumber(row?.last_check);
  });
  cy.setTestCheckBehaviour({ exitCode: exitCodes.critical, name: service });
});

Then(
  'the service is Critical in a soft state at attempt 1 and is retried every minute',
  () => {
    cy.waitForServiceStatus({
      condition: (row) =>
        toNumber(row.last_check) > previousLastCheck &&
        toNumber(row.state) === exitCodes.critical &&
        toNumber(row.state_type) === 0 &&
        toNumber(row.check_attempt) === 1,
      description: 'Critical in a soft state at attempt 1',
      host: hostName,
      service: serviceName,
      timeout: 180000
    }).then((row) => {
      expectCheckedEvery(row, 1);
    });
  }
);

Then(
  'after its third attempt the service is Critical in a hard state and is checked every 2 minutes again',
  () => {
    cy.waitForServiceStatus({
      condition: (row) =>
        toNumber(row.state) === exitCodes.critical &&
        toNumber(row.state_type) === 1 &&
        toNumber(row.check_attempt) === 3,
      description: 'Critical in a hard state at attempt 3',
      host: hostName,
      service: serviceName,
      timeout: 180000
    }).then((row) => {
      expectCheckedEvery(row, 2);
    });
  }
);

When('the check returns Ok again', () => {
  cy.getServiceStatus(hostName, serviceName).then((row) => {
    previousLastCheck = toNumber(row?.last_check);
  });
  cy.setTestCheckBehaviour({ exitCode: exitCodes.ok, name: serviceName });
});

Then('the service is Ok in a hard state after its next check', () => {
  cy.waitForServiceStatus({
    condition: (row) =>
      toNumber(row.last_check) > previousLastCheck &&
      toNumber(row.state) === exitCodes.ok &&
      toNumber(row.state_type) === 1,
    description: 'Ok in a hard state after its next check',
    host: hostName,
    service: serviceName,
    timeout: 180000
  });
});
