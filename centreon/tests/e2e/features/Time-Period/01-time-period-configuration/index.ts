import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { INTERCEPTORS } from 'fixtures/shared/constants/interceptors';

import {
  navigateToTimePeriodsAndInitiateAddition,
  searchTimePeriod,
  setTimePeriod,
  submitForm,
  visitTimePeriodsListing
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
    url: '**/ajaxTimeperiodListing.php*'
  }).as('getTimePeriodListing');
});

Given('a user is logged in Centreon', () => {
  cy.loginByTypeOfUser({ jsonName: 'admin' });
});

Given('several time periods exist', () => {
  // The default install already ships 24x7 and nonworkhours.
  cy.addTimePeriod({
    alias: 'Test TP Alpha',
    name: 'tp_test_alpha'
  });
  cy.addTimePeriod({
    alias: 'Test TP Beta',
    name: 'tp_test_beta'
  });
});

When(
  'a user creates a time period with separated holidays dates excluded',
  () => {
    navigateToTimePeriodsAndInitiateAddition();
    setTimePeriod();
  }
);

Then('all properties of my time period are saved', () => {
  submitForm();
});

When('a user creates a time period with a range of dates to exclude', () => {
  navigateToTimePeriodsAndInitiateAddition();
  cy.getSidePanelBody().find('input[name="tp_name"]').type('timePeriodName');
  cy.getSidePanelBody().find('input[name="tp_alias"]').type('timePeriodAlias');
  cy.getSidePanelBody().find('input[name="tp_sunday"]').type('14:00-16:00');
  cy.getSidePanelBody()
    .find('input[name="tp_monday"]')
    .type('07:00-12:00,13:00-18:00');
  cy.getSidePanelBody().find('input[name="tp_tuesday"]').type('07:00-18:00');
  cy.getSidePanelBody()
    .find('input[name="tp_wednesday"]')
    .type('07:00-12:00,13:00-17:00');
  cy.getSidePanelBody().find('input[name="tp_thursday"]').type('14:00-16:00');
  cy.getSidePanelBody().find('input[name="tp_friday"]').type('07:00-18:00');
  cy.getSidePanelBody().find('input[name="tp_saturday"]').type('10:00-16:00');
  cy.getSidePanelBody().find('a[href="#cf-sec-exceptions"]').click();
  cy.getSidePanelBody().contains('+ Add new entry').click();
  cy.getSidePanelBody().find('input#exceptionInput_0').type('august 1 - 31');
  cy.getSidePanelBody().find('input#exceptionTimerange_0').type('00:00-24:00');
});

Then('all properties of my time period are saved with the exclusions', () => {
  submitForm();
});

Given('an existing time period', () => {
  navigateToTimePeriodsAndInitiateAddition();
  setTimePeriod();
  submitForm();
});

When('a user duplicates the time period', () => {
  visitTimePeriodsListing();
  cy.runListingBulkAction(
    'timePeriodName',
    'Duplicate',
    'Duplicate time period'
  );
});

Then(
  'a new time period is created with identical properties except the name',
  () => {
    visitTimePeriodsListing();
    cy.getIframeBody()
      .find('#clTableBody')
      .contains('a', 'timePeriodName_1')
      .click();
    cy.waitForElementInIframe('#main-content', 'iframe#cfSidePanelFrame');

    const expectedValues = {
      tp_alias: 'timePeriodAlias',
      tp_friday: '07:00-18:00',
      tp_monday: '07:00-12:00,13:00-18:00',
      tp_name: 'timePeriodName_1',
      tp_saturday: '10:00-16:00',
      tp_sunday: '14:00-16:00',
      tp_thursday: '14:00-16:00',
      tp_tuesday: '07:00-18:00',
      tp_wednesday: '07:00-12:00,13:00-17:00'
    };

    Object.entries(expectedValues).forEach(([field, value]) => {
      cy.getSidePanelBody()
        .find(`input[name="${field}"]`)
        .should('have.value', value);
    });
  }
);

When('a user deletes the time period', () => {
  visitTimePeriodsListing();
  cy.runListingBulkAction('timePeriodName', 'Delete', 'Delete time period');
});

Then('the time period disappears from the time periods list', () => {
  visitTimePeriodsListing();
  // Anchored: contains() is substring-based and 'timePeriodName' would still
  // match the 'timePeriodName_1' left by the duplication scenario.
  cy.getIframeBody()
    .find('#clTableBody')
    .contains(/^timePeriodName$/)
    .should('not.exist');
});

When('the user navigates to the time periods listing', () => {
  visitTimePeriodsListing();
});

Then('the listing table is displayed with time period rows', () => {
  cy.getIframeBody().find('table.cl-listing-table').should('exist');
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('tp_test_alpha')
    .should('exist');
});

When('the user searches for a specific time period', () => {
  searchTimePeriod('tp_test_alpha');
});

Then('only the matching time period is displayed', () => {
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('tp_test_alpha')
    .should('exist');
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('tp_test_beta')
    .should('not.exist');
});

Then('the pagination info shows the total count', () => {
  cy.getIframeBody()
    .find('#clPaginationTop .cl-page-info')
    .invoke('text')
    .should('match', /\d+-\d+ of \d+/);
});

When('the user clicks on a time period name', () => {
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('a', 'tp_test_alpha')
    .click();
});

Then('the time period form opens in the side panel', () => {
  cy.waitForElementInIframe('#main-content', 'iframe#cfSidePanelFrame');
  cy.getSidePanelBody()
    .find('input[name="tp_name"]')
    .should('have.value', 'tp_test_alpha');
});

When('the user navigates back to the time periods listing', () => {
  // visitTimePeriodsListing already waits for the listing fetch.
  visitTimePeriodsListing();
});

Then('the search field still contains the search term', () => {
  cy.getIframeBody()
    .find('#clSearchInput')
    .should('have.value', 'tp_test_alpha');
});

afterEach(() => {
  // The scenarios create these; without an explicit cleanup the suite only
  // stays green because the containers are recreated between runs.
  for (const name of [
    'tp_test_alpha',
    'tp_test_beta',
    'timePeriodName',
    'timePeriodName_1'
  ]) {
    cy.executeActionViaClapi({
      bodyContent: { action: 'DEL', object: 'TP', values: name },
      failOnError: false
    });
  }
  cy.stopContainers();
});
