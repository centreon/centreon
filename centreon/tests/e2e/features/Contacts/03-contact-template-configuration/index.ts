import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { INTERCEPTORS } from 'fixtures/shared/constants/interceptors';

import contactTemplates from '../../../fixtures/users/contact.json';
import {
  contactTemplatesPage,
  expectRowToggleUnchecked,
  searchListing,
  toggleListingRow,
  visitListing,
  waitForListingRefresh
} from '../common';

const checkContactTemplateFromListing = (contactTemplateName: string) => {
  visitListing(contactTemplatesPage);
  cy.checkListingRow(contactTemplateName);
};

beforeEach(() => {
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
  cy.stopContainers();
});

Given('an admin user is logged in a Centreon server', () => {
  cy.loginByTypeOfUser({
    jsonName: 'admin',
    loginViaApi: false
  });
});

When('a contact template is configured', () => {
  visitListing(contactTemplatesPage);
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
    waitForListingRefresh();
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
  waitForListingRefresh();
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
  cy.checkLegacyRadioButton(contactTemplates.templateForUpdate.isNotEnabled);
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
  checkContactTemplateFromListing(contactTemplates.defaultTemplate.alias);
  cy.runListingBulkAction('Duplicate');
  cy.wait('@getTimeZone');
  cy.exportConfig();
});

Then('a new contact template is created with identical properties', () => {
  waitForListingRefresh();
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
  cy.checkLegacyRadioButton(contactTemplates.defaultTemplate.isNotEnabled);
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
  checkContactTemplateFromListing(contactTemplates.defaultTemplate.alias);
  cy.runListingBulkAction('Delete');
  cy.wait('@getTimeZone');
  cy.exportConfig();
});

Then(
  'the deleted contact template is not visible anymore on the contact template page',
  () => {
    waitForListingRefresh();
    cy.getIframeBody()
      .find('#clTableBody')
      .contains(contactTemplates.defaultTemplate.alias)
      .should('not.exist');
  }
);

// ---------------------------------------------------------------------------
// Modernized listing
// ---------------------------------------------------------------------------

When('the user displays the contact templates listing', () => {
  visitListing(contactTemplatesPage);
});

Then('the listing table is displayed with contact template rows', () => {
  cy.getIframeBody().find('table.cl-listing-table').should('exist');
  cy.getIframeBody()
    .find('#clTableBody')
    .contains(contactTemplates.defaultTemplate.alias)
    .should('exist');
});

When('the user searches for the configured contact template', () => {
  searchListing(contactTemplates.defaultTemplate.alias);
});

Then('only the matching contact template is displayed', () => {
  cy.getIframeBody()
    .find('#clTableBody tr')
    .each(($row) => {
      cy.wrap($row)
        .invoke('text')
        .should('include', contactTemplates.defaultTemplate.alias);
    });
});

When('the user clicks the toggle to disable the contact template', () => {
  toggleListingRow(contactTemplates.defaultTemplate.alias);
  cy.wait('@toggleContactTemplate');
});

Then('the contact template toggle switches to disabled', () => {
  cy.get('@toggleContactTemplate').its('response.statusCode').should('eq', 200);
  expectRowToggleUnchecked(contactTemplates.defaultTemplate.alias);
});
