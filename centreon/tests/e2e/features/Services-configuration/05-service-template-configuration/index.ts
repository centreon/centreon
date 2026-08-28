import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { INTERCEPTORS } from 'fixtures/shared/constants/interceptors';
import { PAGES } from 'fixtures/shared/constants/pages';

beforeEach(() => {
  cy.startContainers();
  cy.intercept({
    method: 'GET',
    url: INTERCEPTORS.pages.time_zone
  }).as('getUserTimezone');
  cy.intercept({
    method: 'GET',
    url: INTERCEPTORS.api.navigation_list
  }).as('getNavigationList');
});

Given('a user is logged in Centreon', () => {
  cy.loginByTypeOfUser({ jsonName: 'admin' });
});

const templateName = 'service_template';
const modifiedAlias = 'service_template_modified';
const modifiedDescription = 'template_desp_modified';

const waitForTemplatesListing = (): void => {
  cy.waitForElementInIframe('#main-content', 'table.cl-listing-table');
  cy.waitForListingRefresh();
};

// Bulk actions go through the More actions menu (cy.runListingBulkAction),
// which runs the path a user takes: menu, confirmation modal, submit.
const runBulkActionOn = (name: string, action: string): void => {
  cy.getIframeBody()
    .find(
      `#clTableBody tr:contains("${name}") .cl-col-picker input[type="checkbox"]`
    )
    .click({ force: true });
  cy.runListingBulkAction(action);
};

// Single-select fields are select2 widgets: open the selection, then pick the
// option from the results rendered in the same (side panel) document.
const selectFormOption = (selectId: string, option: string): void => {
  cy.getListingSidePanelBody()
    .find(`select#${selectId}`)
    .next()
    .find('.select2-selection')
    .click({ force: true });
  cy.getListingSidePanelBody()
    .find('.select2-results__option', { timeout: 20_000 })
    .contains(option)
    .click({ force: true });
};

Then('a service template is configured', () => {
  cy.addServiceTemplate({
    name: templateName,
    template: 'generic-service'
  });
  cy.visitListingAndWait(PAGES.configuration.servicesTemplatesLegacy);
  cy.openListingRowForm(templateName)
    .find('input[name="service_alias"]', { timeout: 20_000 })
    .should('be.visible')
    .clear()
    .type(templateName);
  cy.getListingSidePanelBody()
    .find('input.btc.bt_success[name^="submit"]')
    .first()
    .click();
});

When('the user changes the properties of a service template', () => {
  waitForTemplatesListing();
  cy.openListingRowForm(templateName)
    .find('input[name="service_alias"]', { timeout: 20_000 })
    .should('be.visible')
    .clear()
    .type(modifiedAlias);
  cy.getListingSidePanelBody()
    .find('input[name="service_description"]')
    .clear()
    .type(modifiedDescription);
  selectFormOption('service_template_model_stm_id', 'Ping-WAN');
  cy.getListingSidePanelBody()
    .find('.cf-tab-nav a[href="#cf-sec-notif"]')
    .click();
  selectFormOption('timeperiod_tp_id2', '24x7');
  cy.getListingSidePanelBody().find('#notifC').click({ force: true });
  cy.getListingSidePanelBody()
    .find('input.btc.bt_success[name^="submit"]')
    .first()
    .click();
});

Then('the properties are updated', () => {
  waitForTemplatesListing();
  cy.openListingRowForm(modifiedDescription)
    .find('input[name="service_alias"]', { timeout: 20_000 })
    .should('have.value', modifiedAlias);
  cy.getListingSidePanelBody()
    .find('input[name="service_description"]')
    .should('have.value', modifiedDescription);
  cy.getListingSidePanelBody()
    .find('select#service_template_model_stm_id')
    .find('option:selected')
    .should('have.length', 1)
    .and('have.text', 'Ping-WAN');
  cy.getListingSidePanelBody()
    .find('.cf-tab-nav a[href="#cf-sec-notif"]')
    .click();
  cy.getListingSidePanelBody()
    .find('#timeperiod_tp_id2')
    .find('option:selected')
    .should('have.length', 1)
    .and('have.text', '24x7');
  cy.getListingSidePanelBody().find('#notifC').should('be.checked');
});

When('the user duplicates a service template', () => {
  waitForTemplatesListing();
  runBulkActionOn(templateName, 'm');
});

Then('the new service template has the same properties', () => {
  waitForTemplatesListing();
  cy.openListingRowForm(`${templateName}_1`)
    .find('input[name="service_alias"]', { timeout: 20_000 })
    .should('have.value', templateName);
  cy.getListingSidePanelBody()
    .find('input[name="service_description"]')
    .should('have.value', `${templateName}_1`);
});

When('the user deletes a service template', () => {
  waitForTemplatesListing();
  runBulkActionOn(templateName, 'd');
});

Then('the deleted service template is not displayed in the list', () => {
  waitForTemplatesListing();
  cy.waitForListingToDrop(templateName);
});

afterEach(() => {
  cy.stopContainers();
});
