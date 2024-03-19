import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';

import {
  checkHostsAreMonitored,
  checkServicesAreMonitored
} from '../../../commons';
import {
  enableNotificationFeature,
  notificationSentCheck,
  setBrokerNotificationsOutput,
  waitUntilLogFileChange
} from '../common';
import data from '../../../fixtures/notifications/data-for-notification.json';

let globalResourceType = '';
let globalContactSettings = '';

beforeEach(() => {
  cy.startContainers();
  enableNotificationFeature();
  setBrokerNotificationsOutput({
    configName: 'central-broker-master',
    name: 'central-cloud-notifications-output'
  });

  cy.intercept({
    method: 'GET',
    url: '/centreon/api/internal.php?object=centreon_topology&action=navigationList'
  }).as('getNavigationList');
  cy.loginByTypeOfUser({ jsonName: 'admin' });
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/latest/configuration/notifications?page=1&limit=10*'
  }).as('getNotifications');
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/latest/configuration/users?page=1*'
  }).as('getUsers');

  globalResourceType = '';
  globalContactSettings = '';
});

afterEach(() => {
  cy.stopContainers();
});

Given('a user with access to the Notification Rules page', () => {
  cy.navigateTo({
    page: 'Notifications',
    rootItemNumber: 3,
    subMenu: 'Notifications'
  });
});

Given(
  'a {string} with hosts and {string}',
  (resourceType: string, contactSettings: string) => {
    globalResourceType = resourceType;
    globalContactSettings = contactSettings;

    switch (contactSettings) {
      case 'a single contact':
        cy.addContact({
          email: data.contacts.contact1.email,
          name: data.contacts.contact1.name,
          password: data.contacts.contact1.password
        });
        break;
      case 'two contacts':
        cy.addContact({
          email: data.contacts.contact1.email,
          name: data.contacts.contact1.name,
          password: data.contacts.contact1.password
        });
        cy.addContact({
          email: data.contacts.contact2.email,
          name: data.contacts.contact2.name,
          password: data.contacts.contact2.password
        });
        break;
      default:
        throw new Error(`${contactSettings} not managed`);
    }

    cy.addHostGroup({
      name: data.hostGroups.hostGroup1.name
    });

    cy.addHost({
      activeCheckEnabled: false,
      checkCommand: 'check_centreon_cpu',
      hostGroup: data.hostGroups.hostGroup1.name,
      name: data.hosts.host1.name,
      template: 'generic-host'
    })
      .addService({
        activeCheckEnabled: false,
        host: data.hosts.host1.name,
        maxCheckAttempts: 1,
        name: data.services.service1.name,
        template: 'Ping-LAN'
      })
      .applyPollerConfiguration();

    cy.addHost({
      activeCheckEnabled: false,
      checkCommand: 'check_centreon_cpu',
      hostGroup: data.hostGroups.hostGroup1.name,
      name: data.hosts.host2.name,
      template: 'generic-host'
    })
      .addService({
        activeCheckEnabled: false,
        host: data.hosts.host2.name,
        maxCheckAttempts: 1,
        name: data.services.service2.name,
        template: 'Ping-LAN'
      })
      .applyPollerConfiguration();

    checkHostsAreMonitored([
      {
        name: data.hosts.host1.name
      },
      {
        name: data.hosts.host2.name
      }
    ]);

    checkServicesAreMonitored([
      {
        name: data.services.service1.name
      },
      {
        name: data.services.service2.name
      }
    ]);
  }
);

When('the user defines a name for the rule', () => {
  cy.contains('Add').click();
  const notificationName = globalResourceType
    ? `Notification for ${globalResourceType} and ${globalContactSettings}`
    : `Notification for 1000 services`;
  cy.get('#Notificationname').type(notificationName);
});

When(
  'the user selects a {string} with associated events on which to notify',
  (resourceType: string) => {
    switch (resourceType) {
      case 'host group':
        cy.get('#Searchhostgroups').click();
        cy.contains(data.hostGroups.hostGroup1.name).click();
        cy.get('#Recovery').click();
        cy.get('#Down').click();
        cy.get('#Unreachable').click();
        break;
      case 'host group and services for these hosts':
        cy.get('#Searchhostgroups').click();
        cy.contains(data.hostGroups.hostGroup1.name).click();
        cy.get('#Searchhostgroups').blur();
        cy.contains('Include services for these hosts').click();
        cy.get('[data-testid="Extra events services"] >').each(($el) => {
          cy.wrap($el).click();
        });
        break;
      default:
        throw new Error(`${resourceType} not managed`);
    }
  }
);

When('the user selects the {string}', (contactSettings: string) => {
  switch (contactSettings) {
    case 'a single contact':
      cy.get('#Searchcontacts').click();
      cy.wait('@getUsers');
      cy.contains(data.contacts.contact1.name).click();
      break;
    case 'two contacts':
      cy.get('#Searchcontacts').click();
      cy.wait('@getUsers');
      cy.contains(data.contacts.contact1.name).click();
      cy.contains(data.contacts.contact2.name).click();
      break;
    default:
      throw new Error(`${contactSettings} not managed`);
  }
});

When('the user defines a mail subject', () => {
  const subject = globalResourceType
    ? `{selectAll}{backspace}Subject notification for ${globalResourceType} and ${globalContactSettings}`
    : `Subject notification for 1000 services`;
  cy.getByLabel({ label: 'Subject' }).clear().type(subject);
});

When('the user defines a mail body', () => {
  const body = globalResourceType
    ? `{selectAll}{backspace}Body notification for ${globalResourceType} and ${globalContactSettings}`
    : `{selectAll}{backspace}Body notification for 1000 services`;
  cy.getByLabel({ label: 'EmailBody' }).clear().type(body);
});

When('the user clicks on the "Save" button to confirm', () => {
  cy.get('#Save').should('be.enabled').click();
});

Then(
  'a success message is displayed and the created Notification Rule is displayed in the listing',
  () => {
    cy.get('.MuiAlert-message').should(
      'have.text',
      'The notification was successfully added'
    );

    cy.wait('@getNotifications');

    const notificationName = globalResourceType
      ? `Notification for ${globalResourceType} and ${globalContactSettings}`
      : `Notification for 1000 services`;
    cy.contains(notificationName).should('exist');
  }
);

When(
  'changes occur in the configured statuses for the selected {string}',
  (resourceType) => {
    switch (resourceType) {
      case 'host group':
        cy.submitResults([
          {
            host: data.hosts.host2.name,
            output: 'submit_status_1',
            status: 'down'
          }
        ]);

        checkHostsAreMonitored([
          {
            name: data.hosts.host2.name,
            status: 'down'
          }
        ]);
        break;
      case 'host group and services for these hosts':
        cy.submitResults([
          {
            host: data.hosts.host1.name,
            output: 'submit_status_2',
            service: data.services.service1.name,
            status: 'critical'
          }
        ]);

        checkServicesAreMonitored([
          {
            name: data.services.service1.name,
            status: 'critical'
          }
        ]);
        break;
      default:
        throw new Error(`${resourceType} not managed`);
    }
  }
);

When('the hard state has been reached', () => {
  switch (globalResourceType) {
    case 'host group':
      checkHostsAreMonitored([
        {
          name: data.hosts.host2.name,
          status: 'down',
          statusType: 'hard'
        }
      ]);
      break;
    case 'host group and services for these hosts':
      checkServicesAreMonitored([
        {
          name: data.services.service1.name,
          status: 'critical',
          statusType: 'hard'
        }
      ]);
      break;
    default:
      for (let i = 1; i <= 1000; i++) {
        cy.log('Check the hard state of service ' + i);
        checkServicesAreMonitored([
          {
            name: 'service_' + i,
            status: 'critical',
            statusType: 'hard'
          }
        ]);
      }
  }
});

When('the notification refresh_delay has been reached', () => {
  waitUntilLogFileChange();
});

Then(
  'an email is sent to the configured {string} with the configured format',
  (contactSettings) => {
    switch (contactSettings) {
      case 'a single contact':
        if (globalResourceType) {
          notificationSentCheck({ log: `<<${data.hosts.host2.name}>>` });
        } else {
          for (let i = 1; i <= 1000; i++) {
            cy.log('Check notification for service ' + i);
            notificationSentCheck({
              log: `<<${data.hosts.host1.name}/${'service_' + i}`
            });
          }
        }
        notificationSentCheck({
          log: `[{"email_address":"${data.contacts.contact1.email}","full_name":"${data.contacts.contact1.name}"}]`
        });
        break;
      case 'two contacts':
        if (globalResourceType) {
          notificationSentCheck({
            log: `<<${data.hosts.host1.name}/${data.services.service1.name}`
          });
        } else {
          for (let i = 1; i <= 1000; i++) {
            cy.log('Check notification for service ' + i);
            notificationSentCheck({
              log: `<<${data.hosts.host1.name}/${'service_' + i}`
            });
          }
        }
        notificationSentCheck({
          log: `[{"email_address":"${data.contacts.contact1.email}","full_name":"${data.contacts.contact1.name}"},{"email_address":"${data.contacts.contact2.email}","full_name":"${data.contacts.contact2.name}"}]`
        });
        break;
      default:
        throw new Error(`${contactSettings} not managed`);
    }
  }
);

Given(
  'a minimum of 1000 services linked to a host group and {string}',
  (contactSettings) => {
    switch (contactSettings) {
      case 'a single contact':
        cy.addContact({
          email: data.contacts.contact1.email,
          name: data.contacts.contact1.name,
          password: data.contacts.contact1.password
        });
        break;
      case 'two contacts':
        cy.addContact({
          email: data.contacts.contact1.email,
          name: data.contacts.contact1.name,
          password: data.contacts.contact1.password
        });
        cy.addContact({
          email: data.contacts.contact2.email,
          name: data.contacts.contact2.name,
          password: data.contacts.contact2.password
        });
        break;
      default:
        throw new Error(`${contactSettings} not managed`);
    }

    cy.addHostGroup({
      name: data.hostGroups.hostGroup1.name
    });

    cy.addHost({
      activeCheckEnabled: false,
      checkCommand: 'check_centreon_cpu',
      hostGroup: data.hostGroups.hostGroup1.name,
      name: data.hosts.host1.name,
      template: 'generic-host'
    }).applyPollerConfiguration();

    checkHostsAreMonitored([
      {
        name: data.hosts.host1.name
      }
    ]);

    for (let i = 1; i <= 1000; i++) {
      cy.log('Add service ' + i);

      cy.addService({
        activeCheckEnabled: false,
        host: data.hosts.host1.name,
        maxCheckAttempts: 1,
        name: 'service_' + i,
        template: 'Ping-LAN'
      });
    }

    cy.applyPollerConfiguration();

    // separate the add and the check for execution time performance
    for (let i = 1; i <= 1000; i++) {
      cy.log('Check service ' + i);
      checkServicesAreMonitored([
        {
          name: 'service_' + i
        }
      ]);
    }
  }
);

When(
  'the user selects a host group with its linked services and with associated events on which to notify',
  () => {
    cy.get('#Searchhostgroups').click();
    cy.contains(data.hostGroups.hostGroup1.name).click();
    cy.get('#Searchhostgroups').blur();
    cy.contains('Include services for these hosts').click();
    cy.get('[data-testid="Extra events services"] >').each(($el) => {
      cy.wrap($el).click();
    });
  }
);

When(
  'changes occur in the configured statuses for the selected host group',
  () => {
    for (let i = 1; i <= 1000; i++) {
      cy.log('Submit result for service ' + i);
      cy.submitResults([
        {
          host: data.hosts.host1.name,
          output: 'submit_status_' + i,
          service: 'service_' + i,
          status: 'critical'
        }
      ]);
    }
  }
);
