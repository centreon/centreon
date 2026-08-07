import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { INTERCEPTORS } from 'fixtures/shared/constants/interceptors';

import { contactsPage, listingAlias, visitListing } from '../common';

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
  cy.stopContainers();
});

const checkCreatedContactFromListing = () => {
  visitListing(contactsPage, listingAlias.contacts);
  cy.checkListingRow('user-with-access-to-allmodules');
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
  cy.runListingBulkAction('Duplicate');
  cy.wait('@getTimeZone');
  cy.exportConfig();
});

When('the admin user deletes the original non-admin contact', () => {
  cy.reload();
  checkCreatedContactFromListing();
  cy.runListingBulkAction('Delete');
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
  cy.getIframeBody()
    .find('a[href*="contact_id"]')
    .filter((_index, element) => {
      return (
        Cypress.$(element).text().trim() === 'user-with-access-to-allmodules'
      );
    })
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
