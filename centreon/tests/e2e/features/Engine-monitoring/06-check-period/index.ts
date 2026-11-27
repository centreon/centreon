import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { INTERCEPTORS } from 'fixtures/shared/constants/interceptors';

import {
  engineConfigurationDirectory,
  exitCodes,
  hostCheckCommandName,
  parseCfgObjects,
  weekDays
} from '../common';

const checkCommand = 'rbt-tp-check';
const pendingStatus = 4;
// One check interval, so that a service on an open period would be checked
const checkIntervalWait = 70000;

let hostName = '';
let closedService = '';

const toNumber = (value: string | number | null | undefined): number =>
  Number(value ?? 0);

beforeEach(() => {
  cy.startContainers();

  hostName = '';
  closedService = '';

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
  'a time period {string} with no time range on any day',
  (timePeriod: string) => {
    cy.executeActionViaClapi({
      bodyContent: {
        action: 'ADD',
        object: 'TP',
        values: `${timePeriod};${timePeriod}`
      }
    });
  }
);

Given(
  'a host {string} with two services checked every minute, {string} on the check period {string} and {string} on the check period {string}',
  (
    host: string,
    openService: string,
    openPeriod: string,
    service: string,
    closedPeriod: string
  ) => {
    hostName = host;
    closedService = service;

    cy.installTestCheckCommands().addTestCheckCommand(checkCommand);
    cy.addHost({
      checkCommand: hostCheckCommandName,
      name: host,
      template: 'generic-host'
    });
    [
      { name: openService, period: openPeriod },
      { name: service, period: closedPeriod }
    ].forEach(({ name, period }) => {
      cy.setTestCheckBehaviour({ exitCode: exitCodes.ok, name });
      cy.addService({
        activeCheckEnabled: true,
        checkCommand,
        checkPeriod: period,
        host,
        maxCheckAttempts: 1,
        name,
        passiveCheckEnabled: false,
        template: 'generic-service'
      }).executeActionViaClapi({
        bodyContent: {
          action: 'SETPARAM',
          object: 'SERVICE',
          values: `${host};${name};normal_check_interval;1`
        }
      });
    });
  }
);

When('the configuration of the Central is exported and reloaded', () => {
  cy.applyPollerConfiguration();
});

Then(
  'timeperiods.cfg contains {string} without any day line and services.cfg sets the check_period of {string} on it',
  (timePeriod: string, service: string) => {
    cy.execInContainer({
      command: `cat ${engineConfigurationDirectory}/timeperiods.cfg`,
      name: 'web'
    }).then(({ output }) => {
      const definition = parseCfgObjects(output, 'timeperiod').find(
        (object) => object.timeperiod_name === timePeriod
      );

      expect(definition, `time period ${timePeriod}`).to.not.eq(undefined);
      weekDays.forEach((day) => {
        expect(definition, `${day} of ${timePeriod}`).to.not.have.property(day);
      });
    });

    cy.execInContainer({
      command: `cat ${engineConfigurationDirectory}/services.cfg`,
      name: 'web'
    }).then(({ output }) => {
      const definition = parseCfgObjects(output, 'service').find(
        (object) =>
          object.service_description === service &&
          object.host_name === hostName
      );

      expect(definition, `service ${service}`).to.not.eq(undefined);
      expect(definition?.check_period, 'check_period').to.eq(timePeriod);
    });
  }
);

Then(
  'within 3 minutes {string} is Ok with a last check and {string} is still Pending without a last check',
  (openService: string, service: string) => {
    cy.waitForServiceStatus({
      condition: (row) =>
        toNumber(row.checked) === 1 &&
        toNumber(row.state) === exitCodes.ok &&
        toNumber(row.last_check) > 0,
      description: 'Ok with a last check',
      host: hostName,
      service: openService,
      timeout: 180000
    });

    // Leave one more check interval to the closed service before looking
    cy.wait(checkIntervalWait);

    cy.getServiceStatus(hostName, service).then((row) => {
      expect(row, `status of ${service}`).to.not.eq(null);
      expect(toNumber(row?.enabled), 'monitored').to.eq(1);
      expect(toNumber(row?.checked), 'checked').to.eq(0);
      expect(toNumber(row?.last_check), 'last check').to.eq(0);
      expect(toNumber(row?.resource_status), 'status').to.eq(pendingStatus);
    });
  }
);

When(
  'the time period {string} is given the range 00:00-24:00 on every day and the configuration is exported again',
  (timePeriod: string) => {
    weekDays.forEach((day) => {
      cy.executeActionViaClapi({
        bodyContent: {
          action: 'SETPARAM',
          object: 'TP',
          values: `${timePeriod};${day};00:00-24:00`
        }
      });
    });
    cy.applyPollerConfiguration();
  }
);

Then(
  'within 2 minutes {string} is checked and leaves the Pending state',
  (service: string) => {
    expect(service).to.eq(closedService);

    cy.waitForServiceStatus({
      condition: (row) =>
        toNumber(row.checked) === 1 &&
        toNumber(row.last_check) > 0 &&
        toNumber(row.resource_status) !== pendingStatus,
      description: 'checked',
      host: hostName,
      service,
      timeout: 120000
    });
  }
);
