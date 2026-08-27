import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { INTERCEPTORS } from 'fixtures/shared/constants/interceptors';
import { PAGES } from 'fixtures/shared/constants/pages';

import data from '../../../fixtures/host-groups/dependency.json';
import grps from '../../../fixtures/notifications/data-for-notification.json';

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
    url: INTERCEPTORS.ajax.hostgroup_dependency_listing
  }).as('getHostGroupDependencies');
});

afterEach(() => {
  cy.stopContainers();
});

Given('a user is logged in a Centreon server', () => {
  cy.loginByTypeOfUser({
    jsonName: 'admin',
    loginViaApi: false
  });
});

Given('some hosts groups are configured', () => {
  cy.addHostGroup({
    name: grps.hostGroups.hostGroup1.name
  });
  cy.addHostGroup({
    name: grps.hostGroups.hostGroup2.name
  });
});

Given('a host group dependency is configured', () => {
  cy.visit(PAGES.configuration.hostGroupsDependenciesLegacy);
  cy.waitForModernListing();
  // The +Add button opens the form in the side panel, not in #main-content.
  cy.getIframeBody().find('a.cl-btn-add').click();
  cy.addHostGroupDependency({
    comment: data.default.comment,
    dependentHostGroupsNames: data.default.dependentHostGrpsNames,
    description: data.default.description,
    executionFailsOnDown: data.default.execution_fails_on_down,
    executionFailsOnNone: data.default.notification_fails_on_none,
    executionFailsOnOk: data.default.execution_fails_on_ok,
    executionFailsOnPending: data.default.execution_fails_on_pending,
    executionFailsOnUnreachable: data.default.execution_fails_on_unreachable,
    hostGroupsNames: data.default.hostGrpsNames,
    name: data.default.name,
    notificationFailsOnDown: data.default.notification_fails_on_down,
    notificationFailsOnNone: data.default.notification_fails_on_none,
    notificationFailsOnOk: data.default.notification_fails_on_ok,
    notificationFailsOnPending: data.default.notification_fails_on_pending,
    notificationFailsOnUnreachable:
      data.default.notification_fails_on_unreachable,
    parentRelationship: data.default.parent_relationship
  });
});

const unmatchedSearchTerm = 'no-such-dependency';

When('the user opens the host group dependencies listing', () => {
  cy.visit(PAGES.configuration.hostGroupsDependenciesLegacy);
  cy.waitForModernListing();
});

Then(
  'the AJAX listing table is displayed with the configured host group dependency',
  () => {
    cy.getIframeBody().find('table.cl-listing-table').should('exist');
    cy.getIframeBody()
      .find('#clTableBody')
      .contains(data.default.name)
      .should('exist');
  }
);

When('the user searches for a term matching no host group dependency', () => {
  // Live search (debounced AJAX) — there is no submit button, the table
  // refreshes as the user types.
  cy.getIframeBody().find('#clSearchInput').clear().type(unmatchedSearchTerm);
  cy.wait('@getHostGroupDependencies');
});

Then('no host group dependency is displayed', () => {
  cy.listingShouldBeEmpty();
});

When('the user searches for the configured host group dependency', () => {
  cy.getIframeBody().find('#clSearchInput').clear().type(data.default.name);
  cy.wait('@getHostGroupDependencies');
});

Then('only the matching host group dependency is displayed', () => {
  cy.listingShouldContainOnly(data.default.name);
});

Then('the pagination information shows the total count', () => {
  cy.getIframeBody()
    .find('#clPaginationTop')
    .invoke('text')
    .should('match', /\d+-\d+ of \d+/);
});

When('the user changes the properties of a host group dependency', () => {
  cy.openSidePanelForm(data.default.name, 'input[name="dep_name"]');
  cy.updateHostGroupDependency({
    comment: data.HostGrpDependency1.comment,
    dependentHostGroupsNames: data.HostGrpDependency1.dependentHostGrpsNames,
    description: data.HostGrpDependency1.description,
    executionFailsOnDown: data.HostGrpDependency1.execution_fails_on_down,
    executionFailsOnNone: data.HostGrpDependency1.execution_fails_on_none,
    executionFailsOnOk: data.HostGrpDependency1.execution_fails_on_ok,
    executionFailsOnPending: data.HostGrpDependency1.execution_fails_on_pending,
    executionFailsOnUnreachable:
      data.HostGrpDependency1.execution_fails_on_unreachable,
    hostGroupsNames: data.HostGrpDependency1.hostGrpsNames,
    name: data.HostGrpDependency1.name,
    notificationFailsOnDown: data.HostGrpDependency1.notification_fails_on_down,
    notificationFailsOnNone: data.HostGrpDependency1.notification_fails_on_none,
    notificationFailsOnOk: data.HostGrpDependency1.notification_fails_on_ok,
    notificationFailsOnPending:
      data.HostGrpDependency1.notification_fails_on_pending,
    notificationFailsOnUnreachable:
      data.HostGrpDependency1.notification_fails_on_unreachable,
    parentRelationship: data.HostGrpDependency1.parent_relationship
  });
});

Then('the properties are updated', () => {
  cy.openSidePanelForm(data.HostGrpDependency1.name, 'input[name="dep_name"]');
  cy.getSidePanelBody()
    .find('input[name="dep_name"]')
    .should('have.value', data.HostGrpDependency1.name);
  cy.getSidePanelBody()
    .find('input[name="dep_description"]')
    .should('have.value', data.HostGrpDependency1.description);
  cy.getSidePanelBody().find('#eUp').should('be.checked');
  cy.getSidePanelBody().find('#nDown').should('be.checked');
  // Selections show up as select2 chips, not as selected <option> elements.
  cy.getSidePanelBody()
    .find(
      `.select2-selection__choice[title="${data.HostGrpDependency1.hostGrpsNames[0]}"]`
    )
    .should('exist');
  cy.getSidePanelBody()
    .find(
      `.select2-selection__choice[title="${data.HostGrpDependency1.hostGrpsNames[1]}"]`
    )
    .should('exist');
  cy.getSidePanelBody()
    .find(
      `.select2-selection__choice[title="${data.HostGrpDependency1.dependentHostGrpsNames[0]}"]`
    )
    .should('exist');
  cy.getSidePanelBody()
    .find('textarea[name="dep_comment"]')
    .should('have.value', data.HostGrpDependency1.comment);
});

When('the user duplicates a host group dependency', () => {
  cy.runListingBulkAction(data.default.name, 'Duplicate');
  cy.wait('@getTimeZone');
  cy.exportConfig();
});

Then('the new object has the same properties', () => {
  cy.openSidePanelForm(`${data.default.name}_1`, 'input[name="dep_name"]');
  cy.getSidePanelBody()
    .find('input[name="dep_name"]')
    .should('have.value', `${data.default.name}_1`);
  cy.getSidePanelBody()
    .find('input[name="dep_description"]')
    .should('have.value', data.default.description);
  cy.getSidePanelBody().find('#eDown').should('be.checked');
  cy.getSidePanelBody().find('#nPending').should('be.checked');
  cy.getSidePanelBody()
    .find(
      `.select2-selection__choice[title="${data.default.hostGrpsNames[0]}"]`
    )
    .should('exist');
  cy.getSidePanelBody()
    .find(
      `.select2-selection__choice[title="${data.default.dependentHostGrpsNames[0]}"]`
    )
    .should('exist');
  cy.getSidePanelBody()
    .find('textarea[name="dep_comment"]')
    .should('have.value', data.default.comment);
});

When('the user deletes a host group dependency', () => {
  cy.runListingBulkAction(data.default.name, 'Delete');
  cy.wait('@getTimeZone');
  cy.exportConfig();
});

Then('the deleted object is not displayed in the list', () => {
  // The duplicate created earlier is "<name>_1", so the deleted row has to be
  // matched on its exact name rather than as a substring.
  cy.listingRowShouldNotExist(data.default.name);
});
