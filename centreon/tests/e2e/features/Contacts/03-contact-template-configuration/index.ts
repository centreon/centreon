import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { INTERCEPTORS } from 'fixtures/shared/constants/interceptors';

import contactTemplates from '../../../fixtures/users/contact.json';
import {
  contactTemplatesPage,
  expectRowToggleUnchecked,
  expectSegmentedChoice,
  listingAlias,
  searchListing,
  toggleListingRow,
  visitListing,
  waitForListingXhr
} from '../common';

const visitContactTemplatesListing = () => {
  visitListing(contactTemplatesPage, listingAlias.contactTemplates);
};

beforeEach(() => {
  cy.intercept({
    method: 'GET',
    url: '**/ajaxContactTemplateListing.php*'
  }).as('getContactTemplateListing');
  cy.startContainers();
  cy.intercept({
    method: 'POST',
    url: '**/ajaxContactTemplateToggle.php'
  }).as('toggleContactTemplate');
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
    url: `${INTERCEPTORS.pages.centreon_configuration_timeperiod}*`
  }).as('getTimePeriods');
  cy.intercept({
    method: 'GET',
    url: `${INTERCEPTORS.pages.centreon_configuration_command}*`
  }).as('getNotCommands');
});

afterEach(() => {
  // The scenarios create these; without an explicit cleanup the suite only
  // stays green because the containers are recreated between runs.
  for (const base of [
    contactTemplates.defaultTemplate.alias,
    contactTemplates.templateForUpdate.alias
  ]) {
    for (const alias of [base, `${base}_1`, `${base}-1`]) {
      cy.executeActionViaClapi({
        bodyContent: { action: 'DEL', object: 'CONTACTTPL', values: alias },
        failOnError: false
      });
    }
  }
  cy.stopContainers();
});

Given('an admin user is logged in a Centreon server', () => {
  cy.loginByTypeOfUser({
    jsonName: 'admin',
    loginViaApi: false
  });
});

When('a contact template is configured', () => {
  visitListing(contactTemplatesPage, listingAlias.contactTemplates);
  cy.getIframeBody().find('a.cl-btn-add').click();
  cy.addOrUpdateContactTemplate({
    ...contactTemplates.defaultTemplate,
    notCommands: contactTemplates.defaultTemplate.NotCommands,
    usedContactTemplate: contactTemplates.defaultTemplate.usedCTemplate
  });
});

When(
  'the user updates the properties of the configured contact template',
  () => {
    waitForListingXhr(listingAlias.contactTemplates);
    cy.getIframeBody()
      .find('#clTableBody')
      .contains('a', contactTemplates.defaultTemplate.alias)
      .click();
    cy.addOrUpdateContactTemplate({
      ...contactTemplates.templateForUpdate,
      notCommands: contactTemplates.templateForUpdate.NotCommands,
      usedContactTemplate: contactTemplates.templateForUpdate.usedCTemplate
    });
  }
);

Then('the properties are updated', () => {
  waitForListingXhr(listingAlias.contactTemplates);
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('a', contactTemplates.templateForUpdate.alias)
    .click();
  cy.wait('@getTimeZone');
  cy.waitForElementInIframe('#main-content', 'iframe#cfSidePanelFrame');
  cy.getSidePanelBody()
    .find('input[name="contact_alias"]')
    .should('have.value', contactTemplates.templateForUpdate.alias);
  cy.getSidePanelBody()
    .find('input[name="contact_name"]')
    .should('have.value', contactTemplates.templateForUpdate.name);
  cy.getSidePanelBody()
    .find('select[name="contact_template_id"]')
    .should('have.value', contactTemplates.templateForUpdate.usedCTemplate);
  cy.getSidePanelBody()
    .find('select[name="default_page"]')
    .should('have.value', contactTemplates.templateForUpdate.defaultPage);
  expectSegmentedChoice(
    'contact_enable_notifications',
    contactTemplates.templateForUpdate.isNotEnabled
  );
  cy.getSidePanelBody().find('input[id="hDown"]').should('not.be.checked');
  cy.getSidePanelBody()
    .find('span[id="select2-timeperiod_tp_id-container"]')
    .should(
      'have.attr',
      'title',
      contactTemplates.templateForUpdate.timePeriod
    );
  cy.getSidePanelBody()
    .find('#contact_hostNotifCmds')
    .find('option:selected')
    .then((selectedOptions) => {
      const selectedTexts = Array.from(selectedOptions).map(
        (option) => (option as HTMLOptionElement).text
      );
      expect(selectedTexts).to.include.members([
        contactTemplates.defaultTemplate.NotCommands,
        contactTemplates.templateForUpdate.NotCommands
      ]);
    });
  cy.getSidePanelBody().find('input[id="sWarning"]').should('not.be.checked');
  cy.getSidePanelBody()
    .find('span[id="select2-timeperiod_tp_id2-container"]')
    .should(
      'have.attr',
      'title',
      contactTemplates.templateForUpdate.timePeriod
    );
  cy.getSidePanelBody()
    .find('#contact_svNotifCmds')
    .find('option:selected')
    .then((selectedOptions) => {
      const selectedTexts = Array.from(selectedOptions).map(
        (option) => (option as HTMLOptionElement).text
      );
      expect(selectedTexts).to.include.members([
        contactTemplates.defaultTemplate.NotCommands,
        contactTemplates.templateForUpdate.NotCommands
      ]);
    });
});

When('the user duplicates the configured contact template', () => {
  visitContactTemplatesListing();
  cy.runListingBulkAction(
    contactTemplates.defaultTemplate.alias,
    'Duplicate',
    'Duplicate contact template'
  );
  cy.wait('@getTimeZone');
  cy.exportConfig();
});

Then('a new contact template is created with identical properties', () => {
  waitForListingXhr(listingAlias.contactTemplates);
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('a', `${contactTemplates.defaultTemplate.alias}_1`)
    .click();
  cy.wait('@getTimeZone');
  cy.waitForElementInIframe('#main-content', 'iframe#cfSidePanelFrame');
  cy.getSidePanelBody()
    .find('input[name="contact_alias"]')
    .should('have.value', `${contactTemplates.defaultTemplate.alias}_1`);
  cy.getSidePanelBody()
    .find('input[name="contact_name"]')
    .should('have.value', `${contactTemplates.defaultTemplate.name}_1`);
  cy.getSidePanelBody()
    .find('select[name="contact_template_id"]')
    .should('have.value', contactTemplates.defaultTemplate.usedCTemplate);
  cy.getSidePanelBody()
    .find('select[name="default_page"]')
    .should('have.value', contactTemplates.defaultTemplate.defaultPage);
  expectSegmentedChoice(
    'contact_enable_notifications',
    contactTemplates.defaultTemplate.isNotEnabled
  );
  cy.getSidePanelBody().find('input[id="hDown"]').should('be.checked');
  cy.getSidePanelBody()
    .find('span[id="select2-timeperiod_tp_id-container"]')
    .should('have.attr', 'title', contactTemplates.defaultTemplate.timePeriod);
  cy.getSidePanelBody()
    .find('#contact_hostNotifCmds')
    .find('option:selected')
    .then((selectedOptions) => {
      const selectedTexts = Array.from(selectedOptions).map(
        (option) => (option as HTMLOptionElement).text
      );
      expect(selectedTexts).to.include.members([
        contactTemplates.defaultTemplate.NotCommands
      ]);
    });
  cy.getSidePanelBody().find('input[id="sWarning"]').should('be.checked');
  cy.getSidePanelBody()
    .find('span[id="select2-timeperiod_tp_id2-container"]')
    .should('have.attr', 'title', contactTemplates.defaultTemplate.timePeriod);
  cy.getSidePanelBody()
    .find('#contact_svNotifCmds')
    .find('option:selected')
    .then((selectedOptions) => {
      const selectedTexts = Array.from(selectedOptions).map(
        (option) => (option as HTMLOptionElement).text
      );
      expect(selectedTexts).to.include.members([
        contactTemplates.defaultTemplate.NotCommands
      ]);
    });
});

When('the user deletes the configured contact template', () => {
  visitContactTemplatesListing();
  cy.runListingBulkAction(
    contactTemplates.defaultTemplate.alias,
    'Delete',
    'Delete contact template'
  );
  cy.wait('@getTimeZone');
  cy.exportConfig();
});

Then(
  'the deleted contact template is not visible anymore on the contact template page',
  () => {
    waitForListingXhr(listingAlias.contactTemplates);
    // Anchored: contains() is substring-based and would still match the
    // '<alias>_1' left by the duplication scenario.
    cy.getIframeBody()
      .find('#clTableBody')
      .contains(new RegExp(`^${contactTemplates.defaultTemplate.alias}$`))
      .should('not.exist');
  }
);

// ---------------------------------------------------------------------------
// Modernized listing
// ---------------------------------------------------------------------------

When('the user displays the contact templates listing', () => {
  visitListing(contactTemplatesPage, listingAlias.contactTemplates);
});

Then('the listing table is displayed with contact template rows', () => {
  cy.getIframeBody().find('table.cl-listing-table').should('exist');
  cy.getIframeBody()
    .find('#clTableBody')
    .contains(contactTemplates.defaultTemplate.name)
    .should('exist');
});

When('the user searches for the configured contact template', () => {
  // The endpoint filters on contact_name, as the legacy page did — searching by
  // alias would match nothing even though the alias is the first column.
  searchListing(
    contactTemplates.defaultTemplate.name,
    listingAlias.contactTemplates
  );
});

Then('only the matching contact template is displayed', () => {
  // A retried length assertion, not .each(): .each() snapshots the row list and
  // would iterate the pre-search rows.
  cy.getIframeBody().find('#clTableBody tr').should('have.length', 1);
  cy.getIframeBody()
    .find('#clTableBody')
    .contains(contactTemplates.defaultTemplate.name)
    .should('exist');
});

When('the user clicks the toggle to disable the contact template', () => {
  toggleListingRow(contactTemplates.defaultTemplate.alias);
  cy.wait('@toggleContactTemplate');
});

Then('the contact template toggle switches to disabled', () => {
  cy.get('@toggleContactTemplate').its('response.statusCode').should('eq', 200);
  expectRowToggleUnchecked(contactTemplates.defaultTemplate.alias);
});
