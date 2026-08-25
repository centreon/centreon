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

// Bulk actions go through the More actions menu (cy.runListingBulkAction),
// which runs the path a user takes: menu, confirmation modal, submit.
const runBulkActionOn = (name: string, action: string): void => {
  // One query, not a chain: the listing auto-refreshes every 30s and a
  // contains -> parents -> find chain loses its subject when the table is
  // replaced mid-way. Cypress retries a single find() atomically.
  cy.getIframeBody()
    .find(
      `#clTableBody tr:contains("${name}") .cl-col-picker input[type="checkbox"]`
    )
    .click({ force: true });
  cy.runListingBulkAction(action);
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
  runBulkActionOn(serviceGroupName, 'm');
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
  runBulkActionOn(serviceGroupName, 'd');
});

Then(
  'the deleted service group is not displayed in the service group list',
  () => {
    cy.waitForElementInIframe('#main-content', 'table.cl-listing-table');
    cy.waitForListingRefresh();
    cy.waitForListingToDrop(serviceGroupName);
  }
);

afterEach(() => {
  cy.stopContainers();
});
