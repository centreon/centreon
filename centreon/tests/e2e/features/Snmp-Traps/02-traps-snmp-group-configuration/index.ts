import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { INTERCEPTORS } from 'fixtures/shared/constants/interceptors';

import data from '../../../fixtures/snmp-traps/snmp-trap.json';
import {
  CreateOrUpdateTrapGroup,
  listingTable,
  listingTableBody
} from '../common';

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
    url: `${INTERCEPTORS.pages.centreon_configuration_trap}&action=list*`
  }).as('listTraps');
  cy.intercept({
    method: 'GET',
    url: INTERCEPTORS.ajax.trap_groups_listing
  }).as('listTrapGroupsAjax');
});

afterEach(() => {
  cy.stopContainers();
});

Given('an admin user is logged in a Centreon server', () => {
  cy.loginByTypeOfUser({ jsonName: 'admin' });
});

Given('a trap group is configured', () => {
  cy.openTrapGroupsListing();
  cy.openTrapsAddForm('input[name="name"]');
  CreateOrUpdateTrapGroup(data.snmpGroup1);
  cy.wait('@getTimeZone');
  cy.exportConfig();
});

Given('a second trap group is configured', () => {
  cy.openTrapGroupsListing();
  cy.openTrapsAddForm('input[name="name"]');
  CreateOrUpdateTrapGroup(data.snmpGroup2);
  cy.wait('@getTimeZone');
  cy.exportConfig();
});

// Scenario: The trap groups listing loads through the AJAX framework
When('the user opens the trap groups listing', () => {
  cy.openTrapGroupsListing();
  cy.wait('@listTrapGroupsAjax');
});

Then(
  'the AJAX listing table is displayed with the configured trap group',
  () => {
    cy.get('@listTrapGroupsAjax').its('response.statusCode').should('eq', 200);
    cy.getIframeBody().find(listingTable).should('exist');
    cy.getIframeBody()
      .find(listingTableBody)
      .contains(data.snmpGroup1.name)
      .should('exist');
  }
);

// Scenario: The search filters the trap groups by name
When('the user searches for the first trap group', () => {
  cy.searchInTrapsListing(data.snmpGroup1.name);
});

Then('only the matching trap group is displayed', () => {
  cy.getIframeBody()
    .find(listingTableBody)
    .contains(data.snmpGroup1.name)
    .should('exist');
  cy.getIframeBody()
    .find(listingTableBody)
    .contains(data.snmpGroup2.name)
    .should('not.exist');
});

// Scenario: Edit one existing trap group
When('the user changes the properties of a trap group', () => {
  cy.openTrapGroupsListing();
  cy.openTrapsRowForm(data.snmpGroup1.name, 'input[name="name"]');
  CreateOrUpdateTrapGroup(data.snmpGroup2);
  cy.wait('@getTimeZone');
  cy.exportConfig();
});

Then('the properties are updated', () => {
  cy.waitForElementInIframe('#main-content', listingTable);
  cy.openTrapsRowForm(data.snmpGroup2.name, 'input[name="name"]');
  cy.getTrapSidePanelBody()
    .find('input[name="name"]')
    .should('have.value', data.snmpGroup2.name);
  cy.getTrapSidePanelBody()
    .find('select[id="traps"]')
    .find('option:selected')
    .then((selectedOptions) => {
      const selectedTexts = Array.from(selectedOptions).map(
        (option) => option.textContent
      );
      expect(selectedTexts).to.include.members([
        data.snmpGroup2.traps[0],
        data.snmpGroup2.traps[1]
      ]);
    });
});

// Scenario: Duplicate one existing trap group
When('the user duplicates a trap group', () => {
  cy.openTrapGroupsListing();
  cy.runTrapsBulkAction(data.snmpGroup1.name, 'Duplicate');
  cy.wait('@getTimeZone');
  cy.exportConfig();
});

Then('the a new trap group is created with identical properties', () => {
  cy.waitForElementInIframe('#main-content', listingTable);
  cy.openTrapsRowForm(`${data.snmpGroup1.name}_1`, 'input[name="name"]');
  cy.getTrapSidePanelBody()
    .find('input[name="name"]')
    .should('have.value', `${data.snmpGroup1.name}_1`);
  cy.getTrapSidePanelBody()
    .find('select[id="traps"]')
    .find('option:selected')
    .then((selectedOptions) => {
      const selectedTexts = Array.from(selectedOptions).map(
        (option) => option.textContent
      );
      expect(selectedTexts).to.include.members([
        data.snmpGroup1.traps[0],
        data.snmpGroup1.traps[1]
      ]);
    });
});

// Scenario: Delete one existing trap group
When('the user deletes a trap group', () => {
  cy.openTrapGroupsListing();
  cy.runTrapsBulkAction(data.snmpGroup1.name, 'Delete');
  cy.wait('@getTimeZone');
  cy.exportConfig();
});

Then(
  'the deleted trap group is not visible anymore on the trap group page',
  () => {
    cy.waitForElementInIframe('#main-content', listingTable);
    cy.getIframeBody()
      .find(listingTableBody)
      .contains(data.snmpGroup1.name)
      .should('not.exist');
  }
);
