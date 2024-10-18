/* eslint-disable cypress/unsafe-to-chain-command */
import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';

import contacts from '../../../fixtures/users/contact.json';

const checkFirstContactFromListing = () => {
  cy.navigateTo({
    page: 'Contacts / Users',
    rootItemNumber: 3,
    subMenu: 'Users'
  });
  cy.wait('@getTimeZone');
  cy.getIframeBody().find('div.md-checkbox.md-checkbox-inline').eq(1).click();
  cy.getIframeBody()
    .find('select')
    .eq(0)
    .invoke(
      'attr',
      'onchange',
      "javascript: { setO(this.form.elements['o1'].value); submit(); }"
    );
};

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

When('a contact is configured', () => {
  cy.navigateTo({
    page: 'Contacts / Users',
    rootItemNumber: 3,
    subMenu: 'Users'
  });
  cy.wait('@getTimeZone');
  cy.getIframeBody().contains('a', 'Add').click();
  cy.addOrUpdateContact(contacts.default);
});

When('the user updates some contact properties', () => {
  cy.getIframeBody().contains(contacts.default.alias).click();
  cy.addOrUpdateContact(contacts.contactForUpdate);
});

Then('these properties are updated', () => {
  cy.getIframeBody().contains(contacts.contactForUpdate.alias).should('exist');
  cy.getIframeBody().contains(contacts.contactForUpdate.alias).click();
  cy.wait('@getTimeZone');
  cy.waitForElementInIframe('#main-content', 'input[id="contact_alias"]');
  cy.getIframeBody()
    .find('input[id="contact_alias"]')
    .should('have.value', contacts.contactForUpdate.alias);
  cy.getIframeBody()
    .find('input[id="contact_name"]')
    .should('have.value', contacts.contactForUpdate.name);
  cy.getIframeBody()
    .find('input[id="contact_email"]')
    .should('have.value', contacts.contactForUpdate.email);
  cy.getIframeBody()
    .find('input[id="contact_pager"]')
    .should('have.value', contacts.contactForUpdate.pager);
  cy.getIframeBody().find('#contact_template_id').should('have.value', '19');
  cy.checkLegacyRadioButton(contacts.contactForUpdate.isNotificationsEnabled);
});

When('the user duplicates the configured contact', () => {
});

Then('a new contact is created with identical properties', () => {
});

When('the user deletes the configured contact', () => {
});

Then('the deleted contact is not visible anymore on the contact page', () => {
});
