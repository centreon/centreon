import { PAGES } from 'fixtures/shared/constants/pages';

const contactsPage = PAGES.configuration.contactsUsersLegacy;
const contactTemplatesPage = PAGES.configuration.contactTemplatesLegacy;
const contactGroupsPage = PAGES.configuration.contactGroupsLegacy;

/**
 * Aliases of the listing XHR, registered in each spec's beforeEach. Waiting on
 * the request is the only reliable synchronisation: the "Loading..." row is
 * rendered on the first load only, and closing the side panel refetches
 * silently, so a DOM-only wait passes against stale rows.
 */
const listingAlias = {
  contactGroups: '@getContactGroupListing',
  contacts: '@getContactListing',
  contactTemplates: '@getContactTemplateListing'
};

/** Wait for a listing fetch to land and its rows to be rendered. */
function waitForListingXhr(alias: string): void {
  cy.wait(alias);
  cy.getIframeBody()
    .find('#clTableBody tr td')
    .should('not.contain', 'Loading');
}

/** Open a modernized listing and wait for its first AJAX page. */
function visitListing(page: string, alias: string): void {
  cy.visit(page);
  cy.waitForElementInIframe('#main-content', 'table.cl-listing-table');
  waitForListingXhr(alias);
  cy.getIframeBody()
    .find('#clTableBody .cl-col-picker input[type="checkbox"]')
    .should('have.length.greaterThan', 0);
}

/**
 * Type in the search field. Every listing has liveSearch enabled, so the fetch
 * is debounced on input; only the contacts listing renders a Search button, so
 * no step may rely on one.
 */
function searchListing(term: string, alias: string): void {
  cy.getIframeBody().find('#clSearchInput').clear().type(term);
  waitForListingXhr(alias);
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
  listingAlias,
  searchListing,
  toggleListingRow,
  visitListing,
  waitForListingXhr
};
