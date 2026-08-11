import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { INTERCEPTORS } from 'fixtures/shared/constants/interceptors';

import contacts from '../../../fixtures/users/contact.json';
import {
  contactsPage,
  listingAlias,
  searchListing,
  visitListing,
  waitForListingXhr
} from '../common';

beforeEach(() => {
  cy.intercept({
    method: 'GET',
    url: '**/ajaxContactListing.php*'
  }).as('getContactListing');
  cy.startContainers();
  cy.intercept({
    method: 'GET',
    url: INTERCEPTORS.api.navigation_list
  }).as('getNavigationList');
  cy.intercept({
    method: 'GET',
    url: INTERCEPTORS.pages.time_zone
  }).as('getTimeZone');
});

afterEach(() => {
  // The suite creates this contact; without an explicit cleanup it only stays
  // green because the containers are recreated between runs.
  for (const alias of [
    'user-with-access-to-allmodules',
    'user-with-access-to-allmodules_1'
  ]) {
    cy.executeActionViaClapi({
      bodyContent: { action: 'DEL', object: 'CONTACT', values: alias },
      failOnError: false
    });
  }
  cy.executeActionViaClapi({
    bodyContent: {
      action: 'DEL',
      object: 'ACLMENU',
      values: 'name-non-admin-ACLMENU'
    },
    failOnError: false
  });
  cy.executeActionViaClapi({
    bodyContent: {
      action: 'DEL',
      object: 'ACLGROUP',
      values: 'name-non-admin-ACLGROUP'
    },
    failOnError: false
  });
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
    visitListing(contactsPage, listingAlias.contacts);
    // The listing pages at 30 rows and this contact sorts past the first page.
    searchListing('user-with-access-to-allmodules', listingAlias.contacts);
    cy.getIframeBody()
      .find('#clTableBody')
      .contains('a', 'user-with-access-to-allmodules')
      .click();
    cy.addOrUpdateContact(contacts.contactWithSpecialAlias);
    cy.getSidePanelBody()
      .find('input.btc.bt_success[name^="submit"]')
      .eq(0)
      .click();
    cy.wait('@getTimeZone');
    cy.exportConfig();
  }
);

Then(
  'the new record is displayed in the users list with the new alias value',
  () => {
    // The contact was just renamed, so the listing is still filtered on the old
    // alias; search again on the new one.
    searchListing(
      contacts.contactWithSpecialAlias.alias,
      listingAlias.contacts
    );
    cy.getIframeBody()
      .find('#clTableBody')
      .contains('a', contacts.contactWithSpecialAlias.alias)
      .should('be.visible')
      .click();
    cy.waitForElementInIframe('#main-content', 'iframe#cfSidePanelFrame');
    cy.getSidePanelBody()
      .find('input[id="contact_alias"]')
      .should('have.value', contacts.contactWithSpecialAlias.alias);
  }
);

Given('the contact alias contains an accent', () => {
  visitListing(contactsPage, listingAlias.contacts);
  searchListing('user-with-access-to-allmodules', listingAlias.contacts);
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('a', 'user-with-access-to-allmodules')
    .click();
  cy.addOrUpdateContact(contacts.contactWithSpecialAlias);
  cy.getSidePanelBody()
    .find('input.btc.bt_success[name^="submit"]')
    .eq(0)
    .click();
  cy.wait('@getTimeZone');
  cy.exportConfig();
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
