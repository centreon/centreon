import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { INTERCEPTORS } from 'fixtures/shared/constants/interceptors';

import {
  exitCodes,
  hostCheckCommandName,
  processServiceCheckResult,
  testCheckScript
} from '../common';

const hostName = 'rbt-env-host';
const serviceName = 'rbt-env-svc';
const contactName = 'rbt-env-contact';
const notificationCommand = 'rbt-env-notify';
const criticalOutput = 'CRITICAL - rbt notified service';
const hostOutput = 'OK - rbt check of host returned 0';
const upStatus = 0;

let logMark = 0;
let enginePid = 0;
let hostLastCheck = 0;

const toNumber = (value: string | number | null | undefined): number =>
  Number(value ?? 0);

const clapi = (action: string, object: string, values: string) =>
  cy.executeActionViaClapi({ bodyContent: { action, object, values } });

beforeEach(() => {
  cy.startContainers();

  logMark = 0;
  enginePid = 0;
  hostLastCheck = 0;

  cy.intercept({
    method: 'GET',
    url: INTERCEPTORS.api.navigation_list
  }).as('getNavigationList');
});

afterEach(() => {
  cy.stopContainers();
});

Given('an administrator is logged in', () => {
  cy.loginByTypeOfUser({ jsonName: 'admin', loginViaApi: true });
});

Given("environment macros are enabled on the poller's Engine", () => {
  cy.setEngineParameter({
    parameter: 'enable_environment_macros',
    value: '1'
  });
});

Given(
  'a host is monitored with a service that has notifications enabled',
  () => {
    cy.installTestCheckCommands();

    // A notification command that always succeeds
    clapi('ADD', 'CMD', `${notificationCommand};notif;${testCheckScript}`);
    cy.addContact({
      admin: false,
      email: 'rbt-env-contact@localhost',
      enableNotifications: true,
      GUIAccess: false,
      name: contactName,
      password: 'Centreon!2021'
    });
    [
      ['hostnotifcmd', notificationCommand],
      ['svcnotifcmd', notificationCommand],
      ['hostnotifperiod', '24x7'],
      ['svcnotifperiod', '24x7'],
      ['hostnotifopt', 'd,u,r'],
      ['servicenotifopt', 'w,u,c,r']
    ].forEach(([parameter, value]) => {
      clapi('SETPARAM', 'CONTACT', `${contactName};${parameter};${value}`);
    });

    cy.addHost({
      checkCommand: hostCheckCommandName,
      name: hostName,
      template: 'generic-host'
    });
    clapi('SETPARAM', 'HOST', `${hostName};check_interval;1`);
    cy.addService({
      activeCheckEnabled: false,
      // Engine needs a check command, even for a passive service
      checkCommand: hostCheckCommandName,
      host: hostName,
      maxCheckAttempts: 1,
      name: serviceName,
      passiveCheckEnabled: true,
      template: 'generic-service'
    });
    [
      ['notifications_enabled', '1'],
      ['notification_options', 'w,u,c,r'],
      ['notification_period', '24x7'],
      ['notification_interval', '0']
    ].forEach(([parameter, value]) => {
      clapi(
        'SETPARAM',
        'SERVICE',
        `${hostName};${serviceName};${parameter};${value}`
      );
    });
    clapi('ADDCONTACT', 'SERVICE', `${hostName};${serviceName};${contactName}`);

    cy.applyPollerConfiguration();

    cy.waitForHostStatus({
      condition: (row) =>
        toNumber(row.checked) === 1 && toNumber(row.state) === upStatus,
      description: 'Up',
      host: hostName,
      timeout: 180000
    });
    cy.waitForServiceStatus({
      condition: (row) => toNumber(row.enabled) === 1,
      description: 'monitored',
      host: hostName,
      service: serviceName
    });
  }
);

Given(
  'the service is in a non-OK state and its notification has been sent',
  () => {
    cy.getEngineLogLineCount().then((count) => {
      logMark = count;
    });
    cy.writeEngineExternalCommand(
      processServiceCheckResult(
        hostName,
        serviceName,
        exitCodes.critical,
        criticalOutput
      )
    );
    cy.waitForServiceStatus({
      condition: (row) =>
        toNumber(row.state) === exitCodes.critical &&
        toNumber(row.state_type) === 1,
      description: 'Critical in a hard state',
      host: hostName,
      service: serviceName
    });
    cy.then(() =>
      cy.waitForEngineLog({
        fromLine: logMark,
        text: `SERVICE NOTIFICATION: ${contactName};${hostName};${serviceName};CRITICAL;${notificationCommand};`
      })
    );
  }
);

When(
  'the administrator removes the notified service from the configuration',
  () => {
    clapi('DEL', 'SERVICE', `${hostName};${serviceName}`);
  }
);

When(
  'the administrator exports the configuration and reloads the Engine',
  () => {
    cy.getEngineProcess().then(({ pid, running }) => {
      expect(running, 'Engine running').to.eq(true);

      enginePid = pid;
    });
    cy.getEngineLogLineCount().then((count) => {
      logMark = count;
    });

    cy.applyPollerConfiguration();

    cy.then(() =>
      cy.waitForEngineLog({
        fromLine: logMark,
        text: 'Configuration reloaded, main loop continuing.'
      })
    );
  }
);

When('the next active host check is executed', () => {
  cy.waitForHostStatus({
    condition: () => true,
    description: 'found',
    host: hostName
  }).then((row) => {
    hostLastCheck = toNumber(row.last_check);
  });

  cy.then(() =>
    cy.waitForHostStatus({
      condition: (row) => toNumber(row.last_check) > hostLastCheck,
      description: 'checked again after the reload',
      host: hostName,
      timeout: 150000
    })
  );
});

Then('the host check result is shown in the monitoring', () => {
  cy.then(() =>
    cy.waitForHostStatus({
      condition: (row) =>
        toNumber(row.resource_last_check) > hostLastCheck &&
        toNumber(row.resource_enabled) === 1,
      description: 'shown with its new check result',
      host: hostName,
      timeout: 60000
    })
  ).then((row) => {
    expect(toNumber(row.check_type), 'active check type').to.eq(0);
    expect(toNumber(row.resource_status), 'status').to.eq(upStatus);
    expect(String(row.resource_output ?? '').trim(), 'output').to.eq(
      hostOutput
    );
  });
});

Then('the Engine is still running', () => {
  cy.getEngineProcess().then(({ pid, running }) => {
    expect(running, 'Engine running').to.eq(true);
    expect(pid, 'Engine PID').to.eq(enginePid);

    cy.checkEngineProcessIsAlive(pid);
  });
});

Then('the Engine logs show no fatal error', () => {
  cy.then(() => cy.getEngineLogLines({ fromLine: logMark, text: '' })).then(
    (lines) => {
      // The reload itself is in the lines read, so they cannot be empty
      expect(lines.join('\n'), 'Engine log since the reload').to.contain(
        'Configuration reloaded, main loop continuing.'
      );

      const fatalLines = lines.filter((line) =>
        /\[critical\]|Caught SIG/.test(line)
      );

      expect(fatalLines, 'fatal lines since the reload').to.have.length(0);
    }
  );
});
