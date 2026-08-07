import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { INTERCEPTORS } from 'fixtures/shared/constants/interceptors';

import groups from '../../../fixtures/users/contact.json';
import {
  contactGroupsPage,
  expectRowToggleUnchecked,
  searchListing,
  toggleListingRow,
  visitListing,
  waitForListingRefresh
} from '../common';

const checkFirstContactGroupFromListing = () => {
  visitListing(contactGroupsPage);
  cy.checkListingRow(groups.defaultGroup.name);
};

beforeEach(() => {
  cy.startContainers();
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
    url: `${INTERCEPTORS.pages.centreon_configuration_contact}*`
  }).as('getContacts');
  cy.intercept({
    method: 'GET',
    url: `${INTERCEPTORS.pages.centreon_administration_aclgroup}*`
  }).as('getACLGroups');
  cy.intercept({
    method: 'POST',
    url: '**/ajaxContactGroupToggle.php'
  }).as('toggleContactGroup');
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

When('a contact group is configured', () => {
  visitListing(contactGroupsPage);
  cy.getIframeBody().find('a.cl-btn-add').click();
  cy.addOrUpdateContactGroup(groups.defaultGroup);
});

When('the user updates the properties of the configured contact group', () => {
  waitForListingRefresh();
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('a', groups.defaultGroup.name)
    .click();
  cy.addOrUpdateContactGroup(groups.GroupForUpdate);
});

Then('the properties are updated', () => {
  waitForListingRefresh();
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('a', groups.GroupForUpdate.name)
    .click();
  cy.wait('@getTimeZone');
  cy.waitForElementInIframe('#main-content', 'iframe#cfSidePanelFrame');
  cy.getSidePanelBody()
    .find('input[name="cg_name"]')
    .should('have.value', groups.GroupForUpdate.name);
  cy.getSidePanelBody()
    .find('input[name="cg_alias"]')
    .should('have.value', groups.GroupForUpdate.alias);
  cy.getSidePanelBody()
    .find('#cg_contacts')
    .find('option:selected')
    .then((selectedOptions) => {
      const selectedTexts = Array.from(selectedOptions).map(
        (option) => option.textContent
      );
      expect(selectedTexts).to.include.members([
        groups.defaultGroup.linkedContact,
        groups.GroupForUpdate.linkedContact
      ]);
    });
  cy.getSidePanelBody()
    .find('#cg_acl_groups')
    .find('option:selected')
    .then((selectedOptions) => {
      const selectedTexts = Array.from(selectedOptions).map(
        (option) => option.textContent
      );
      expect(selectedTexts).to.include.members(['ALL']);
    });
  cy.checkLegacyRadioButton(groups.GroupForUpdate.status);
  cy.getSidePanelBody()
    .find('textarea[name="cg_comment"]')
    .should('have.value', groups.GroupForUpdate.comment);
});

When('the user duplicates the configured contact group', () => {
  checkFirstContactGroupFromListing();
  cy.runListingBulkAction('Duplicate');
  cy.wait('@getTimeZone');
  cy.exportConfig();
});

Then('a new contact group is created with identical properties', () => {
  waitForListingRefresh();
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('a', `${groups.defaultGroup.name}_1`)
    .click();
  cy.wait('@getTimeZone');
  cy.waitForElementInIframe('#main-content', 'iframe#cfSidePanelFrame');
  cy.getSidePanelBody()
    .find('input[name="cg_name"]')
    .should('have.value', `${groups.defaultGroup.name}_1`);
  cy.getSidePanelBody()
    .find('input[name="cg_alias"]')
    .should('have.value', groups.defaultGroup.alias);
  cy.getSidePanelBody()
    .find('#cg_contacts')
    .find('option:selected')
    .then((selectedOptions) => {
      const selectedTexts = Array.from(selectedOptions).map(
        (option) => option.textContent
      );
      expect(selectedTexts).to.include.members([
        groups.defaultGroup.linkedContact
      ]);
    });
  cy.getSidePanelBody()
    .find('#cg_acl_groups')
    .find('option:selected')
    .then((selectedOptions) => {
      const selectedTexts = Array.from(selectedOptions).map(
        (option) => option.textContent
      );
      expect(selectedTexts).to.include.members(['ALL']);
    });
  cy.checkLegacyRadioButton(groups.defaultGroup.status);
  cy.getSidePanelBody()
    .find('textarea[name="cg_comment"]')
    .should('have.value', groups.defaultGroup.comment);
});

When('the user deletes the configured contact group', () => {
  checkFirstContactGroupFromListing();
  cy.runListingBulkAction('Delete');
  cy.wait('@getTimeZone');
  cy.exportConfig();
});

Then(
  'the deleted contact group is not visible anymore on the contact group page',
  () => {
    waitForListingRefresh();
    cy.getIframeBody()
      .find('#clTableBody')
      .contains(groups.defaultGroup.name)
      .should('not.exist');
  }
);

// ---------------------------------------------------------------------------
// Modernized listing
// ---------------------------------------------------------------------------

When('the user displays the contact groups listing', () => {
  visitListing(contactGroupsPage);
});

Then('the listing table is displayed with contact group rows', () => {
  cy.getIframeBody().find('table.cl-listing-table').should('exist');
  cy.getIframeBody()
    .find('#clTableBody')
    .contains(groups.defaultGroup.name)
    .should('exist');
});

When('the user searches for the configured contact group', () => {
  searchListing(groups.defaultGroup.name);
});

Then('only the matching contact group is displayed', () => {
  cy.getIframeBody()
    .find('#clTableBody tr')
    .each(($row) => {
      cy.wrap($row).invoke('text').should('include', groups.defaultGroup.name);
    });
});

When('the user clicks the toggle to disable the contact group', () => {
  toggleListingRow(groups.defaultGroup.name);
  cy.wait('@toggleContactGroup');
});

Then('the contact group toggle switches to disabled', () => {
  cy.get('@toggleContactGroup').its('response.statusCode').should('eq', 200);
  expectRowToggleUnchecked(groups.defaultGroup.name);
});
