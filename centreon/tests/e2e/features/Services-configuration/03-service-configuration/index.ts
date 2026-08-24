import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { INTERCEPTORS } from 'fixtures/shared/constants/interceptors';
import { PAGES } from 'fixtures/shared/constants/pages';

beforeEach(() => {
  cy.startContainers();
  cy.intercept({
    method: 'GET',
    url: INTERCEPTORS.pages.time_zone
  }).as('getTimeZone');
  cy.intercept({
    method: 'GET',
    url: INTERCEPTORS.api.navigation_list
  }).as('getNavigationList');
});

Given('a user is logged in Centreon', () => {
  cy.loginByTypeOfUser({ jsonName: 'admin' });
});

When('a service is configured', () => {
  cy.setUserTokenApiV1();
  cy.addHost({
    hostGroup: 'Linux-Servers',
    name: 'host_1',
    template: 'generic-host'
  }).addService({
    activeCheckEnabled: false,
    host: 'host_1',
    maxCheckAttempts: 1,
    name: 'test',
    template: 'Ping-LAN'
  });
});

const serviceName = 'test';
const modifiedName = 'test_modified';

// Single-select fields are select2 widgets rendered in the side panel document.
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

// Queries only — find and filter are replayed, so the chain survives the timed
// refresh replacing the table. The row is matched on the exact link text, and
// its checkbox is visibility:hidden behind the md-checkbox label, hence the
// forced click. Picking the row by name is also why these steps no longer
// narrow the listing down to one host first: every search re-fetches, and a box
// ticked while that is in flight is dropped by the re-render.
const selectListingRow = (name: string): void => {
  cy.getIframeBody()
    .find('#clTableBody tr')
    .filter((_index, row) =>
      Array.from(row.querySelectorAll('a')).some(
        (link) => link.textContent?.trim() === name
      )
    )
    .find('.cl-col-picker input[type="checkbox"]')
    .click({ force: true });
};

When('the user changes the properties of a service', () => {
  cy.visitListingAndWait(PAGES.configuration.servicesByHostLegacy);
  cy.openListingRowForm(serviceName)
    .find('input[name="service_description"]', { timeout: 20_000 })
    .should('be.visible')
    .clear()
    .type(modifiedName);
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
  cy.waitForElementInIframe('#main-content', 'table.cl-listing-table');
  cy.waitForListingRefresh();
  cy.openListingRowForm(modifiedName)
    .find('input[name="service_description"]', { timeout: 20_000 })
    .should('have.value', modifiedName);
  cy.getListingSidePanelBody()
    .find('select#service_template_model_stm_id')
    .contains('Ping-WAN')
    .should('exist');
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

When('the user duplicates a service', () => {
  cy.visitListingAndWait(PAGES.configuration.servicesByHostLegacy);
  selectListingRow(serviceName);
  cy.runListingBulkAction('m');
  cy.exportConfig();
});

Then('the new service has the same properties', () => {
  cy.waitForElementInIframe('#main-content', 'table.cl-listing-table');
  cy.waitForListingRefresh();
  cy.openListingRowForm(`${serviceName}_1`)
    .find('input[name="service_description"]', { timeout: 20_000 })
    .should('have.value', `${serviceName}_1`);
  cy.getListingSidePanelBody()
    .find('select#service_template_model_stm_id')
    .contains('Ping-LAN')
    .should('exist');
});

When('the user deletes a service', () => {
  cy.visitListingAndWait(PAGES.configuration.servicesByHostLegacy);
  selectListingRow(serviceName);
  cy.runListingBulkAction('d');
});

Then('the deleted service is not displayed in the service list', () => {
  cy.wait('@getTimeZone');
});

afterEach(() => {
  cy.stopContainers();
});
