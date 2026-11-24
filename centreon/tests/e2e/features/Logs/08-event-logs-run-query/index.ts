import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { INTERCEPTORS } from 'fixtures/shared/constants/interceptors';
import { PAGES } from 'fixtures/shared/constants/pages';

import { openHostFilterDropdown, runQueryButton } from '../common';

// Long enough to check the button while the search runs.
const searchDelayInMs = 2000;
// Leaves time for a search that would start late.
const noSearchGraceInMs = 2000;
// Set on the displayed list: a new render replaces the list and drops it.
const renderedListMarker = 'data-e2e-rendered-list';
// "the Event Logs list is displayed again" needs a marked list to compare with.
let renderedListMarked = false;

const filterCheckboxes = [
  '#cb_up',
  '#cb_down',
  '#cb_ok',
  '#cb_critical',
  '#notifId',
  '#ohId'
];

beforeEach(() => {
  renderedListMarked = false;
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

  // Enter in the output filter must not run the search: "no Event Logs search is run"
  // checks it. The form has several text inputs and no submit button, so Enter
  // never submits it: a page reload is not a risk to check.
  cy.getIframeBody().find('#output').type('ping{enter}');
});

When('the administrator clicks the "Run query" button', () => {
  runQueryButton().click();
});

Then('no Event Logs search is run', () => {
  cy.wait(noSearchGraceInMs);
  cy.get('@eventLogsSearch.all').should('have.length', 0);
});

Then('the "Run query" button is enabled', () => {
  runQueryButton().should('have.value', 'Run query').and('not.be.disabled');
});

Then('the "Run query" button is disabled while the search runs', () => {
  runQueryButton().should('be.disabled');
});

Then('the Event Logs list is displayed', () => {
  cy.wait('@eventLogsSearch').its('response.statusCode').should('eq', 200);
  cy.getIframeBody()
    .find('#logView4xml .eventLogsPager')
    .should('exist')
    .then(($pager) => {
      $pager.attr(renderedListMarker, 'true');
      renderedListMarked = true;
    });
});

// The second search must render a new list, not only send a request.
Then('the Event Logs list is displayed again', () => {
  cy.then(() => {
    expect(
      renderedListMarked,
      '"the Event Logs list is displayed" ran before'
    ).to.eq(true);
  });
  cy.getIframeBody()
    .find(`#logView4xml .eventLogsPager[${renderedListMarker}]`)
    .should('not.exist');
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
