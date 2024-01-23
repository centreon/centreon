import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { enableNotificationFeature } from '../common';
import { checkHostsAreMonitored, checkServicesAreMonitored } from 'e2e/commons';

var globalResourceType = '';
var globalContactSettings = '';

const hostGroups = {
  hostGroup1: {
    name: 'host_group_1'
  },
  hostGroup2: {
    name: 'host_group_2'
  }
};

const services = {
  service1: {
    name: 'service_1'
  },
  service2: {
    name: 'service_2'
  }
};

const hosts = {
  host1: {
    name: 'host_1',
    hostGroup: hostGroups.hostGroup1.name
  },
  host2: {
    name: 'host_2',
    hostGroup: hostGroups.hostGroup1.name
  },
  host3: {
    name: 'host_3',
    hostGroup: hostGroups.hostGroup2.name
  },
  host4: {
    name: 'host_4',
    hostGroup: hostGroups.hostGroup2.name
  }
};

const contacts = {
  contact1: {
    name: 'contact_1',
    email: 'contact1@localhost',
    password: 'myPassword@1'
  },
  contact2: {
    name: 'contact_2',
    email: 'contact2@localhost',
    password: 'myPassword@2'
  }
};

before(() => {
  cy.startWebContainer();
  enableNotificationFeature();
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
          name: contacts.contact1.name,
          email: contacts.contact1.email,
          password: contacts.contact1.password
        });
        break;
      case 'two contacts':
        cy.addContact({
          name: contacts.contact2.name,
          email: contacts.contact2.email,
          password: contacts.contact2.password
        });
        break;
    }

    switch (resourceType) {
      case 'host group':
        cy.addHostGroup({
          name: hostGroups.hostGroup1.name
        });

        cy.addHost({
          activeCheckEnabled: false,
          checkCommand: 'check_centreon_cpu',
          hostGroup: hostGroups.hostGroup1.name,
          name: hosts.host1.name,
          template: 'generic-host'
        }).applyPollerConfiguration();

        cy.addHost({
          activeCheckEnabled: false,
          checkCommand: 'check_centreon_cpu',
          hostGroup: hostGroups.hostGroup1.name,
          name: hosts.host2.name,
          template: 'generic-host'
        }).applyPollerConfiguration();

        checkHostsAreMonitored([
          {
            name: hosts.host1.name
          },
          {
            name: hosts.host2.name
          }
        ]);
        break;

      case 'host group and services for these hosts':
        cy.addHostGroup({
          name: hostGroups.hostGroup2.name
        });

        cy.addHost({
          activeCheckEnabled: false,
          checkCommand: 'check_centreon_cpu',
          hostGroup: hostGroups.hostGroup2.name,
          name: hosts.host3.name,
          template: 'generic-host'
        })
          .addService({
            activeCheckEnabled: false,
            host: hosts.host3.name,
            maxCheckAttempts: 1,
            name: services.service1.name,
            template: 'Ping-LAN'
          })
          .applyPollerConfiguration();

        cy.addHost({
          activeCheckEnabled: false,
          checkCommand: 'check_centreon_cpu',
          hostGroup: hostGroups.hostGroup2.name,
          name: hosts.host4.name,
          template: 'generic-host'
        })
          .addService({
            activeCheckEnabled: false,
            host: hosts.host4.name,
            maxCheckAttempts: 1,
            name: 'service_2',
            template: 'Ping-LAN'
          })
          .applyPollerConfiguration();

        checkHostsAreMonitored([
          {
            name: hosts.host3.name
          },
          {
            name: hosts.host4.name
          }
        ]);

        checkServicesAreMonitored([
          {
            name: services.service1.name
          },
          {
            name: services.service2.name
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
        cy.contains(hostGroups.hostGroup1.name).click();
        cy.get('#Recovery').click();
        cy.get('#Down').click();
        cy.get('#Unreachable').click();
        break;
      case 'host group and services for these hosts':
        cy.get('#Searchhostgroups').click();
        cy.contains(hostGroups.hostGroup2.name).click();
        cy.contains('Include services for these hosts').click({ force: true });
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
      cy.contains(contacts.contact1.name).click();
      break;
    case 'two contacts':
      cy.get('#Searchcontacts').click();
      cy.contains(contacts.contact1.name).click();
      cy.contains(contacts.contact2.name).click();
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
            host: hosts.host1.name,
            output: 'submit_status_1',
            status: 'down'
          },
          {
            host: hosts.host2.name,
            output: 'submit_status_1',
            status: 'down'
          }
        ]);

        checkHostsAreMonitored([
          {
            name: hosts.host1.name,
            status: 'down'
          },
          {
            name: 'host_2',
            status: 'down'
          }
        ]);
        break;
      case 'host group and services for these hosts':
        cy.submitResults([
          {
            host: hosts.host3.name,
            output: 'submit_status_2',
            service: services.service1.name,
            status: 'critical'
          },
          {
            host: hosts.host4.name,
            output: 'submit_status_2',
            service: services.service2.name,
            status: 'critical'
          }
        ]);

        checkServicesAreMonitored([
          {
            name: services.service1.name,
            status: 'critical'
          },
          {
            name: services.service2.name,
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
          name: hosts.host1.name,
          status: 'down',
          statusType: 'hard'
        },
        {
          name: hosts.host2.name,
          status: 'down',
          statusType: 'hard'
        }
      ]);
      break;
    case 'host group and services for these hosts':
      checkServicesAreMonitored([
        {
          name: services.service1.name,
          status: 'critical',
          statusType: 'hard'
        },
        {
          name: services.service2.name,
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
    // WIP
  }
);
