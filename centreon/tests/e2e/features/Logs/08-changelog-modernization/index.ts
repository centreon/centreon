import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { INTERCEPTORS } from 'fixtures/shared/constants/interceptors';

import { openChangelogListing } from '../common';

const hostCategoryUrl = '/centreon/api/latest/configuration/hosts/categories';
const listingUrl = '**/ajaxChangelogListing.php*';
const detailUrl = '**/ajaxChangelogDetail.php*';

// Object Type label shown for the 'hostcategories' token (see viewLogs.php).
const hostCategoryLabel = 'Host Categories';

let seededName = '';

const seedHostCategory = (name: string): Cypress.Chainable =>
  cy.addSubjectViaApiV2({ alias: name, name }, hostCategoryUrl);

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
  // Interceptors live here, not inside a step, so every scenario can wait on them.
  cy.intercept({ method: 'GET', url: listingUrl }).as('getChangelogListing');
  cy.intercept({ method: 'GET', url: detailUrl }).as('getChangelogDetail');
});

afterEach(() => {
  cy.stopContainers();
});

Given('a user is logged in Centreon', () => {
  cy.loginByTypeOfUser({ jsonName: 'admin' });
});

Given('a configuration change has been recorded', () => {
  seededName = 'changelog_hc';
  seedHostCategory(seededName);
});

Given('a disabled configuration change has been recorded', () => {
  seededName = 'changelog_hc_disabled';
  // Disabling records a "Disabled" action, which has no expandable diff.
  seedHostCategory(seededName).then((categoryId) => {
    expect(categoryId, 'created host category id').to.be.a('number');
    cy.updateSubjectViaApiV2(
      { alias: seededName, is_activated: false, name: seededName },
      `${hostCategoryUrl}/${categoryId}`
    );
  });
});

When('the user navigates to the changelog page', () => {
  openChangelogListing();
  // Consume the initial listing request so later waits see the next one.
  cy.wait('@getChangelogListing');
});

Then('the changelog listing is displayed', () => {
  cy.getIframeBody().find('table.cl-listing-table').should('exist');
  cy.getIframeBody()
    .find('#clTableBody tr')
    .should('have.length.greaterThan', 0);
});

Then('no pagination controls are shown', () => {
  cy.getIframeBody().find('#clPaginationTop a.cl-page-num').should('not.exist');
});

Then('a scroll info counter is displayed', () => {
  cy.getIframeBody()
    .find('#clPaginationTop .cl-page-info')
    .invoke('text')
    .should('match', /\d+\s*\/\s*\d+/);
});

When('the user searches for that object name in the changelog', () => {
  cy.getIframeBody().find('#clSearchInput').clear().type(seededName);
  cy.getIframeBody().find('#clSearchBtn').click();
  cy.wait('@getChangelogListing');
});

Then('only matching changelog entries are displayed', () => {
  cy.getIframeBody()
    .find('#clTableBody tr')
    .should('have.length.greaterThan', 0)
    .each(($row) => {
      cy.wrap($row).should('contain', seededName);
    });
});

When('the user selects the host category object type filter', () => {
  cy.getIframeBody().find('#clSearchType').select('hostcategories');
  cy.wait('@getChangelogListing');
});

Then('only entries of that type are displayed', () => {
  cy.getIframeBody()
    .find('#clTableBody tr')
    .should('have.length.greaterThan', 0)
    .each(($row) => {
      cy.wrap($row).find('td').eq(3).should('contain', hostCategoryLabel);
    });
});

const expandAddedEntry = (): void => {
  cy.getIframeBody()
    .find('#clTableBody tr')
    .contains('Added')
    .parents('tr')
    .find('.cl-expand-btn:not(.disabled)')
    .first()
    .click();
  cy.wait('@getChangelogDetail').its('response.statusCode').should('eq', 200);
};

When('the user clicks the expand button on the Added entry', () => {
  expandAddedEntry();
});

When('the user expands the Added entry', () => {
  expandAddedEntry();
  cy.getIframeBody().find('.cl-detail-row').should('exist');
});

When('the user clicks the expand button again', () => {
  cy.getIframeBody().find('#clTableBody .cl-expand-btn.open').first().click();
});

Then('a diff panel appears below the row', () => {
  cy.getIframeBody().find('.cl-detail-row').should('exist');
  cy.getIframeBody().find('.cl-diff-container').should('exist');
});

Then('the diff shows the recorded field name and value', () => {
  cy.getIframeBody()
    .find('.cl-diff-container .cl-diff-row')
    .should('have.length.greaterThan', 0);
  // The seeded creation records hc_name = the object name.
  cy.getIframeBody()
    .find('.cl-diff-container .cl-diff-row')
    .contains('.cl-diff-field', 'hc_name')
    .parents('.cl-diff-row')
    .find('.cl-diff-after')
    .should('contain.text', seededName);
});

Then('the diff panel is removed', () => {
  cy.getIframeBody().find('.cl-detail-row').should('not.exist');
});

Then('the Disabled entry expand button is grayed out and not clickable', () => {
  cy.getIframeBody()
    .find('#clTableBody tr')
    .contains('Disabled')
    .parents('tr')
    .find('.cl-expand-btn.disabled')
    .should('exist')
    .and('have.css', 'pointer-events', 'none');
});

When('the user clicks on the object name link', () => {
  cy.getIframeBody().find('#clTableBody td a').first().click();
});

Then('the timeline detail page is displayed', () => {
  cy.waitForElementInIframe('#main-content', '.cld-wrapper');
  cy.getIframeBody().find('.cld-timeline').should('exist');
  cy.getIframeBody().find('.cld-entry').should('have.length.greaterThan', 0);
});

Then('a back button returns to the changelog listing', () => {
  cy.getIframeBody().find('.cld-back-btn').click();
  cy.waitForElementInIframe('#main-content', 'table.cl-listing-table');
  cy.getIframeBody()
    .find('#clTableBody tr')
    .should('have.length.greaterThan', 0);
});
