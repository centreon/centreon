import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { enableNotificationFeature } from '../common';
import { checkHostsAreMonitored, checkServicesAreMonitored } from 'e2e/commons';

var globalResourceType = '';

var globalContactSettings = '';

// // a single contact | host group
// const initiateForExample1 = () => {
//   cy.addContact({
//     name: 'contact_1',
//     email: 'contact1@localhost',
//     password: 'myPassword@1'
//   });
//   cy.addHostGroup({
//     name: 'host_group'
//   });
//   cy.addHost({
//     activeCheckEnabled: false,
//     checkCommand: 'check_centreon_cpu',
//     hostGroup: 'host_group',
//     name: 'host_1',
//     template: 'generic-host'
//   })
//     .addService({
//       activeCheckEnabled: false,
//       host: 'host_1',
//       maxCheckAttempts: 1,
//       name: 'service_1',
//       template: 'Ping-LAN'
//     })
//     .applyPollerConfiguration();

//   cy.addHost({
//     activeCheckEnabled: false,
//     checkCommand: 'check_centreon_cpu',
//     hostGroup: 'host_group',
//     name: 'host2',
//     template: 'generic-host'
//   })
//     .addService({
//       activeCheckEnabled: false,
//       host: 'host_2',
//       maxCheckAttempts: 1,
//       name: 'service_2',
//       template: 'Ping-LAN'
//     })
//     .applyPollerConfiguration();

//   checkServicesAreMonitored([
//     {
//       name: 'service_1'
//     },
//     {
//       name: 'service_2'
//     }
//   ]);
// };

// // two contacts | host group and services for these hosts
// const initiateForExample2 = () => {
//   cy.addContact({
//     name: 'contact_2',
//     email: 'contact2@localhost',
//     password: 'myPassword@1'
//   });
//   cy.addHostGroup({
//     name: 'host_group_2'
//   });
//   cy.addHost({
//     name: 'host_with_service_1',
//     hostGroup: 'host_group_2',
//     template: 'generic-host'
//   });
//   cy.addHost({
//     name: 'host_with_service_2',
//     hostGroup: 'host_group_2',
//     template: 'generic-host'
//   });
//   cy.addServiceTemplate({
//     name: 'service_template_1'
//   });
//   cy.addService({
//     host: 'host_with_service_1',
//     name: 'service_1',
//     template: 'service_template_1'
//   });
//   cy.addService({
//     host: 'host_with_service_2',
//     name: 'service_2',
//     template: 'service_template_1'
//   });
// };

// // a single contact group | service group
// const initiateForExample3 = () => {
//   cy.addContactGroup({
//     name: 'contact_group_1',
//     contacts: ['contact_1', 'contact_2']
//   });
//   cy.addServiceGroup({
//     name: 'service_group_1',
//     hostsAndServices: [
//       ['host_with_service_1', 'service_1'],
//       ['host_with_service_2', 'service_2']
//     ]
//   });
// };

// // two contact groups | Business View
// const initiateForExample4 = () => {
//   cy.addContact({
//     name: 'contact_3',
//     email: 'contact3@localhost',
//     password: 'myPassword@1'
//   });
//   cy.addContact({
//     name: 'contact_4',
//     email: 'contact4@localhost',
//     password: 'myPassword@1'
//   });
//   cy.addContactGroup({
//     name: 'contact_group_2',
//     contacts: ['contact_3', 'contact_4']
//   });
// };

// // a single contact and a single contact group
// // nothing to initiate for this example

// // two contacts and two contact groups |
// const initiateForExample6 = () => {
//   cy.addContact({
//     name: 'contact_5',
//     email: 'contact5@localhost',
//     password: 'myPassword@1'
//   });
//   cy.addContact({
//     name: 'contact_6',
//     email: 'contact6@localhost',
//     password: 'myPassword@1'
//   });
// };

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
          name: 'contact_1',
          email: 'contact1@localhost',
          password: 'myPassword@1'
        });
        break;
      case 'two contacts':
        cy.addContact({
          name: 'contact_2',
          email: 'contact2@localhost',
          password: 'myPassword@2'
        });
        break;
      // case 'a single contact group':
      //   initiateForExample3();
      //   break;
      // case 'two contact groups':
      //   initiateForExample4();
      //   break;
      // case 'a single contact and a single contact group':
      //   break;
      // case 'two contacts and two contact groups':
      //   initiateForExample6();
      //   break;
    }

    switch (resourceType) {
      case 'host group':
        cy.addHostGroup({
          name: 'host_group_1'
        });

        cy.addHost({
          activeCheckEnabled: false,
          checkCommand: 'check_centreon_cpu',
          hostGroup: 'host_group_1',
          name: 'host_1',
          template: 'generic-host'
        }).applyPollerConfiguration();

        cy.addHost({
          activeCheckEnabled: false,
          checkCommand: 'check_centreon_cpu',
          hostGroup: 'host_group_1',
          name: 'host_2',
          template: 'generic-host'
        }).applyPollerConfiguration();

        checkHostsAreMonitored([
          {
            name: 'host_1'
          },
          {
            name: 'host_2'
          }
        ]);
        break;

      case 'host group and services for these hosts':
        cy.addHostGroup({
          name: 'host_group_2'
        });

        cy.addHost({
          activeCheckEnabled: false,
          checkCommand: 'check_centreon_cpu',
          hostGroup: 'host_group_2',
          name: 'host_3',
          template: 'generic-host'
        })
          .addService({
            activeCheckEnabled: false,
            host: 'host_3',
            maxCheckAttempts: 1,
            name: 'service_1',
            template: 'Ping-LAN'
          })
          .applyPollerConfiguration();

        cy.addHost({
          activeCheckEnabled: false,
          checkCommand: 'check_centreon_cpu',
          hostGroup: 'host_group_2',
          name: 'host_4',
          template: 'generic-host'
        })
          .addService({
            activeCheckEnabled: false,
            host: 'host_4',
            maxCheckAttempts: 1,
            name: 'service_2',
            template: 'Ping-LAN'
          })
          .applyPollerConfiguration();

        checkHostsAreMonitored([
          {
            name: 'host_3'
          },
          {
            name: 'host_4'
          }
        ]);

        checkServicesAreMonitored([
          {
            name: 'service_1'
          },
          {
            name: 'service_2'
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
        cy.contains('host_group_1').click();
        cy.get('#Recovery').eq(1).click();
        cy.get('#Down').eq(1).click();
        cy.get('#Unreachable').eq(1).click();
        break;
      case 'host group and services for these hosts':
        cy.get('#Searchhostgroups').click();
        cy.contains('host_group_2').click();
        cy.contains('Include services for these hosts').click();
        cy.get('#Recovery').eq(2).click();
        cy.get('#Warning').eq(1).click();
        cy.get('#Critical').eq(1).click();
        cy.get('#Unkown').eq(1).click();
        break;
      // case 'service group':
      //   cy.get('#Searchservicegroups').click();
      //   cy.contains('service_group_1').click();
      //   break;
      // case 'Business View':
      //   break;
    }
  }
);

When('the user selects the {string}', (contactSettings: string) => {
  switch (contactSettings) {
    case 'a single contact':
      cy.get('#Searchcontacts').click();
      cy.contains('contact_1').click();
      break;
    case 'two contacts':
      cy.get('#Searchcontacts').click();
      cy.contains('contact_1').click();
      cy.contains('contact_2').click();
      break;
    // case 'a single contact group':
    //   cy.get('#Searchcontacts').click();
    //   cy.contains('contact_group_1').click();
    //   break;
    // case 'two contact groups':
    //   cy.get('#Searchcontacts').click();
    //   cy.contains('contact_group_1').click();
    //   cy.contains('contact_group_2').click();
    //   break;
    // case 'a single contact and a single contact group':
    //   cy.get('#Searchcontacts').click();
    //   cy.contains('contact_1').click();
    //   cy.contains('contact_group_2').click();
    //   break;
    // case 'two contacts and two contact groups':
    //   cy.get('#Searchcontacts').click();
    //   cy.contains('contact_5').click();
    //   cy.contains('contact_6').click();
    //   cy.contains('contact_group_1').click();
    //   cy.contains('contact_group_2').click();
    //   break;
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
            host: 'host_1',
            output: 'submit_status_1',
            status: 'down'
          },
          {
            host: 'host_2',
            output: 'submit_status_1',
            status: 'down'
          }
        ]);

        checkHostsAreMonitored([
          {
            name: 'host_1',
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
            host: 'host_3',
            output: 'submit_status_2',
            service: 'service_1',
            status: 'critical'
          },
          {
            host: 'host_4',
            output: 'submit_status_2',
            service: 'service_2',
            status: 'critical'
          }
        ]);

        checkServicesAreMonitored([
          {
            name: 'service_1',
            status: 'critical'
          },
          {
            name: 'service_2',
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
          name: 'host_1',
          status: 'down',
          statusType: 'hard'
        },
        {
          name: 'host_2',
          status: 'down',
          statusType: 'hard'
        }
      ]);
      break;
    case 'host group and services for these hosts':
      checkServicesAreMonitored([
        {
          name: 'service_1',
          status: 'critical',
          statusType: 'hard'
        },
        {
          name: 'service_2',
          status: 'critical',
          statusType: 'hard'
        }
      ]);
      break;
  }
});

When('the notification refresh_delay has been reached', () => {
  cy.wait(3000);
});

Then(
  'an email is sent to the configured {string} with the configured format',
  (contactSettings) => {}
);
