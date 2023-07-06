/* eslint-disable @typescript-eslint/no-namespace */

const apiBase = '/centreon/api';
const apiActionV1 = `${apiBase}/index.php`;

interface ActionClapi {
  action: string;
  object?: string;
  values: string;
}

interface ExecuteActionViaClapiProps {
  bodyContent: ActionClapi;
  method?: string;
}

Cypress.Commands.add(
  'executeActionViaClapi',
  ({
    bodyContent,
    method = 'POST'
  }: ExecuteActionViaClapiProps): Cypress.Chainable => {
    return cy.request({
      body: bodyContent,
      headers: {
        'Content-Type': 'application/json',
        'centreon-auth-token': window.localStorage.getItem('userTokenApiV1')
      },
      method,
      url: `${apiActionV1}?action=action&object=centreon_clapi`
    });
  }
);

Cypress.Commands.add(
  'executeCommandsViaClapi',
  (fixtureFile: string): Cypress.Chainable => {
    return cy.fixture(fixtureFile).then((listRequestConfig) => {
      cy.wrap(
        Promise.all(
          listRequestConfig.map((request: ActionClapi) =>
            cy.executeActionViaClapi({ bodyContent: request })
          )
        )
      );
    });
  }
);

interface TimePeriod {
  alias?: string | null;
  friday?: string;
  monday?: string;
  name: string;
  saturday?: string;
  sunday?: string;
  thursday?: string;
  tuesday?: string;
  wednesday?: string;
}

const defaultDayPeriod = '00:00-24:00';

Cypress.Commands.add(
  'addTimePeriod',
  ({
    alias = null,
    friday = defaultDayPeriod,
    monday = defaultDayPeriod,
    name,
    saturday = defaultDayPeriod,
    sunday = defaultDayPeriod,
    thursday = defaultDayPeriod,
    tuesday = defaultDayPeriod,
    wednesday = defaultDayPeriod
  }: TimePeriod): Cypress.Chainable => {
    const timePeriodAlias = alias === null ? name : alias;

    return cy
      .executeActionViaClapi({
        bodyContent: {
          action: 'ADD',
          object: 'TP',
          values: `${name};${timePeriodAlias}`
        }
      })
      .then(() => {
        const weekDays = {
          friday,
          monday,
          saturday,
          sunday,
          thursday,
          tuesday,
          wednesday
        };
        Object.entries(weekDays).map(([dayName, dayValue]) => {
          return cy.executeActionViaClapi({
            bodyContent: {
              action: 'SETPARAM',
              object: 'TP',
              values: `${name};${dayName};${dayValue}`
            }
          });
        });

        return cy.wrap(null);
      });
  }
);

interface CheckCommand {
  command: string;
  enableShell?: boolean;
  name: string;
}

Cypress.Commands.add(
  'addCheckCommand',
  ({ name, enableShell = true, command }: CheckCommand): Cypress.Chainable => {
    const commandEnableShell = enableShell ? 1 : 0;

    return cy
      .executeActionViaClapi({
        bodyContent: {
          action: 'ADD',
          object: 'CMD',
          values: `${name};check;${command}`
        }
      })
      .executeActionViaClapi({
        bodyContent: {
          action: 'SETPARAM',
          object: 'CMD',
          values: `${name};enable_shell;${commandEnableShell}`
        }
      });
  }
);

interface Host {
  activeCheckEnabled?: boolean;
  address?: string;
  alias?: string | null;
  checkCommand?: string | null;
  checkPeriod?: string | null;
  maxCheckAttempts?: number | null;
  name: string;
  passiveCheckEnabled?: boolean;
  poller?: string;
  template?: string;
}

Cypress.Commands.add(
  'addHost',
  ({
    activeCheckEnabled = true,
    address = '127.0.0.1',
    alias = null,
    checkCommand = null,
    checkPeriod = null,
    maxCheckAttempts = 1,
    name,
    passiveCheckEnabled = true,
    poller = 'Central',
    template = ''
  }: Host): Cypress.Chainable => {
    const hostAlias = alias === null ? name : alias;
    const hostMaxCheckAttempts =
      maxCheckAttempts === null ? '' : maxCheckAttempts;
    const hostActiveCheckEnabled = activeCheckEnabled ? 1 : 0;
    const hostPassiveCheckEnabled = passiveCheckEnabled ? 1 : 0;

    return cy
      .executeActionViaClapi({
        bodyContent: {
          action: 'ADD',
          object: 'HOST',
          values: `${name};${hostAlias};${address};${template};${poller};`
        }
      })
      .then(() => {
        const hostParams = {
          active_checks_enabled: hostActiveCheckEnabled,
          check_command: checkCommand,
          check_period: checkPeriod,
          max_check_attempts: hostMaxCheckAttempts,
          passive_checks_enabled: hostPassiveCheckEnabled
        };
        Object.entries(hostParams).map(([paramName, paramValue]) => {
          if (paramValue === null) {
            return null;
          }

          return cy.executeActionViaClapi({
            bodyContent: {
              action: 'SETPARAM',
              object: 'HOST',
              values: `${name};${paramName};${paramValue}`
            }
          });
        });

        return cy.wrap(null);
      });
  }
);

interface ServiceTemplate {
  activeCheckEnabled?: boolean;
  checkCommand?: string | null;
  checkPeriod?: string | null;
  description?: string | null;
  maxCheckAttempts?: number | null;
  name: string;
  passiveCheckEnabled?: boolean;
  template?: string;
}

Cypress.Commands.add(
  'addServiceTemplate',
  ({
    activeCheckEnabled = true,
    checkCommand = null,
    checkPeriod = null,
    description = null,
    maxCheckAttempts = 1,
    name,
    passiveCheckEnabled = true,
    template = ''
  }: ServiceTemplate): Cypress.Chainable => {
    const serviceDescription = description === null ? name : description;
    const serviceMaxCheckAttempts =
      maxCheckAttempts === null ? '' : maxCheckAttempts;
    const serviceActiveCheckEnabled = activeCheckEnabled ? 1 : 0;
    const servicePassiveCheckEnabled = passiveCheckEnabled ? 1 : 0;

    return cy
      .executeActionViaClapi({
        bodyContent: {
          action: 'ADD',
          object: 'STPL',
          values: `${name};${description};${template}`
        }
      })
      .then(() => {
        const serviceParams = {
          active_checks_enabled: serviceActiveCheckEnabled,
          check_command: checkCommand,
          check_period: checkPeriod,
          description: serviceDescription,
          max_check_attempts: serviceMaxCheckAttempts,
          passive_checks_enabled: servicePassiveCheckEnabled
        };
        Object.entries(serviceParams).map(([paramName, paramValue]) => {
          if (paramValue === null) {
            return null;
          }

          return cy.executeActionViaClapi({
            bodyContent: {
              action: 'SETPARAM',
              object: 'STPL',
              values: `${name};${paramName};${paramValue}`
            }
          });
        });

        return cy.wrap(null);
      });
  }
);

interface Service extends ServiceTemplate {
  host: string;
}

Cypress.Commands.add(
  'addService',
  ({
    activeCheckEnabled = true,
    checkCommand = null,
    checkPeriod = null,
    host,
    maxCheckAttempts = 1,
    name,
    passiveCheckEnabled = true,
    template = ''
  }: Service): Cypress.Chainable => {
    const serviceMaxCheckAttempts =
      maxCheckAttempts === null ? '' : maxCheckAttempts;
    const serviceActiveCheckEnabled = activeCheckEnabled ? 1 : 0;
    const servicePassiveCheckEnabled = passiveCheckEnabled ? 1 : 0;

    return cy
      .executeActionViaClapi({
        bodyContent: {
          action: 'ADD',
          object: 'SERVICE',
          values: `${host};${name};${template}`
        }
      })
      .then(() => {
        const serviceParams = {
          active_checks_enabled: serviceActiveCheckEnabled,
          check_command: checkCommand,
          check_period: checkPeriod,
          max_check_attempts: serviceMaxCheckAttempts,
          passive_checks_enabled: servicePassiveCheckEnabled
        };
        Object.entries(serviceParams).map(([paramName, paramValue]) => {
          if (paramValue === null) {
            return null;
          }

          return cy.executeActionViaClapi({
            bodyContent: {
              action: 'SETPARAM',
              object: 'SERVICE',
              values: `${host};${name};${paramName};${paramValue}`
            }
          });
        });

        return cy.wrap(null);
      });
  }
);

Cypress.Commands.add(
  'applyPollerConfiguration',
  (pollerName = 'Central'): Cypress.Chainable => {
    return cy.executeActionViaClapi({
      bodyContent: {
        action: 'APPLYCFG',
        values: pollerName
      }
    });
  }
);

declare global {
  namespace Cypress {
    interface Chainable {
      addCheckCommand: (props: CheckCommand) => Cypress.Chainable;
      addHost: (props: Host) => Cypress.Chainable;
      addService: (props: Service) => Cypress.Chainable;
      addServiceTemplate: (props: ServiceTemplate) => Cypress.Chainable;
      addTimePeriod: (props: TimePeriod) => Cypress.Chainable;
      applyPollerConfiguration: (props?: string) => Cypress.Chainable;
      executeActionViaClapi: (
        props: ExecuteActionViaClapiProps
      ) => Cypress.Chainable;
      executeCommandsViaClapi: (fixtureFile: string) => Cypress.Chainable;
    }
  }
}

export {};
