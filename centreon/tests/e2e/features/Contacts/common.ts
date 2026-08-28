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

/**
 * Wait for a listing fetch to land and its rows to be rendered. The status check
 * is what catches an access regression: on a 401/403 the framework replaces the
 * "Loading..." row with its own message, so the DOM assertion below would pass
 * and the failure would only surface later as a missing element.
 */
function waitForListingXhr(alias: string): void {
  cy.wait(alias).its('response.statusCode').should('eq', 200);
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

/**
 * Assert the activation state. cy.checkLegacyRadioButton reads the QuickForm
 * radio group in the listing frame; the modernized form hides that group behind
 * a cl-toggle and renders it inside the side panel.
 */
function expectActivation(toggleId: string, enabled: boolean): void {
  cy.getSidePanelBody()
    .find(`#${toggleId}`)
    .should(enabled ? 'be.checked' : 'not.be.checked');
}

/**
 * form.js turns a Yes/No/Default radio group into a segmented control and hides
 * the original radios, so the labels can no longer be clicked; the buttons it
 * generates carry the choice and the active one is marked with .active.
 */
function setSegmentedChoice(radioName: string, label: string): void {
  cy.getSidePanelBody()
    .find(`.cf-segmented[data-radio-name="${radioName}"]`)
    .contains('button', label)
    .click();
}

function expectSegmentedChoice(radioName: string, label: string): void {
  cy.getSidePanelBody()
    .find(`.cf-segmented[data-radio-name="${radioName}"]`)
    .contains('button', label)
    .should('have.class', 'active');
}

/**
 * form.js also turns checkbox option groups into clickable chips and hides the
 * original checkboxes, so label[for=…] can no longer be clicked. Scope by the
 * group label: host and service options share several chip names.
 */
function setOptionChip(groupLabel: string, chipLabel: string): void {
  cy.getSidePanelBody()
    .contains('.cf-chips', groupLabel)
    .contains('.cf-chip', chipLabel)
    .click();
}

export {
  contactGroupsPage,
  expectActivation,
  expectSegmentedChoice,
  setOptionChip,
  setSegmentedChoice,
  contactTemplatesPage,
  contactsPage,
  expectRowToggleUnchecked,
  listingAlias,
  searchListing,
  toggleListingRow,
  visitListing,
  waitForListingXhr
};
