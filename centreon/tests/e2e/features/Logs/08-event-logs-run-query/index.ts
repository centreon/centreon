import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { INTERCEPTORS } from 'fixtures/shared/constants/interceptors';
import { PAGES } from 'fixtures/shared/constants/pages';

import { openHostFilterDropdown } from '../common';

// Long enough to check the button while the search runs.
const searchDelayInMs = 2000;
// Leaves time for a search that would start late.
const noSearchGraceInMs = 2000;
// Set on the window of the Event Logs page: a reload replaces the window and drops it.
const notReloadedMarker = '__eventLogsNotReloaded';

const eventLogsWindow = (): Cypress.Chainable<Record<string, unknown>> =>
  cy.get('iframe#main-content').its('0.contentWindow');

const runQueryButton = (): Cypress.Chainable =>
  cy.getIframeBody().find('#div1 input[name="graph"]');

const filterCheckboxes = [
  '#cb_up',
  '#cb_down',
  '#cb_ok',
  '#cb_critical',
  '#notifId',
  '#ohId'
];

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
  cy.intercept(
    { method: 'GET', pathname: INTERCEPTORS.pages.event_logs_search },
    (request) => {
      request.on('response', (response) => {
        response.setDelay(searchDelayInMs);
      });
    }
  ).as('eventLogsSearch');
});

Given('an administrator is logged in', () => {
  cy.loginByTypeOfUser({ jsonName: 'admin', loginViaApi: true });
});

Given('the administrator opens the Event Logs page', () => {
  cy.visit(PAGES.monitoring.eventLogsLegacy);
  cy.wait('@getTimeZone');
  cy.waitForElementInIframe('#main-content', 'select#host_filter');
});

Given('the Event Logs search fails', () => {
  // Registered after the one in beforeEach, so it answers first.
  cy.intercept(
    { method: 'GET', pathname: INTERCEPTORS.pages.event_logs_search },
    { body: 'Gateway Timeout', delay: searchDelayInMs, statusCode: 504 }
  ).as('eventLogsSearch');
});

When('the administrator changes the Event Logs filters', () => {
  eventLogsWindow().then((win) => {
    win[notReloadedMarker] = true;
  });

  filterCheckboxes.forEach((selector) => {
    cy.getIframeBody().find(selector).click();
  });

  openHostFilterDropdown();
  // Skips the "Searching..." placeholder shown while the hosts load.
  cy.getIframeBody()
    .find(
      'ul.select2-results__options li.select2-results__option:not(.loading-results):not(.select2-results__message)'
    )
    .first()
    .click();

  cy.getIframeBody()
    .find('select[name="period"] option')
    .eq(1)
    .then((option) => {
      cy.getIframeBody()
        .find('select[name="period"]')
        .select(String(option.val()));
    });

  // Enter must not submit the form: "the Event Logs page is not reloaded" checks it.
  cy.getIframeBody().find('#output').type('ping{enter}');
});

When('the administrator clicks the "Run query" button', () => {
  runQueryButton().click();
});

Then('no Event Logs search is run', () => {
  cy.wait(noSearchGraceInMs);
  cy.get('@eventLogsSearch.all').should('have.length', 0);
});

// Runs after the wait of "no Event Logs search is run", which leaves time for a reload to happen.
Then('the Event Logs page is not reloaded', () => {
  eventLogsWindow().its(notReloadedMarker).should('eq', true);
  cy.getIframeBody().find('#output').should('have.value', 'ping');
});

Then('the "Run query" button is enabled', () => {
  runQueryButton().should('have.value', 'Run query').and('not.be.disabled');
});

Then('the "Run query" button is disabled while the search runs', () => {
  runQueryButton().should('be.disabled');
});

Then('the Event Logs list is displayed', () => {
  cy.wait('@eventLogsSearch').its('response.statusCode').should('eq', 200);
  cy.getIframeBody().find('#logView4xml .eventLogsPager').should('exist');
});

Then('the Event Logs search ends with an error', () => {
  cy.wait('@eventLogsSearch').its('response.statusCode').should('eq', 504);
});

Then('the "Run query" button is enabled again', () => {
  // The click disables the button at once; this retries until the search ends.
  runQueryButton().should('not.be.disabled');
});

Then('the Event Logs search has run {int} times', (count: number) => {
  cy.wait('@eventLogsSearch');
  cy.get('@eventLogsSearch.all').should('have.length', count);
});

afterEach(() => {
  cy.stopContainers();
});
