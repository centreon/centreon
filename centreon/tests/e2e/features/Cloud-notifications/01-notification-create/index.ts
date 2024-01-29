import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import {
  enableNotificationFeature,
  notificationSentCheck,
  setBrokerNotificationsOutput
} from '../common';
import { checkHostsAreMonitored, checkServicesAreMonitored } from 'e2e/commons';

import data from '../../../fixtures/notifications/data-for-notification.json';

var globalResourceType = '';
var globalContactSettings = '';

before(() => {
  cy.startWebContainer({ useSlim: false });
  enableNotificationFeature();
  setBrokerNotificationsOutput({
    name: 'central-cloud-notifications-output',
    configName: 'central-broker-master'
  });
});

after(() => {
  cy.stopWebContainer();
});

beforeEach(() => {
  cy.setUserTokenApiV1();
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
});

Given('a user with access to the Notification Rules page', () => {
  cy.navigateTo({
    page: 'Notifications',
    rootItemNumber: 3,
    subMenu: 'Notifications'
  });
});

Given('the user is on the Notification Rules page', () => {
  cy.url().should('include', '/configuration/notifications');
});

Given(
  'a {string} with hosts and {string}',
  (resourceType: string, contactSettings: string) => {
    globalResourceType = resourceType;
    globalContactSettings = contactSettings;

    switch (contactSettings) {
      case 'a single contact':
        cy.addContact({
          name: data.contacts.contact1.name,
          email: data.contacts.contact1.email,
          password: data.contacts.contact1.password
        });
        break;
      case 'two contacts':
        cy.addContact({
          name: data.contacts.contact2.name,
          email: data.contacts.contact2.email,
          password: data.contacts.contact2.password
        });
        break;
    }

    switch (resourceType) {
      case 'host group':
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
        break;
    }
  }
);

When('the user defines a name for the rule', () => {
  cy.contains('Add').click();
  cy.get('#Notificationname')
    .click()
    .type(
      `Notification for ${globalResourceType} and ${globalContactSettings}`
    );
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
  }
});

When('the user defines a mail subject', () => {
  cy.getByLabel({ label: 'Subject' })
    .click()
    .clear()
    .type(
      `Subject notification for ${globalResourceType} and ${globalContactSettings}`
    );
});

When('the user defines a mail body', () => {
  cy.getByLabel({ label: 'EmailBody' })
    .click()
    .clear()
    .type(
      `Body notification for ${globalResourceType} and ${globalContactSettings}`
    );
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
    cy.contains(
      `Notification for ${globalResourceType} and ${globalContactSettings}`
    ).should('exist');
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
  }
});

When('the notification refresh_delay has been reached', () => {
  cy.wait(5000);
});

Then(
  'an email is sent to the configured {string} with the configured format',
  (contactSettings) => {
    switch (contactSettings) {
      case 'a single contact':
        notificationSentCheck({ log: 'Notification on host 16' });
        notificationSentCheck({
          log: `[{"email_address":"${data.contacts.contact1.email}","full_name":"${data.contacts.contact1.name}"}]`
        });
        break;
      case 'two contacts':
        notificationSentCheck({ log: 'Notification on service (15,27)' });
        notificationSentCheck({
          log: `[{"email_address":"${data.contacts.contact1.email}","full_name":"${data.contacts.contact1.name}"},{"email_address":"${data.contacts.contact2.email}","full_name":"${data.contacts.contact2.name}"}]`
        });
        break;
    }
  }
);
