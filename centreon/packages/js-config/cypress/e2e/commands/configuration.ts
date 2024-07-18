/* eslint-disable @typescript-eslint/no-namespace */

import { Action } from 'e2e/features/ACLs/commands';

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

interface Contact {
  GUIAccess?: boolean;
  admin?: boolean;
  alias?: string | null;
  authenticationType?: 'local' | 'ldap';
  email: string;
  enableNotifications?: boolean;
  language?: string;
  name: string;
  password: string;
}

Cypress.Commands.add(
  'addContact',
  ({
    admin = true,
    alias = null,
    authenticationType = 'local',
    email,
    enableNotifications = true,
    GUIAccess = true,
    language = 'en_US',
    name,
    password
  }: Contact): Cypress.Chainable => {
    const contactAdmin = admin ? 1 : 0;
    const contactAlias = alias === null ? name : alias;
    const contactEnableNotifications = enableNotifications ? 1 : 0;
    const contactGUIAccess = GUIAccess ? 1 : 0;

    return cy
      .executeActionViaClapi({
        bodyContent: {
          action: 'ADD',
          object: 'CONTACT',
          values: `${name};${contactAlias};${email};${password};${contactAdmin};${contactGUIAccess};${language};${authenticationType}`
        }
      })
      .then(() => {
        const contactParams = {
          enable_notifications: contactEnableNotifications
        };
        Object.entries(contactParams).map(([paramName, paramValue]) => {
          if (paramValue === null) {
            return null;
          }

          return cy.executeActionViaClapi({
            bodyContent: {
              action: 'SETPARAM',
              object: 'CONTACT',
              values: `${name};${paramName};${paramValue}`
            }
          });
        });

        return cy.wrap(null);
      });
  }
);

interface ContactGroup {
  alias?: string | null;
  contacts: Array<string>;
  name: string;
}

Cypress.Commands.add(
  'addContactGroup',
  ({ alias = null, contacts, name }: ContactGroup): Cypress.Chainable => {
    const contactGroupAlias = alias === null ? name : alias;

    return cy
      .executeActionViaClapi({
        bodyContent: {
          action: 'ADD',
          object: 'CG',
          values: `${name};${contactGroupAlias}`
        }
      })
      .then(() => {
        contacts.map((contact) => {
          return cy.executeActionViaClapi({
            bodyContent: {
              action: 'ADDCONTACT',
              object: 'CG',
              values: `${name};${contact}`
            }
          });
        });

        return cy.wrap(null);
      });
  }
);

interface Host {
  activeCheckEnabled?: boolean;
  address?: string;
  alias?: string | null;
  checkCommand?: string | null;
  checkPeriod?: string | null;
  hostGroup?: string;
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
    hostGroup = '',
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
          values: `${name};${hostAlias};${address};${template};${poller};${hostGroup}`
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

interface HostGroup {
  alias?: string | null;
  name: string;
}

Cypress.Commands.add(
  'addHostGroup',
  ({ alias = null, name }: HostGroup): Cypress.Chainable => {
    const hostGroupAlias = alias === null ? name : alias;

    return cy.executeActionViaClapi({
      bodyContent: {
        action: 'ADD',
        object: 'HG',
        values: `${name};${hostGroupAlias}`
      }
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

interface ServiceGroup {
  alias?: string | null;
  hostsAndServices: Array<Array<string>>;
  name: string;
}

Cypress.Commands.add(
  'addServiceGroup',
  ({
    alias = null,
    hostsAndServices,
    name
  }: ServiceGroup): Cypress.Chainable => {
    const serviceGroupAlias = alias === null ? name : alias;

    return cy
      .executeActionViaClapi({
        bodyContent: {
          action: 'ADD',
          object: 'SG',
          values: `${name};${serviceGroupAlias}`
        }
      })
      .then(() => {
        hostsAndServices.map((hostAndService) => {
          return cy.executeActionViaClapi({
            bodyContent: {
              action: 'ADDSERVICE',
              object: 'SG',
              values: `${name};${hostAndService[0]},${hostAndService[1]}`
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

interface ACLGroup {
  alias?: string | null;
  contactGroups?: Array<string>;
  contacts?: Array<string>;
  name: string;
}

Cypress.Commands.add(
  'addACLGroup',
  ({
    alias = null,
    contacts = [],
    contactGroups = [],
    name
  }: ACLGroup): Cypress.Chainable => {
    const ACLGroupALias = alias === null ? name : alias;

    return cy
      .executeActionViaClapi({
        bodyContent: {
          action: 'ADD',
          object: 'ACLGROUP',
          values: `${name};${ACLGroupALias}`
        }
      })
      .then(() => {
        if (contacts) {
          contacts.forEach((contact) => {
            cy.executeActionViaClapi({
              bodyContent: {
                action: 'ADDCONTACT',
                object: 'ACLGROUP',
                values: `${name};${contact}`
              }
            });
          });
        }
        if (contactGroups) {
          contactGroups.forEach((contactGroup) => {
            cy.executeActionViaClapi({
              bodyContent: {
                action: 'ADDCONTACTGROUP',
                object: 'ACLGROUP',
                values: `${name};${contactGroup}`
              }
            });
          });
        }
      });
  }
);

interface ACLMenu {
  alias?: string | null;
  includeChildren?: boolean;
  name: string;
  readOnly?: boolean;
  rule?: Array<string>;
}

Cypress.Commands.add(
  'addACLMenu',
  ({
    name,
    rule = [],
    alias = null,
    includeChildren = true,
    readOnly = false
  }: ACLMenu): Cypress.Chainable => {
    const ACLMenuAlias = alias === null ? name : alias;
    const action = readOnly ? 'GRANTRO' : 'GRANTRW';
    const children = includeChildren ? '1' : '0';

    return cy
      .executeActionViaClapi({
        bodyContent: {
          action: 'ADD',
          object: 'ACLMENU',
          values: `${name};${ACLMenuAlias}`
        }
      })
      .then(() => {
        if (rule.length === 0) {
          return cy.wrap(null);
        }

        let ruleCommand = '';
        rule.forEach((rulePage, index) => {
          ruleCommand += rulePage + (index == rule.length - 1 ? '' : ';');
        });
        cy.executeActionViaClapi({
          bodyContent: {
            action,
            object: 'ACLMENU',
            values: `${name};${children};${ruleCommand}`
          }
        });

        return cy.wrap(null);
      });
  }
);

interface ACLAction {
  actions?: Array<Action>;
  description: string;
  name: string;
}

Cypress.Commands.add(
  'addACLAction',
  ({ name, description, actions = [] }: ACLAction): Cypress.Chainable => {
    return cy
      .executeActionViaClapi({
        bodyContent: {
          action: 'ADD',
          object: 'ACLACTION',
          values: `${name};${description}`
        }
      })
      .then(() => {
        if (actions.length === 0) {
          return cy.wrap(null);
        }

        let actionCommand = '';
        actions.forEach((action, index) => {
          actionCommand += action + (index == actions.length - 1 ? '' : '|');
        });
        cy.executeActionViaClapi({
          bodyContent: {
            action: 'GRANT',
            object: 'ACLACTION',
            values: `${name};${actionCommand}`
          }
        });

        return cy.wrap(null);
      });
  }
);

interface ACLResource {
  alias?: string | null;
  name: string;
}

Cypress.Commands.add(
  'addACLResource',
  ({ name, alias = null }: ACLResource): Cypress.Chainable => {
    const ACLResourcesAlias = alias === null ? name : alias;

    return cy.executeActionViaClapi({
      bodyContent: {
        action: 'ADD',
        object: 'ACLRESOURCE',
        values: `${name};${ACLResourcesAlias}`
      }
    });
  }
);

declare global {
  namespace Cypress {
    interface Chainable {
      addACLAction: (props: ACLAction) => Cypress.Chainable;
      addACLGroup: (props: ACLGroup) => Cypress.Chainable;
      addACLMenu: (props: ACLMenu) => Cypress.Chainable;
      addACLResource: (props: ACLResource) => Cypress.Chainable;
      addCheckCommand: (props: CheckCommand) => Cypress.Chainable;
      addContact: (props: Contact) => Cypress.Chainable;
      addContactGroup: (props: ContactGroup) => Cypress.Chainable;
      addHost: (props: Host) => Cypress.Chainable;
      addHostGroup: (props: HostGroup) => Cypress.Chainable;
      addService: (props: Service) => Cypress.Chainable;
      addServiceGroup: (props: ServiceGroup) => Cypress.Chainable;
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
