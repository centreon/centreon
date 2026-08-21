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
const hostName = 'host_1';

// The Host filter sits in the advanced-filters popover, which has to be opened
// before its fields are reachable.
const filterOnHost = (host: string): void => {
  cy.getIframeBody()
    .find('.cl-adv-icon-btn[data-cl-adv-panel="clAdvPanel"]')
    .click();
  cy.getIframeBody().find('#clSearchH').clear().type(host);
  cy.getIframeBody().find('#clSearchBtn').click();
  cy.waitForListingRefresh();
};

// Bulk actions go through the hidden o1 select, whose onchange has to be
// overridden to reach the legacy dispatcher.
const runBulkActionOnSelection = (action: string): void => {
  cy.getIframeBody()
    .find('select[name="o1"]')
    .invoke(
      'attr',
      'onchange',
      "javascript: { setO(this.form.elements['o1'].value); this.form.submit(); }"
    );
  cy.getIframeBody().find('select[name="o1"]').select(action, { force: true });
};

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

When('the user changes the properties of a service', () => {
  cy.visitListingAndWait(PAGES.configuration.servicesByHostLegacy);
  cy.openListingRowForm(serviceName)
    .find('input[name="service_description"]', { timeout: 20_000 })
    .should('be.visible')
    .clear()
    .type(modifiedName);
  selectFormOption('service_template_model_stm_id', 'Ping-WAN');
  cy.getListingSidePanelBody().contains('a', 'Notifications').click();
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
  cy.getListingSidePanelBody().contains('a', 'Notifications').click();
  cy.getListingSidePanelBody()
    .find('#timeperiod_tp_id2')
    .find('option:selected')
    .should('have.length', 1)
    .and('have.text', '24x7');
  cy.getListingSidePanelBody().find('#notifC').should('be.checked');
});

When('the user duplicates a service', () => {
  cy.visitListingAndWait(PAGES.configuration.servicesByHostLegacy);
  filterOnHost(hostName);
  cy.getIframeBody().find('#checkall').click({ force: true });
  runBulkActionOnSelection('Duplicate');
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
  // Narrowing on the host then selecting everything replaces the per-row host
  // match: the listing groups its rows, so the host name is only rendered on
  // the first row of each group.
  filterOnHost(hostName);
  cy.getIframeBody().find('#checkall').click({ force: true });
  runBulkActionOnSelection('Delete');
});

Then('the deleted service is not displayed in the service list', () => {
  cy.wait('@getTimeZone');
});

afterEach(() => {
  cy.stopContainers();
});
