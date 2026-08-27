import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { INTERCEPTORS } from 'fixtures/shared/constants/interceptors';
import { PAGES } from 'fixtures/shared/constants/pages';

import data from '../../../fixtures/services/meta_service.json';

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
  cy.intercept({
    method: 'GET',
    url: `${INTERCEPTORS.pages.centreon_configuration_meta}&action=list&*`
  }).as('getListOfMServices');
  cy.intercept({
    method: 'GET',
    url: INTERCEPTORS.ajax.metaservice_dependency_listing
  }).as('getMetaServiceDependencies');
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

Given('some meta services are configured', () => {
  cy.visit(PAGES.configuration.metaServicesLegacy);
  cy.wait('@getTimeZone');
  cy.addMetaService({
    ...data.metaService1,
    maxCheckAttempts: data.metaService1.max_check_attempts
  });
  cy.addMetaService({
    ...data.metaService2,
    maxCheckAttempts: data.metaService2.max_check_attempts
  });
  cy.addMetaService({
    ...data.metaService3,
    maxCheckAttempts: data.metaService3.max_check_attempts
  });
});

Given('a meta service dependency is configured', () => {
  cy.visit('/').url().should('include', '/monitoring/resources');
  cy.visit(PAGES.configuration.metaServicesDependenciesLegacy);
  cy.waitForModernListing();
  // The +Add button opens the form in the side panel, not in #main-content.
  cy.getIframeBody().find('a.cl-btn-add').click();
  cy.addMetaserviceDependency({
    ...data.defaultMetaServiceDep,
    dependentMetaServicesNames: data.defaultMetaServiceDep.dependentMSNames,
    executionFailsOnCritical:
      data.defaultMetaServiceDep.execution_fails_on_critical,
    executionFailsOnNone: data.defaultMetaServiceDep.execution_fails_on_none,
    executionFailsOnOk: data.defaultMetaServiceDep.execution_fails_on_ok,
    executionFailsOnPending:
      data.defaultMetaServiceDep.execution_fails_on_pending,
    executionFailsOnUnknown:
      data.defaultMetaServiceDep.execution_fails_on_unknown,
    executionFailsOnWarning:
      data.defaultMetaServiceDep.execution_fails_on_warning,
    metaServicesNames: data.defaultMetaServiceDep.metaServicesNames,
    notificationFailsOnCritical:
      data.defaultMetaServiceDep.notification_fails_on_critical,
    notificationFailsOnNone:
      data.defaultMetaServiceDep.notification_fails_on_none,
    notificationFailsOnOk: data.defaultMetaServiceDep.notification_fails_on_ok,
    notificationFailsOnPending:
      data.defaultMetaServiceDep.notification_fails_on_pending,
    notificationFailsOnUnknown:
      data.defaultMetaServiceDep.notification_fails_on_unknown,
    notificationFailsOnWarning:
      data.defaultMetaServiceDep.notification_fails_on_warning,
    parentRelationship: data.defaultMetaServiceDep.parent_relationship
  });
});

const unmatchedSearchTerm = 'no-such-dependency';

When('the user opens the meta service dependencies listing', () => {
  cy.visit(PAGES.configuration.metaServicesDependenciesLegacy);
  cy.waitForModernListing();
});

Then(
  'the AJAX listing table is displayed with the configured meta service dependency',
  () => {
    cy.getIframeBody().find('table.cl-listing-table').should('exist');
    cy.getIframeBody()
      .find('#clTableBody')
      .contains(data.defaultMetaServiceDep.name)
      .should('exist');
  }
);

When('the user searches for a term matching no meta service dependency', () => {
  // Live search (debounced AJAX) — there is no submit button, the table
  // refreshes as the user types.
  cy.getIframeBody().find('#clSearchInput').clear().type(unmatchedSearchTerm);
  cy.wait('@getMetaServiceDependencies');
});

Then('no meta service dependency is displayed', () => {
  cy.listingShouldBeEmpty();
});

When('the user searches for the configured meta service dependency', () => {
  cy.getIframeBody()
    .find('#clSearchInput')
    .clear()
    .type(data.defaultMetaServiceDep.name);
  cy.wait('@getMetaServiceDependencies');
});

Then('only the matching meta service dependency is displayed', () => {
  cy.listingShouldContainOnly(data.defaultMetaServiceDep.name);
});

Then('the pagination information shows the total count', () => {
  cy.getIframeBody()
    .find('#clPaginationTop')
    .invoke('text')
    .should('match', /\d+-\d+ of \d+/);
});

When(
  'the user changes the properties of the configured meta service dependency',
  () => {
    cy.openSidePanelForm(
      data.defaultMetaServiceDep.name,
      'input[name="dep_name"]'
    );
    cy.updateMetaserviceDependency({
      ...data.MetaServiceDep1,
      dependentMetaServicesNames: data.MetaServiceDep1.dependentMSNames,
      executionFailsOnCritical:
        data.MetaServiceDep1.execution_fails_on_critical,
      executionFailsOnNone: data.MetaServiceDep1.execution_fails_on_none,
      executionFailsOnOk: data.MetaServiceDep1.execution_fails_on_ok,
      executionFailsOnPending: data.MetaServiceDep1.execution_fails_on_pending,
      executionFailsOnUnknown: data.MetaServiceDep1.execution_fails_on_unknown,
      executionFailsOnWarning: data.MetaServiceDep1.execution_fails_on_warning,
      metaServicesNames: data.MetaServiceDep1.metaServicesNames,
      notificationFailsOnCritical:
        data.MetaServiceDep1.notification_fails_on_critical,
      notificationFailsOnNone: data.MetaServiceDep1.notification_fails_on_none,
      notificationFailsOnOk: data.MetaServiceDep1.notification_fails_on_ok,
      notificationFailsOnPending:
        data.MetaServiceDep1.notification_fails_on_pending,
      notificationFailsOnUnknown:
        data.MetaServiceDep1.notification_fails_on_unknown,
      notificationFailsOnWarning:
        data.MetaServiceDep1.notification_fails_on_warning,
      parentRelationship: data.MetaServiceDep1.parent_relationship
    });
  }
);

Then('the properties are updated', () => {
  cy.openSidePanelForm(data.MetaServiceDep1.name, 'input[name="dep_name"]');
  cy.getSidePanelBody()
    .find('input[name="dep_name"]')
    .should('have.value', data.MetaServiceDep1.name);
  cy.getSidePanelBody()
    .find('input[name="dep_description"]')
    .should('have.value', data.MetaServiceDep1.description);
  cy.getSidePanelBody().find('#eOk').should('be.checked');
  cy.getSidePanelBody().find('#nCritical').should('be.checked');
  // Selections show up as select2 chips, not as selected <option> elements.
  cy.getSidePanelBody()
    .find(`.select2-selection__choice[title="${data.metaService2.name}"]`)
    .should('exist');
  cy.getSidePanelBody()
    .find(`.select2-selection__choice[title="${data.metaService1.name}"]`)
    .should('exist');
  cy.getSidePanelBody()
    .find('textarea[name="dep_comment"]')
    .should('have.value', data.MetaServiceDep1.comment);
});

When('the user duplicates the configured meta service dependency', () => {
  cy.runListingBulkAction(data.defaultMetaServiceDep.name, 'Duplicate');
  cy.wait('@getTimeZone');
});

Then(
  'a new meta service dependency is created with identical properties',
  () => {
    cy.openSidePanelForm(
      `${data.defaultMetaServiceDep.name}_1`,
      'input[name="dep_name"]'
    );
    cy.getSidePanelBody()
      .find('input[name="dep_name"]')
      .should('have.value', `${data.defaultMetaServiceDep.name}_1`);
    cy.getSidePanelBody()
      .find('input[name="dep_description"]')
      .should('have.value', data.defaultMetaServiceDep.description);
    cy.getSidePanelBody().find('#eUnknown').should('be.checked');
    cy.getSidePanelBody().find('#nUnknown').should('be.checked');
    cy.getSidePanelBody()
      .find(`.select2-selection__choice[title="${data.metaService1.name}"]`)
      .should('exist');
    cy.getSidePanelBody()
      .find(`.select2-selection__choice[title="${data.metaService2.name}"]`)
      .should('exist');
    cy.getSidePanelBody()
      .find(`.select2-selection__choice[title="${data.metaService3.name}"]`)
      .should('exist');
    cy.getSidePanelBody()
      .find('textarea[name="dep_comment"]')
      .should('have.value', data.defaultMetaServiceDep.comment);
  }
);

When('the user deletes the configured meta service dependency', () => {
  cy.runListingBulkAction(data.defaultMetaServiceDep.name, 'Delete');
  cy.wait('@getTimeZone');
});

Then(
  'the deleted meta service dependency is not displayed in the list of meta service dependencies',
  () => {
    // The duplicate created earlier is "<name>_1", so the deleted row has to be
    // matched on its exact name rather than as a substring.
    cy.listingRowShouldNotExist(data.defaultMetaServiceDep.name);
  }
);
