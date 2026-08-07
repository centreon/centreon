import { PAGES } from 'fixtures/shared/constants/pages';

const contactsPage = PAGES.configuration.contactsUsersLegacy;
const contactTemplatesPage = PAGES.configuration.contactTemplatesLegacy;
const contactGroupsPage = PAGES.configuration.contactGroupsLegacy;

/** Wait for a modernized listing to swap its loading row for real rows. */
function waitForListingRefresh(): void {
  cy.getIframeBody()
    .find('#clTableBody tr td')
    .should('not.contain', 'Loading');
}

/** Open a modernized listing and wait for its first AJAX page. */
function visitListing(page: string): void {
  cy.visit(page);
  cy.waitForElementInIframe('#main-content', 'table.cl-listing-table');
  waitForListingRefresh();
  cy.getIframeBody()
    .find('#clTableBody .cl-col-picker input[type="checkbox"]')
    .should('have.length.greaterThan', 0);
}

/**
 * Type in the search field. Every listing has liveSearch enabled, so the fetch
 * is debounced on input; only the contacts listing renders a Search button, so
 * no step may rely on one.
 */
function searchListing(term: string): void {
  cy.getIframeBody().find('#clSearchInput').clear().type(term);
  waitForListingRefresh();
}

/**
 * Flip a row's activation toggle. The real input is 0x0 behind the .cl-toggle
 * slider, hence the forced click.
 */
function toggleListingRow(rowLabel: string): void {
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('tr', rowLabel)
    .find('.cl-toggle input[type="checkbox"]')
    .should('be.checked')
    .click({ force: true });
}

function expectRowToggleUnchecked(rowLabel: string): void {
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('tr', rowLabel)
    .find('.cl-toggle input[type="checkbox"]')
    .should('not.be.checked');
}

export {
  contactGroupsPage,
  contactTemplatesPage,
  contactsPage,
  expectRowToggleUnchecked,
  searchListing,
  toggleListingRow,
  visitListing,
  waitForListingRefresh
};
