import { Given, When, Then } from '@badeball/cypress-cucumber-preprocessor';
import {
  createNotification,
  editNotification,
  enableNotificationFeature
} from '../common';
import notificationBody from '../../../fixtures/notifications/notification-creation.json';

const contactAfterEdit = 'Guest';

const editNotificationBody = { ...notificationBody };

beforeEach(() => {
  cy.startWebContainer();
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
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/latest/configuration/users?page=1*'
  }).as('getUsers');
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/latest/configuration/notifications/*'
  }).as('getNotification');
});

afterEach(() => {
  cy.stopWebContainer();
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

Given('the user is on the Notification Rules page', () => {
  cy.url().should('include', '/configuration/notifications');
});

When('the user selects the edit action on a Notification Rule', () => {
  cy.contains(notificationBody.name).click();
});

When(
  'the user changes the resources selection and corresponding status change parameters',
  () => {
    cy.contains('Include services for these hosts').click();
  }
);

When('the user saves to confirm the changes', () => {
  cy.get('[data-testid="Save"]').click();
});

When('the notification refresh delay has been reached', () => {
  cy.wait(5000);
});

Then(
  'only notifications for status changes of the updated resource parameters are sent',
  () => {
    // WIP
  }
);

When('the user changes the {string} configuration', (userType) => {
  cy.get('#Searchcontacts').click();
  cy.wait('@getUsers');
  cy.get('li > div > span.MuiButtonBase-root').eq(0).click();
  cy.contains(contactAfterEdit).click();
});

When(
  'the user selects the {string} action on a Notification Rule line',
  (action) => {
    cy.get('input.MuiSwitch-input').then(($checkbox) => {
      switch (action) {
        case 'enable':
          // Firstly Deactivate the Notification
          editNotificationBody.is_activated = false;
          editNotification(editNotificationBody);
          cy.reload();
          cy.wait('@getNotifications');
          // Reactivate the notification
          cy.get('input.MuiSwitch-input').click().should('be.checked');
          break;
        case 'disable':
          cy.wrap($checkbox).click().should('not.be.checked');
          break;
      }
    });
  }
);

Then(
  'the notifications for status changes are sent only to the updated {string}',
  (userType) => {
    // WIP
  }
);

Then('{string} notification is sent for this rule once', (prefix) => {
  // WIP
});

When('the user {string} the Notification Rule', (action) => {
  cy.get('input.MuiSwitch-input')
    .eq(1)
    .then(($checkbox) => {
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
          cy.get('input.MuiSwitch-input').eq(1).click().should('be.checked');
          break;
        case 'disable':
          cy.wrap($checkbox).click().should('not.be.checked');
          break;
      }
    });
});
