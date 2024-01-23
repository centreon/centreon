import { Given, When, Then } from '@badeball/cypress-cucumber-preprocessor';
import { createNotification, enableNotificationFeature } from '../common';
import notificationBody from '../../../fixtures/notifications/notification-delete.json';

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

Given('the user is on the Notification Rules page', () => {
  cy.url().should('include', '/configuration/notifications');
});

Given('a Notification Rule is already created', () => {
  createNotification(notificationBody);
});

When('the user selects the delete action on a Notification Rule', () => {
  cy.get('#Deletenotificationrule').click();
});

When('the user confirms the deletion', () => {
  cy.get('[data-testid="Confirm"]').click();
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
    // WIP
  }
);

When('the user clicks on the discard action', () => {
  cy.get('[data-testid="Cancel"]').click();
});

Then('the deletion is cancelled', () => {
  cy.contains(notificationBody.name).should('exist');
});
