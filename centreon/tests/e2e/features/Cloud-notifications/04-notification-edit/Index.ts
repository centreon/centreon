import { Given, When, Then } from '@badeball/cypress-cucumber-preprocessor';

import {
  checkHostsAreMonitored,
  checkServicesAreMonitored
} from '../../../commons';
import {
  createNotification,
  editNotification,
  enableNotificationFeature,
  notificationSentCheck,
  setBrokerNotificationsOutput,
  waitUntilLogFileChange
} from '../common';
import notificationBody from '../../../fixtures/notifications/notification-creation.json';
import data from '../../../fixtures/notifications/data-for-notification.json';

const contactAfterEdit = 'Guest';

const editNotificationBody = { ...notificationBody };

let notificationWithServices = true;
let notificationEnabled = true;

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
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/latest/configuration/notifications/*'
  }).as('getNotification');

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

  checkHostsAreMonitored([
    {
      name: data.hosts.host1.name
    }
  ]);

  checkServicesAreMonitored([
    {
      name: data.services.service1.name
    }
  ]);

  notificationEnabled = true;
  notificationWithServices = true;
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

Given('a Notification Rule is already created', () => {
  createNotification(notificationBody);
});

When('the user selects the edit action on a Notification Rule', () => {
  cy.contains(notificationBody.name).click();
});

When(
  'the user changes the resources selection and corresponding status change parameters',
  () => {
    cy.contains('Include services for these hosts').click();
    notificationWithServices = false;
  }
);

When('the user saves to confirm the changes', () => {
  cy.getByTestId({ testId: 'Save' }).click();
});

When('the notification refresh delay has been reached', () => {
  cy.submitResults([
    {
      host: data.hosts.host1.name,
      output: 'submit_status_1',
      status: 'down'
    },
    {
      host: data.hosts.host1.name,
      output: 'submit_status_2',
      service: data.services.service1.name,
      status: 'critical'
    }
  ]);

  checkHostsAreMonitored([
    {
      name: data.hosts.host1.name,
      status: 'down',
      statusType: 'hard'
    }
  ]);

  checkServicesAreMonitored([
    {
      name: data.services.service1.name,
      status: 'critical'
    }
  ]);

  waitUntilLogFileChange();
});

Then(
  'only notifications for status changes of the updated resource parameters are sent',
  () => {
    if (!notificationEnabled) {
      notificationSentCheck({
        contain: false,
        logs: `<<${data.hosts.host1.name}>>`
      });
      notificationSentCheck({
        contain: false,
        logs: `<<${data.hosts.host1.name}/${data.services.service1.name}>>`
      });

      return;
    }

    notificationSentCheck({ logs: `<<${data.hosts.host1.name}>>` });

    if (!notificationWithServices) {
      notificationSentCheck({
        contain: false,
        logs: `<<${data.hosts.host1.name}/${data.services.service1.name}>>`
      });

      return;
    }

    notificationSentCheck({
      logs: `<<${data.hosts.host1.name}/${data.services.service1.name}>>`
    });
  }
);

When('the user changes the contact configuration', () => {
  cy.get('#Searchcontacts').click();
  cy.wait('@getUsers');
  cy.get('.MuiAutocomplete-option').eq(0).click();
  cy.contains(contactAfterEdit).click();
});

When(
  'the user selects the {string} action on a Notification Rule line',
  (action) => {
    cy.get('input.MuiSwitch-input').as('enableCheckbox');

    switch (action) {
      case 'enable':
        // Firstly Deactivate the Notification
        editNotificationBody.is_activated = false;
        editNotification(editNotificationBody);
        cy.reload();
        cy.wait('@getNotifications');
        // Reactivate the notification
        cy.get('@enableCheckbox').click();
        cy.get('@enableCheckbox').should('be.checked');
        // cy.get('input.MuiSwitch-input').click().should('be.checked');
        notificationEnabled = true;
        break;
      case 'disable':
        cy.get('@enableCheckbox').click();
        cy.get('@enableCheckbox').should('not.be.checked');
        notificationEnabled = false;
        break;
      default:
        throw new Error(`${action} not managed`);
    }
  }
);

Then(
  'the notifications for status changes are sent only to the updated contact',
  () => {
    notificationSentCheck({
      contain: false,
      logs: '[{"email_address":"admin@centreon.com","full_name":"admin admin"}]'
    });
    notificationSentCheck({
      logs: '[{"email_address":"guest@localhost","full_name":"Guest"}]'
    });
  }
);

Then('{string} notification is sent for this rule once', (prefix) => {
  switch (prefix) {
    case 'no more':
      notificationSentCheck({
        contain: false,
        logs: `<<${data.hosts.host1.name}>>`
      });
      notificationSentCheck({
        contain: false,
        logs: `<<${data.hosts.host1.name}/${data.services.service1.name}>>`
      });
      break;
    case 'one':
      notificationSentCheck({ logs: `<<${data.hosts.host1.name}>>` });
      notificationSentCheck({
        logs: `<<${data.hosts.host1.name}/${data.services.service1.name}>>`
      });
      break;
    default:
      throw new Error(`${prefix} not managed`);
  }
});

When('the user {string} the Notification Rule', (action) => {
  cy.get('input.MuiSwitch-input').eq(1).as('enableCheckbox');

  switch (action) {
    case 'enable':
      // Firstly Deactivate the Notification
      editNotificationBody.is_activated = false;
      editNotification(editNotificationBody);
      cy.reload();
      cy.wait('@getNavigationList');
      cy.wait('@getNotifications');
      // Reactivate the notification
      cy.contains(notificationBody.name).click();
      cy.wait('@getNotification');
      cy.get('@enableCheckbox').click();
      cy.get('@enableCheckbox').should('be.checked');
      notificationEnabled = true;
      break;
    case 'disable':
      cy.get('@enableCheckbox').click();
      cy.get('@enableCheckbox').should('not.be.checked');
      notificationEnabled = false;
      break;
    default:
      throw new Error(`${action} not managed`);
  }
});
