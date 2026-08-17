import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { INTERCEPTORS } from 'fixtures/shared/constants/interceptors';

import {
  contactsPage,
  listingAlias,
  searchListing,
  visitListing
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
  // The fixture also creates an ACL menu and group, which would collide on the
  // next run just as the contact does.
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

const checkCreatedContactFromListing = () => {
  visitListing(contactsPage, listingAlias.contacts);
  // The listing pages at 30 rows and this contact sorts past the first page,
  // so narrow it down before ticking the row.
  searchListing('user-with-access-to-allmodules', listingAlias.contacts);
};

Given('an admin user is logged in a Centreon server', () => {
  cy.loginByTypeOfUser({
    jsonName: 'admin',
    loginViaApi: false
  });
});

When('the admin user creates a non admin contact', () => {
  cy.executeCommandsViaClapi(
    'resources/clapi/config-ACL/non-admin-with-access-to-allmodules.json'
  );
});

When('the admin user duplicates the newly created non-admin contact', () => {
  checkCreatedContactFromListing();
  cy.runListingBulkAction(
    'user-with-access-to-allmodules',
    'Duplicate',
    'Duplicate contact'
  );
  cy.wait('@getTimeZone');
  cy.exportConfig();
});

When('the admin user deletes the original non-admin contact', () => {
  cy.reload();
  checkCreatedContactFromListing();
  cy.runListingBulkAction(
    'user-with-access-to-allmodules',
    'Delete',
    'Delete contact'
  );
  cy.wait('@getTimeZone');
  cy.exportConfig();
});

Then('the duplicated contact is displayed in the user list', () => {
  cy.getIframeBody()
    .contains('a', 'user-with-access-to-allmodules_1')
    .should('be.visible');
});

Then('the deleted contact should not be visible in the user list', () => {
  cy.getIframeBody()
    .contains('a', 'user-with-access-to-allmodules_1')
    .should('be.visible');
  // contact_id now lives in data-panel-url, not in href: the old selector
  // matched nothing and the assertion passed unconditionally.
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('a', /^user-with-access-to-allmodules$/)
    .should('not.exist');
});

Then(
  'the admin can log in to Centreon Web with the duplicated contact account',
  () => {
    cy.logout();
    cy.loginByDuplicatedOrUpdatedUser(
      'user-with-access-to-allmodules',
      'user-with-access-to-allmodules_1'
    );
    cy.url().should('include', '/centreon/monitoring/resources');
  }
);
