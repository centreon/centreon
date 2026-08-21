import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { INTERCEPTORS } from 'fixtures/shared/constants/interceptors';
import { PAGES } from 'fixtures/shared/constants/pages';

import data from '../../../fixtures/services/service.json';

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

Then('a service group is configured', () => {
  cy.setUserTokenApiV1();
  cy.addHostGroup({
    name: data.hostGroups.hostGroup1.name
  })
    .addHost({
      activeCheckEnabled: false,
      checkCommand: 'check_centreon_cpu',
      hostGroup: data.hostGroups.hostGroup1.name,
      name: data.hosts.host1.name,
      template: 'generic-host'
    })
    .addService({
      activeCheckEnabled: false,
      host: data.hosts.host1.name,
      maxCheckAttempts: 1,
      name: data.services.service1.name,
      template: 'Ping-LAN'
    })
    .addServiceGroup({
      hostsAndServices: [[data.hosts.host1.name, data.services.service1.name]],
      name: data.service_group.service1.name
    });
});

const serviceGroupName = data.service_group.service1.name;
const modifiedName = 'test_modified';
const modifiedAlias = 'description_modified';
const linkedService = 'Centreon-Server - Memory';

// The listing is AJAX-driven and its bulk actions go through the hidden o1
// select, whose onchange has to be overridden to reach the legacy dispatcher.
const runBulkActionOn = (name: string, action: string): void => {
  cy.getIframeBody()
    .find('#clTableBody')
    .contains(name)
    .parents('tr')
    // The row checkbox is visibility:hidden behind its md-checkbox label.
    .find('.cl-col-picker input[type="checkbox"]')
    .click({ force: true });
  cy.getIframeBody()
    .find('select[name="o1"]')
    .invoke(
      'attr',
      'onchange',
      "javascript: { setO(this.form.elements['o1'].value); this.form.submit(); }"
    );
  cy.getIframeBody().find('select[name="o1"]').select(action, { force: true });
};

When('the user changes the properties of a service group', () => {
  cy.visitListingAndWait(PAGES.configuration.servicesGroupsLegacy);
  cy.openListingRowForm(serviceGroupName)
    .find('input[name="sg_name"]', { timeout: 20_000 })
    .should('be.visible')
    .clear()
    .type(modifiedName);
  cy.getListingSidePanelBody()
    .find('input[name="sg_alias"]')
    .clear()
    .type(modifiedAlias);
  cy.getListingSidePanelBody()
    .find('select#sg_hServices')
    .next()
    .find('.select2-selection')
    .click({ force: true });
  cy.getListingSidePanelBody()
    .find('.select2-results__option', { timeout: 20_000 })
    .contains(linkedService)
    .click({ force: true });
  cy.getListingSidePanelBody()
    .find('input.btc.bt_success[name^="submit"]')
    .first()
    .click();
});

Then('the properties of the service group are updated', () => {
  cy.visitListingAndWait(PAGES.configuration.servicesGroupsLegacy);
  cy.openListingRowForm(modifiedName)
    .find('input[name="sg_name"]', { timeout: 20_000 })
    .should('have.value', modifiedName);
  cy.getListingSidePanelBody()
    .find('input[name="sg_alias"]')
    .should('have.value', modifiedAlias);
  cy.getListingSidePanelBody()
    .find('select#sg_hServices')
    .contains(linkedService)
    .should('exist');
});

When('the user duplicates a service group', () => {
  cy.visitListingAndWait(PAGES.configuration.servicesGroupsLegacy);
  runBulkActionOn(serviceGroupName, 'Duplicate');
});

Then('the new service group has the same properties', () => {
  cy.waitForElementInIframe('#main-content', 'table.cl-listing-table');
  cy.waitForListingRefresh();
  cy.openListingRowForm(`${serviceGroupName}_1`)
    .find('input[name="sg_name"]', { timeout: 20_000 })
    .should('have.value', `${serviceGroupName}_1`);
  cy.getListingSidePanelBody()
    .find('input[name="sg_alias"]')
    .should('have.value', serviceGroupName);
  cy.getListingSidePanelBody()
    .find('select#sg_hServices')
    .contains(data.hosts.host1.name)
    .should('exist');
});

When('the user deletes a service group', () => {
  cy.visitListingAndWait(PAGES.configuration.servicesGroupsLegacy);
  runBulkActionOn(serviceGroupName, 'Delete');
});

Then(
  'the deleted service group is not displayed in the service group list',
  () => {
    cy.waitForElementInIframe('#main-content', 'table.cl-listing-table');
    cy.waitForListingRefresh();
    cy.getIframeBody()
      .find('#clTableBody')
      .contains(serviceGroupName)
      .should('not.exist');
  }
);

afterEach(() => {
  cy.stopContainers();
});
