import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { INTERCEPTORS } from 'fixtures/shared/constants/interceptors';

import contacts from '../../../fixtures/users/contact.json';
import {
  contactsPage,
  expectRowToggleUnchecked,
  expectSegmentedChoice,
  listingAlias,
  searchListing,
  toggleListingRow,
  visitListing,
  waitForListingXhr
} from '../common';

const listingTestContacts = ['test_contact_alpha', 'test_contact_beta'];
let isAdmin = true;
let contactPageIndex = 3;
let accessGroup = 'user-ACLGROUP';
const checkContactFromListing = () => {
  cy.visitContactsPage(contactPageIndex);
  waitForListingXhr(listingAlias.contacts);
  cy.checkListingRow(contacts.default.alias);
};

/** Close the form side panel and come back to the listing. */
const closeSidePanel = () => {
  cy.getIframeBody().find('button.cf-side-panel-close').click();
  waitForListingXhr(listingAlias.contacts);
};

before(() => {
  cy.startContainers();
});

beforeEach(() => {
  cy.intercept({
    method: 'GET',
    url: '**/ajaxContactListing.php*'
  }).as('getContactListing');
  cy.setUserTokenApiV1()
    .executeCommandsViaClapi(
      'resources/clapi/config-ACL/contacts-management-acl-user.json'
    )
    .executeCommandsViaClapi(
      'resources/clapi/config-ACL/contacts-management-acl-user-readonly-rights.json'
    );
  cy.intercept({
    method: 'GET',
    url: INTERCEPTORS.api.navigation_list
  }).as('getNavigationList');
  cy.intercept({
    method: 'GET',
    url: INTERCEPTORS.pages.time_zone
  }).as('getTimeZone');
  cy.intercept({
    method: 'GET',
    url: `${INTERCEPTORS.pages.centreon_administration_aclgroup}&action=list*`
  }).as('getAclGroups');
  cy.intercept({
    method: 'POST',
    url: '**/ajaxContactToggle.php'
  }).as('toggleContact');
});

afterEach(() => {
  cy.setUserTokenApiV1()
    .executeCommandsViaClapi(
      'resources/clapi/config-ACL/delete-contacts-management-acl-user-readonly-rights.json'
    )
    .executeCommandsViaClapi(
      'resources/clapi/config-ACL/delete-contacts-management-acl-user.json'
    );
  for (const contact of [contacts.default, contacts.contactForUpdate]) {
    for (const contactAlias of [
      contact.alias,
      `${contact.alias}_1`,
      `${contact.alias}-1`
    ]) {
      cy.executeActionViaClapi({
        bodyContent: {
          action: 'DEL',
          object: 'CONTACT',
          values: contactAlias
        },
        failOnError: false
      });
    }
  }
  for (const alias of listingTestContacts) {
    for (const contactAlias of [alias, `${alias}_1`]) {
      cy.executeActionViaClapi({
        bodyContent: {
          action: 'DEL',
          object: 'CONTACT',
          values: contactAlias
        },
        failOnError: false
      });
    }
  }
  cy.logoutViaAPI({ failOnError: false });
});

after(() => {
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
  cy.visitContactsPage(contactPageIndex);
  waitForListingXhr(listingAlias.contacts);
  cy.getIframeBody().find('a.cl-btn-add').click();
  cy.addOrUpdateContact(contacts.default);
  if (!isAdmin) {
    // Add the contact to the ACL Group of the connected non-admin user
    cy.getSidePanelBody().contains('a', 'Centreon Authentication').click();
    // Click outside the form
    cy.get('body').click(0, 0);
    cy.getSidePanelBody()
      .find('ul[class="select2-selection__rendered"]')
      .eq(3)
      .click();
    cy.wait('@getAclGroups');
    cy.getSidePanelBody().contains('div', accessGroup).click();
  }
  cy.getSidePanelBody()
    .find('input.btc.bt_success[name^="submit"]')
    .eq(0)
    .click();
  cy.wait('@getTimeZone');
  cy.exportConfig();
});

When('the user updates some contact properties', () => {
  waitForListingXhr(listingAlias.contacts);
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('a', contacts.default.alias)
    .click();
  cy.addOrUpdateContact(contacts.contactForUpdate);
  cy.getSidePanelBody()
    .find('input.btc.bt_success[name^="submit"]')
    .eq(0)
    .click();
  cy.wait('@getTimeZone');
  cy.exportConfig();
});

Then('these properties are updated', () => {
  waitForListingXhr(listingAlias.contacts);
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('a', contacts.contactForUpdate.alias)
    .click();
  cy.wait('@getTimeZone');
  cy.waitForElementInIframe('#main-content', 'iframe#cfSidePanelFrame');
  cy.getSidePanelBody()
    .find('input[id="contact_alias"]')
    .should('have.value', contacts.contactForUpdate.alias);
  cy.getSidePanelBody()
    .find('input[id="contact_name"]')
    .should('have.value', contacts.contactForUpdate.name);
  cy.getSidePanelBody()
    .find('input[id="contact_email"]')
    .should('have.value', contacts.contactForUpdate.email);
  cy.getSidePanelBody()
    .find('input[id="contact_pager"]')
    .should('have.value', contacts.contactForUpdate.pager);
  cy.getSidePanelBody().find('#contact_template_id').should('have.value', '19');
  expectSegmentedChoice(
    'contact_enable_notifications',
    contacts.contactForUpdate.isNotificationsEnabled
  );
});

When('the user duplicates the configured contact', () => {
  checkContactFromListing();
  cy.runListingBulkAction('Duplicate');
  cy.wait('@getTimeZone');
  cy.exportConfig();
});

Then('a new contact is created with identical properties', () => {
  waitForListingXhr(listingAlias.contacts);
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('a', `${contacts.default.alias}_1`)
    .click();
  cy.waitForElementInIframe('#main-content', 'iframe#cfSidePanelFrame');

  cy.getSidePanelBody()
    .find('input[name="contact_alias"]')
    .should('have.value', `${contacts.default.alias}_1`);
  cy.getSidePanelBody()
    .find('input[name="contact_name"]')
    .should('have.value', `${contacts.default.name}_1`);
  cy.getSidePanelBody()
    .find('input[name="contact_email"]')
    .should('have.value', contacts.default.email);
  cy.getSidePanelBody()
    .find('input[name="contact_pager"]')
    .should('have.value', contacts.default.pager);
  cy.getSidePanelBody().find('#contact_template_id').should('have.value', '19');
  expectSegmentedChoice(
    'contact_enable_notifications',
    contacts.default.isNotificationsEnabled
  );
});

When('the user deletes the configured contact', () => {
  checkContactFromListing();
  cy.runListingBulkAction('Delete');
  cy.wait('@getTimeZone');
  cy.exportConfig();
});

Then('the deleted contact is not visible anymore on the contact page', () => {
  waitForListingXhr(listingAlias.contacts);
  cy.getIframeBody()
    .find('#clTableBody')
    .contains(contacts.default.name)
    .should('not.exist');
});

Given('the contact configuration page is displayed', () => {
  cy.visitContactsPage(contactPageIndex);
});

When('the user clicks on the contact creation button', () => {
  waitForListingXhr(listingAlias.contacts);
  cy.getIframeBody().find('a.cl-btn-add').click();
});

When('he does not fill in the {string} field', (field: string) => {
  cy.waitForElementInIframe('#main-content', 'iframe#cfSidePanelFrame');
  // Fill All the required fields first
  cy.getSidePanelBody().within(() => {
    cy.get('input[id="contact_alias"]').type(
      `{selectAll}{backspace}${contacts.default.alias}`
    );
    cy.get('input[id="contact_name"]').type(
      `{selectAll}{backspace}${contacts.default.name}`
    );
    cy.get('input[id="contact_email"]').type(
      `{selectAll}{backspace}${contacts.default.email}`
    );
  });

  // Remove the content of one of the required field that we have already filled
  switch (field) {
    case 'Alias':
      cy.getSidePanelBody().find('input[id="contact_alias"]').clear();
      break;
    case 'Full Name':
      cy.getSidePanelBody().find('input[id="contact_name"]').clear();
      break;
    case 'Email':
      cy.getSidePanelBody().find('input[id="contact_email"]').clear();
      break;
    default:
      throw new Error(`Unknown field: ${field}`);
  }
  // Click to save the contact
  cy.getSidePanelBody()
    .find('input.btc.bt_success[name^="submit"]')
    .eq(0)
    .click();
  cy.wait('@getTimeZone');
});

Then('the user is not brought back to the contact configuration page', () => {
  // Check that the add form is still open in the side panel.
  cy.getSidePanelBody()
    .contains('a', 'General Information')
    .should('be.visible');
});

Then(
  'he sees an error displayed above the {string} field with a message {string}',
  (_field: string, message: string) => {
    cy.getSidePanelBody().contains('font', message).should('exist');
  }
);

Then('the contact is not created', () => {
  closeSidePanel();
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('a', contacts.default.name)
    .should('not.exist');
});

When('the {string} user clicks on a this contact', () => {
  waitForListingXhr(listingAlias.contacts);
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('a', contacts.default.alias)
    .click();
});

When('the {string} clears the contents of a mandatory field', () => {
  cy.waitForElementInIframe('#main-content', 'iframe#cfSidePanelFrame');
  cy.getSidePanelBody().find('input[id="contact_alias"]').clear();
  cy.getSidePanelBody().find('input[id="contact_name"]').clear();
  cy.getSidePanelBody().find('input[id="contact_email"]').clear();
  // Click to save the changes
  cy.getSidePanelBody()
    .find('input.btc.bt_success[name^="submit"]')
    .eq(0)
    .click();
  cy.wait('@getTimeZone');
});

Then('the {string} sees an error displayed in the form', () => {
  cy.getSidePanelBody().contains('font', 'Compulsory Alias').should('exist');
  cy.getSidePanelBody().contains('Compulsory Name').should('be.visible');
  cy.getSidePanelBody().contains('Valid Email').should('be.visible');
});

Then('the contact is not updated', () => {
  closeSidePanel();
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('a', contacts.default.alias)
    .should('be.visible');
});

Given(
  'a non-admin user with READ ONLY rights is configured by the admin',
  () => {
    cy.loginByTypeOfUser({
      jsonName: 'admin',
      loginViaApi: false
    });
    contactPageIndex = 3;
    isAdmin = false;
    accessGroup = 'user-ACLGROUP-READ';
    // The configuration of the non-admin user with READ ONLY rights is already done on the beforeEach step
  }
);

When(
  'the non-admin user with READ ONLY rights is logged in a Centreon Server',
  () => {
    // Logout from the admin account
    cy.logout();
    //Log in as a non-admin user with READ ONLY rights
    cy.loginByTypeOfUser({
      jsonName: 'contacts-management-acl-user-readonly-rights',
      loginViaApi: false
    });
    contactPageIndex = 0;
  }
);

When(
  'the non-admin user with READ ONLY rights displays contacts configuration',
  () => {
    cy.visitContactsPage(contactPageIndex);
    waitForListingXhr(listingAlias.contacts);
    // Check that the page is on READ ONLY mod
    cy.getIframeBody().find('a.cl-btn-add').should('not.exist');
  }
);

When(
  'the non-admin user with READ ONLY rights clicks on the configured contact',
  () => {
    waitForListingXhr(listingAlias.contacts);
    cy.getIframeBody()
      .find('#clTableBody')
      .contains('a', contacts.default.alias)
      .click();
    // Wait until the form is visible inside the side panel
    cy.waitForElementInIframe('#main-content', 'iframe#cfSidePanelFrame');
    cy.getSidePanelBody().contains('a', 'General Information').should('exist');
  }
);

Then('the form of this contact is displayed in READ ONLY mode', () => {
  // #tab1 was the legacy tab container; the modernized form lays the same
  // fields out in a cf-sec-* section. A frozen QuickForm renders its values as
  // text and keeps only hidden persistence inputs. Scoped to named inputs: the
  // status toggle is a cosmetic checkbox with no name, mirroring a hidden radio
  // group, so it is not one of the fields this assertion is about.
  cy.getSidePanelBody()
    .find('#cf-sec-info input[name]:not([class*="select"])')
    .should('exist')
    .each((input) => {
      cy.wrap(input).should('have.attr', 'type', 'hidden');
    });
});

// ---------------------------------------------------------------------------
// Modernized listing
// ---------------------------------------------------------------------------

Given('test contacts exist', () => {
  // alias is what CLAPI resolves a DEL by, so keep it equal to the name:
  // the afterEach cleanup below deletes these by the very same value.
  cy.addContact({
    alias: 'test_contact_alpha',
    email: 'alpha@test.com',
    name: 'test_contact_alpha',
    password: 'Centreon!2021'
  });
  cy.addContact({
    alias: 'test_contact_beta',
    email: 'beta@test.com',
    name: 'test_contact_beta',
    password: 'Centreon!2021'
  });
});

When('the user displays the contacts listing', () => {
  visitListing(contactsPage, listingAlias.contacts);
});

When('the user displays the contacts listing again', () => {
  visitListing(contactsPage, listingAlias.contacts);
});

Then('the listing table is displayed with contact rows', () => {
  cy.getIframeBody().find('table.cl-listing-table').should('exist');
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('test_contact_alpha')
    .should('exist');
});

When('the user searches for a specific contact', () => {
  searchListing('test_contact_alpha', listingAlias.contacts);
});

Then('only the matching contact is displayed', () => {
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('test_contact_alpha')
    .should('exist');
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('test_contact_beta')
    .should('not.exist');
});

When('the user clicks the toggle to disable a contact', () => {
  toggleListingRow('test_contact_alpha');
  cy.wait('@toggleContact');
});

Then('the contact toggle switches to disabled', () => {
  expectRowToggleUnchecked('test_contact_alpha');
});

Then('the toggle response is successful', () => {
  cy.get('@toggleContact').its('response.statusCode').should('eq', 200);
  cy.get('@toggleContact')
    .its('response.body')
    .should('have.property', 'success', true);
});

Then('the admin user toggle is disabled', () => {
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('a', 'admin')
    .parents('tr')
    .find('.cl-toggle input[type="checkbox"]')
    .should('be.disabled');
});

Then('the contacts search field still contains the search term', () => {
  cy.getIframeBody()
    .find('#clSearchInput')
    .should('have.value', 'test_contact_alpha');
});
