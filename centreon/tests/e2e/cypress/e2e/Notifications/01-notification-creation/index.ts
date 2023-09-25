import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';

const notificationName = 'notification_cloud';

before(() => {
  cy.startWebContainer();
  cy.execInContainer({
    command: `sed -i 's@"notification" : 0@"notification": 3@' /usr/share/centreon/config/features.json`,
    name: Cypress.env('dockerName')
  });
  cy.executeCommandsViaClapi('notifications/notification-configuration.json');
});

beforeEach(() => {
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/internal.php?object=centreon_topology&action=navigationList'
  }).as('getNavigationList');
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/latest/configuration/notifications*'
  }).as('getNotificationList');
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/latest/configuration/hosts/groups*'
  }).as('getHostGroupList');

  cy.intercept({
    method: 'GET',
    url: 'centreon/api/latest/configuration/services/groups*'
  }).as('getServiceGroupList');

  cy.intercept({
    method: 'GET',
    url: 'centreon/api/latest/configuration/users*'
  }).as('getContactList');

  cy.intercept({
    method: 'GET',
    url: 'centreon/api/latest/configuration/contacts/groups*'
  }).as('getContactGroupList');
});

Given('a user accessing to listing of cloud notification definition', () => {
  cy.loginByTypeOfUser({ jsonName: 'admin' });

  cy.navigateTo({
    page: 'Notifications',
    rootItemNumber: 3,
    subMenu: 'Notifications'
  });

  cy.wait('@getNotificationList');

  cy.url().should('include', '/configuration/notifications');

  cy.getByTestId({
    tag: 'button',
    testId: 'createNotificationForTheFirstTime'
  }).should('be.visible');
});

Given('clicking on create a notification button', () => {
  cy.getByTestId({
    tag: 'button',
    testId: 'createNotificationForTheFirstTime'
  }).click();
});

Then('the user should see the form option for rule creation', () => {
  cy.get('#panel-content').should('be.visible');
});

Given('the user defines a name for the rule', () => {
  cy.getByLabel({ label: 'Notification name', tag: 'input' }).type(
    `{selectall}{backspace}${notificationName}`
  );
});

Given('the user selects one or more host groups and host status', () => {
  cy.getByLabel({ label: 'Search host groups', tag: 'input' }).click();

  cy.wait('@getHostGroupList')
    .get('div[role="presentation"] ul li')
    .eq(1)
    .click();

  cy.getByTestId({ tag: 'div', testId: 'Host groups events' })
    .find('#Recovery')
    .check();

  cy.getByTestId({ tag: 'div', testId: 'Host groups events' })
    .find('#Down')
    .check();

  cy.contains('Include services for these hosts').click();

  cy.getByTestId({ tag: 'div', testId: 'Extra events services' })
    .find('#Recovery')
    .check();

  cy.getByTestId({ tag: 'div', testId: 'Extra events services' })
    .find('#Warning')
    .check();

  cy.getByTestId({ tag: 'div', testId: 'Extra events services' })
    .find('#Critical')
    .check();
});

Given('the user selects one or more service groups and services status', () => {
  cy.getByLabel({ label: 'Search service groups', tag: 'input' }).click();

  cy.wait('@getServiceGroupList')
    .get('div[role="presentation"] ul li')
    .eq(0)
    .click();

  cy.getByTestId({ tag: 'div', testId: 'Service groups events' })
    .find('#Recovery')
    .check();

  cy.getByTestId({ tag: 'div', testId: 'Service groups events' })
    .find('#Warning')
    .check();

  cy.getByTestId({ tag: 'div', testId: 'Service groups events' })
    .find('#Critical')
    .check();
});

Given('the user selects one or more contacts', () => {
  cy.getByLabel({ label: 'Search contacts', tag: 'input' }).click();

  cy.wait('@getContactList')
    .get('div[role="presentation"] ul li')
    .eq(0)
    .click();

  cy.getByLabel({ label: 'Search contacts', tag: 'input' }).click();
});

Given('the user selects one or more contact groups', () => {
  cy.getByLabel({ label: 'Search contact groups', tag: 'input' }).click();

  cy.wait('@getContactGroupList')
    .get('div[role="presentation"] ul li')
    .eq(0)
    .click();
});

Given('the user defines a mail subject', () => {
  cy.getByLabel({ label: 'Subject', tag: 'input' }).type(
    '{selectall}{backspace}Notification Message'
  );
});

Given('the user defines a mail body', () => {
  cy.getByLabel({ label: 'EmailBody', tag: 'div' }).type(
    '{selectall}{backspace}Your notification is created succefully'
  );
});

When('the user clicks on the "Save" button and confirm', () => {
  cy.getByTestId({
    tag: 'button',
    testId: 'Save'
  }).click();

  cy.getByTestId({
    tag: 'button',
    testId: 'Confirm'
  }).click();
});

Then(
  'a success message is displayed and the created notification rule is displayed in the listing',
  () => {
    cy.wait('@getNotificationList').then(() => {
      cy.contains('The notification was successfully added').should(
        'have.length',
        1
      );
    });

    cy.contains(notificationName).should('be.visible');
  }
);

after(() => {
  cy.stopWebContainer();
});
