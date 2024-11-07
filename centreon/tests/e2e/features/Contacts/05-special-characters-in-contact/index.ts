/* eslint-disable cypress/unsafe-to-chain-command */
import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';

import contacts from '../../../fixtures/users/contact.json';

beforeEach(() => {
  cy.startContainers();
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/internal.php?object=centreon_topology&action=navigationList'
  }).as('getNavigationList');
  cy.intercept({
    method: 'GET',
    url: '/centreon/include/common/userTimezone.php'
  }).as('getTimeZone');
});

afterEach(() => {
  cy.stopContainers();
});

Given('an admin user is logged in a Centreon server', () => {
  cy.loginByTypeOfUser({
    jsonName: 'admin',
    loginViaApi: false
  });
});

When('one non admin contact has been created', () => {
  cy.executeCommandsViaClapi(
    'resources/clapi/config-ACL/non-admin-with-access-to-allmodules.json'
  );
});

When(
  'the user has changed the contact alias by adding a special character',
  () => {
    cy.navigateTo({
      page: 'Contacts / Users',
      rootItemNumber: 3,
      subMenu: 'Users'
    });
    cy.getIframeBody().contains('user-with-access-to-allmodules').click();
    cy.addOrUpdateContact(contacts.contactWithSpecialAlias);
  }
);

Then(
  'the new record is displayed in the users list with the new alias value',
  () => {
    cy.getIframeBody()
      .contains(contacts.contactWithSpecialAlias.alias)
      .should('be.visible');
    cy.getIframeBody().contains(contacts.contactWithSpecialAlias.alias).click();
    cy.waitForElementInIframe('#main-content', 'input[id="contact_alias"]');
    cy.getIframeBody()
      .find('input[id="contact_alias"]')
      .should('have.value', contacts.contactWithSpecialAlias.alias);
  }
);

Given('the contact alias contains an accent', () => {
  cy.navigateTo({
    page: 'Contacts / Users',
    rootItemNumber: 3,
    subMenu: 'Users'
  });
  cy.getIframeBody().contains('user-with-access-to-allmodules').click();
  cy.addOrUpdateContact(contacts.contactWithSpecialAlias);
  cy.logout();
});

When('the contact fill login field and Password', () => {
  cy.loginByDuplicatedOrUpdatedUser(
    'user-with-access-to-allmodules',
    contacts.contactWithSpecialAlias.alias
  );
});

Then('the contact is logged in to Centreon Web', () => {
  cy.url().should('include', '/centreon/monitoring/resources');
});
