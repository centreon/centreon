import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';

import { createNotification, enableNotificationFeature } from '../common';
import notificationBody from '../../../fixtures/notifications/notification-creation.json';
import {
  checkHostsAreMonitored,
  checkServicesAreMonitored
} from '../../../commons';
import data from '../../../fixtures/notifications/data-for-notification.json';

const duplicatedNotificationName = 'Duplicated Notification';

const notificationProperties = [
  'channels',
  'is_activated',
  'resources',
  'timeperiod',
  'user_count'
];

beforeEach(() => {
  cy.startContainers();
  enableNotificationFeature();
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

When('the user selects the duplication action on a Notification Rule', () => {
  cy.get('#Duplicate').click();
});

When('the user enters a new Notification Rule name', () => {
  cy.get('#Newnotificationname').type(duplicatedNotificationName);
});

When('the user confirms to duplicate', () => {
  cy.getByTestId({ testId: 'Confirm' }).click();
});

Then('a success message is displayed', () => {
  cy.get('.MuiAlert-message').should('have.text', 'Notification duplicated');
});

Then(
  'the duplicated Notification Rule with same properties is displayed in the listing',
  () => {
    cy.wait('@getNotifications');
    cy.contains(duplicatedNotificationName).should('exist');
  }
);

Then(
  'the duplicated Notification Rule features the same properties as the initial Notification Rule',
  () => {
    cy.request({
      method: 'GET',
      url: 'centreon/api/latest/configuration/notifications'
    }).then((response) => {
      const duplicatedNotification = response.body.result.find(
        (notification) => notification.name === duplicatedNotificationName
      );
      const originalNotification = response.body.result.find(
        (notification) => notification.name === notificationBody.name
      );

      notificationProperties.forEach((property) => {
        expect(duplicatedNotification[property]).to.deep.equal(
          originalNotification[property]
        );
      });
    });
  }
);

When('the user clicks on the discard action', () => {
  cy.getByTestId({ testId: 'Cancel' }).click();
});

Then('the duplication action is cancelled', () => {
  cy.get('.MuiTableRow-root').eq(1).should('have.length', 1);
});

When('the user enters a name that is already taken', () => {
  cy.get('#Newnotificationname')
    .as('notificationNameInput')
    .type(notificationBody.name);
  cy.get('@notificationNameInput').blur();
});

Then(
  'an error message is displayed indicating that the duplication is not possible',
  () => {
    cy.contains('This name already exists').should('exist');
  }
);

Then('the duplicate button is disabled', () => {
  cy.getByTestId({ testId: 'Confirm' }).should('be.disabled');
});
