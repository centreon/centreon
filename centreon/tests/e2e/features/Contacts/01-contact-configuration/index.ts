/* eslint-disable cypress/unsafe-to-chain-command */
import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';

import contacts from '../../../fixtures/users/contact.json';

let isAdmin = true;
let contactPageIndex = 3;
const checkContactFromListing = () => {
  cy.navigateTo({
    page: 'Contacts / Users',
    rootItemNumber: contactPageIndex,
    subMenu: 'Users'
  });
  cy.wait('@getTimeZone');
  const index = isAdmin ? 3 : 1;
  cy.getIframeBody()
    .find('div.md-checkbox.md-checkbox-inline')
    .eq(index)
    .click();
  cy.getIframeBody()
    .find('select[name="o1"]')
    .invoke(
      'attr',
      'onchange',
      "javascript: { setO(this.form.elements['o1'].value); submit(); }"
    );
};

beforeEach(() => {
  cy.startContainers();
  cy.setUserTokenApiV1().executeCommandsViaClapi(
    'resources/clapi/config-ACL/contacts-management-acl-user.json'
  );
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/internal.php?object=centreon_topology&action=navigationList'
  }).as('getNavigationList');
  cy.intercept({
    method: 'GET',
    url: '/centreon/include/common/userTimezone.php'
  }).as('getTimeZone');
  cy.intercept({
    method: 'GET',
    url: '/centreon/include/common/webServices/rest/internal.php?object=centreon_administration_aclgroup&action=list*'
  }).as('getAclGroups');
  //
});

afterEach(() => {
  cy.stopContainers();
});

Given('a {string} user is logged in a Centreon server', (user: string) => {
  contactPageIndex = user === 'admin' ? 3 : 0;
  isAdmin = user === 'admin';
  cy.loginByTypeOfUser({
    jsonName: user === 'admin' ? 'admin' : 'contacts-management-acl-user',
    loginViaApi: false
  });
});

When('a contact is configured', () => {
  cy.navigateTo({
    page: 'Contacts / Users',
    rootItemNumber: contactPageIndex,
    subMenu: 'Users'
  });
  cy.wait('@getTimeZone');
  cy.getIframeBody().contains('a', 'Add').click();
  cy.addOrUpdateContact(contacts.default);
  if (!isAdmin) {
    // Add the contact to the ACL Group of the connected non-admin user
    cy.getIframeBody().contains('a', 'Centreon Authentication').click();
    // Click outside the form
    cy.get('body').click(0, 0);
    cy.getIframeBody()
      .find('ul[class="select2-selection__rendered"]')
      .eq(3)
      .click();
    cy.wait('@getAclGroups');
    cy.getIframeBody().contains('div', 'user-ACLGROUP').click();
  }
  cy.getIframeBody().find('input.btc.bt_success[name^="submit"]').eq(0).click();
  cy.wait('@getTimeZone');
  cy.exportConfig();
});

When('the user updates some contact properties', () => {
  cy.getIframeBody().contains(contacts.default.alias).click();
  cy.addOrUpdateContact(contacts.contactForUpdate);
  cy.getIframeBody().find('input.btc.bt_success[name^="submit"]').eq(0).click();
  cy.wait('@getTimeZone');
  cy.exportConfig();
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
  checkContactFromListing();
  cy.getIframeBody().find('select[name="o1"]').select('Duplicate');
  cy.wait('@getTimeZone');
  cy.exportConfig();
});

Then('a new contact is created with identical properties', () => {
  cy.getIframeBody().contains(`${contacts.default.alias}_1`).should('exist');
  cy.getIframeBody().contains(`${contacts.default.alias}_1`).click();
  cy.waitForElementInIframe('#main-content', 'input[name="contact_alias"]');

  cy.getIframeBody()
    .find('input[name="contact_alias"]')
    .should('have.value', `${contacts.default.alias}_1`);
  cy.getIframeBody()
    .find('input[name="contact_name"]')
    .should('have.value', `${contacts.default.name}_1`);
  cy.getIframeBody()
    .find('input[name="contact_email"]')
    .should('have.value', contacts.default.email);
  cy.getIframeBody()
    .find('input[name="contact_pager"]')
    .should('have.value', contacts.default.pager);
  cy.getIframeBody().find('#contact_template_id').should('have.value', '19');
  cy.checkLegacyRadioButton(contacts.default.isNotificationsEnabled);
});

When('the user deletes the configured contact', () => {
  checkContactFromListing();
  cy.getIframeBody().find('select[name="o1"').select('Delete');
  cy.wait('@getTimeZone');
  cy.exportConfig();
});

Then('the deleted contact is not visible anymore on the contact page', () => {
  cy.getIframeBody().contains(contacts.default.name).should('not.exist');
});
