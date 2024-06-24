import { Given, When, Then } from '@badeball/cypress-cucumber-preprocessor';

import {
  createNotification,
  enableNotificationFeature,
  notificationSentCheck,
  setBrokerNotificationsOutput,
  waitUntilLogFileChange
} from '../common';
import notificationBody from '../../../fixtures/notifications/notification-creation.json';
import {
  checkHostsAreMonitored,
  checkServicesAreMonitored
} from '../../../commons';
import data from '../../../fixtures/notifications/data-for-notification.json';

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

When('the user selects the delete action on a Notification Rule', () => {
  cy.get('#Deletenotificationrule').click();
});

When('the user confirms the deletion', () => {
  cy.getByTestId({ testId: 'Confirm' }).click();
});

Then(
  'a success message is displayed and the Notification Rule is deleted from the listing',
  () => {
    cy.get('.MuiAlert-message').should(
      'have.text',
      'Notification deleted successfully'
    );
    cy.wait('@getNotifications');
    cy.contains(notificationBody.name).should('not.exist');
  }
);

Then(
  'the configured users are no longer notified of status changes for the associated resources once the notification refresh delay has been reached',
  () => {
    cy.submitResults([
      {
        host: data.hosts.host1.name,
        output: 'submit_status_1',
        status: 'down'
      }
    ]);

    checkHostsAreMonitored([
      {
        name: data.hosts.host1.name,
        status: 'down',
        statusType: 'hard'
      }
    ]);

    waitUntilLogFileChange();

    notificationSentCheck({
      contain: false,
      logs: `<<${data.hosts.host1.name}>>`
    });
  }
);

When('the user clicks on the discard action', () => {
  cy.getByTestId({ testId: 'Cancel' }).click();
});

Then('the deletion is cancelled', () => {
  cy.contains(notificationBody.name).should('exist');
});
