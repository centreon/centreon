import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { INTERCEPTORS } from 'fixtures/shared/constants/interceptors';

import {
  buildCheckProcessAgeCommand,
  exitCodes,
  hostCheckCommandName
} from '../common';

const checkCommand = 'rbt-timeout-check';
const secondsPerMinute = 60;
// Gap accepted between the planned and the measured check interval
const scheduleTolerance = 15;

let hostName = '';
let serviceCheckTimeout = 0;
let previousLastCheck = 0;

const toNumber = (value: string | number | null | undefined): number =>
  Number(value ?? 0);

beforeEach(() => {
  cy.startContainers();

  hostName = '';
  serviceCheckTimeout = 0;
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
  "the Service Check Timeout of the poller's Engine configuration is set to {int} seconds",
  (timeout: number) => {
    serviceCheckTimeout = timeout;

    cy.setEngineParameter({
      parameter: 'service_check_timeout',
      value: String(timeout)
    });
  }
);

Given(
  'a host {string} with two active services checked every minute, {string} whose check runs for {int} seconds and {string}',
  (host: string, slow: string, duration: number, fast: string) => {
    hostName = host;

    cy.installTestCheckCommands().addTestCheckCommand(checkCommand);
    cy.setTestCheckBehaviour({
      exitCode: exitCodes.ok,
      name: slow,
      sleepSeconds: duration
    });
    cy.setTestCheckBehaviour({ exitCode: exitCodes.ok, name: fast });
    cy.addHost({
      checkCommand: hostCheckCommandName,
      name: host,
      template: 'generic-host'
    });
    [slow, fast].forEach((service) => {
      cy.addService({
        activeCheckEnabled: true,
        checkCommand,
        host,
        maxCheckAttempts: 1,
        name: service,
        passiveCheckEnabled: false,
        template: 'generic-service'
      }).executeActionViaClapi({
        bodyContent: {
          action: 'SETPARAM',
          object: 'SERVICE',
          values: `${host};${service};normal_check_interval;1`
        }
      });
    });
  }
);

When('the configuration of the poller is exported and Engine restarted', () => {
  cy.exportConfigurationAndRestartEngine();
});

Then(
  'within 3 minutes {string} is not Ok and its status information reports a timeout',
  (service: string) => {
    cy.waitForServiceStatus({
      condition: (row) =>
        toNumber(row.checked) === 1 &&
        toNumber(row.state) !== exitCodes.ok &&
        /timeout/i.test(String(row.output ?? '')),
      description: 'not Ok with a timeout in its status information',
      host: hostName,
      service,
      timeout: 180000
    });
  }
);

Then(
  'its execution time is close to {int} seconds and no check process of {string} lives longer than {int} seconds',
  (expected: number, service: string, maxAge: number) => {
    expect(expected, 'configured service check timeout').to.eq(
      serviceCheckTimeout
    );

    cy.getServiceStatus(hostName, service).then((row) => {
      expect(
        toNumber(row?.execution_time),
        'execution time in seconds'
      ).to.be.within(expected - 2, expected + 3);
    });

    // Sample the check processes over more than one check interval
    cy.execInContainer({
      command: buildCheckProcessAgeCommand(service, 15, 5),
      name: 'web'
    }).then(({ output }) => {
      const [seen, oldest] = output.trim().split(/\s+/).map(Number);

      expect(seen, `check processes of ${service} sampled`).to.be.greaterThan(
        0
      );
      expect(oldest, `oldest check process of ${service}`).to.be.at.most(
        maxAge
      );
    });
  }
);

Then(
  '{string} is Ok and keeps being checked every minute',
  (service: string) => {
    cy.waitForServiceStatus({
      condition: (row) =>
        toNumber(row.checked) === 1 && toNumber(row.state) === exitCodes.ok,
      description: 'Ok',
      host: hostName,
      service
    }).then((first) => {
      const firstCheck = toNumber(first.last_check);

      cy.waitForServiceStatus({
        condition: (row) => toNumber(row.last_check) > firstCheck,
        description: 'checked again',
        host: hostName,
        service,
        timeout: 120000
      }).then((second) => {
        expect(toNumber(second.state), 'state').to.eq(exitCodes.ok);
        expect(
          toNumber(second.last_check) - firstCheck,
          'seconds between two checks'
        ).to.be.within(
          secondsPerMinute - scheduleTolerance,
          secondsPerMinute + scheduleTolerance
        );
      });
    });
  }
);

When('the check of {string} no longer sleeps', (service: string) => {
  cy.getServiceStatus(hostName, service).then((row) => {
    previousLastCheck = toNumber(row?.last_check);
  });
  cy.setTestCheckBehaviour({ exitCode: exitCodes.ok, name: service });
});

Then('{string} is Ok after its next check', (service: string) => {
  cy.waitForServiceStatus({
    condition: (row) =>
      toNumber(row.last_check) > previousLastCheck &&
      toNumber(row.state) === exitCodes.ok &&
      !/timeout/i.test(String(row.output ?? '')),
    description: 'Ok after its next check',
    host: hostName,
    service,
    timeout: 180000
  });
});
