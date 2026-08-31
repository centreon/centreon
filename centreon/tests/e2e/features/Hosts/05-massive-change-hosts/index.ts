import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { INTERCEPTORS } from 'fixtures/shared/constants/interceptors';

import { formSections, formSelectors } from '../common';

const hostNames = ['host2', 'host3', 'host4'];

/**
 * Both the Mass Change form and the per-host form open in the side panel, which
 * is an iframe nested inside the page iframe.
 */
const checkHostsProperties = (hostName: string) => {
  cy.openListingRowForm(hostName);
  cy.getSidePanelBody()
    .find('span[id="select2-host_location-container"]')
    .should('have.attr', 'title', 'Africa/Algiers');

  cy.getSidePanelBody()
    .find('span[id="select2-command_command_id-container"]')
    .should('have.attr', 'title', 'check_http');
  cy.getSidePanelBody()
    .find('input[name="host_retry_check_interval"]')
    .should('have.value', '3');
  cy.getSidePanelBody().find(formSelectors.saveButton).first().click();
  cy.wait('@getTimeZone');
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
    url: INTERCEPTORS.ajax.host_listing
  }).as('getHostListing');
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

Given('several hosts have been created with mandatory properties', () => {
  hostNames.forEach((name) => {
    cy.addHost({
      hostGroup: 'Linux-Servers',
      name,
      template: 'generic-host'
    }).applyPollerConfiguration();
  });
});

When('the user has applied "Mass Change" operation on several hosts', () => {
  cy.openHostsListing();

  // Tick the fixture hosts one by one. The header checkbox ticks every row the
  // table shows, so the platform's own hosts would be carried into the mass
  // change and silently receive these values too.
  hostNames.forEach((name) => {
    cy.tickListingRow(name);
  });

  // Mass Change carries the checked ids into the side panel; unlike Delete and
  // Duplicate it is not gated by a confirmation modal.
  cy.openListingMassChange();

  cy.getSidePanelBody()
    .find('span[id="select2-host_location-container"]', { timeout: 20_000 })
    .click();
  cy.getSidePanelBody().find('div[title="Africa/Algiers"]').click();
  cy.getSidePanelBody()
    .find('span[id="select2-command_command_id-container"]')
    .click();
  cy.getSidePanelBody().find('div[title="check_http"]').click();

  // Retry Check Interval lives in Scheduling, which the form ships collapsed.
  cy.expandFormSection(formSections.scheduling);
  cy.getSidePanelBody()
    .find('input[name="host_retry_check_interval"]')
    .clear()
    .type('3');
  cy.getSidePanelBody().find(formSelectors.massChangeSubmit).first().click();
  cy.wait('@getTimeZone');
  cy.exportConfig();
});

Then('all the selected hosts are updated with the same values', () => {
  hostNames.forEach((name) => {
    cy.openHostsListing();
    checkHostsProperties(name);
  });
});
