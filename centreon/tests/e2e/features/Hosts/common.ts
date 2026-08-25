/**
 * Selectors shared by the Hosts domain specs.
 *
 * The host, host template and host category pages all run on the shared
 * CentreonListing / CentreonForm framework, so these selectors are the same
 * across the three — keep them here rather than inlined in the step bodies.
 *
 * Two traps worth remembering (both cost a red run before):
 * - the real inputs behind a row toggle and a row checkbox are not visible
 *   (0x0 behind the slider, visibility:hidden behind the md-checkbox label), so
 *   they must be clicked with `{ force: true }`;
 * - the native `select[name="o1"]` is display:none — the visible control is the
 *   custom `.cl-more-actions` menu, and Delete / Duplicate go through the
 *   confirmation modal before anything is submitted.
 */

const listingSelectors = {
  advancedClear: '#clAdvPanel .cl-adv-clear',
  // The advanced filters are applied by their own Search button — the select2
  // fields and the Locked toggle carry no onchange of their own.
  advancedSearch: '#clSearchBtn',
  advancedToggle: '.cl-adv-icon-btn[data-cl-adv-panel="clAdvPanel"]',
  checkAll: '#checkall',
  duplicateInput: 'input.cl-dup-input',
  limitSelect: '#clPaginationTop select.cl-limit-select',
  moreActionsButton: '.cl-more-actions-btn',
  moreActionsItem: '.cl-more-actions-item',
  pageInfo: '#clPaginationTop .cl-page-info',
  rowCheckbox: '.cl-col-picker input[type="checkbox"]',
  rowToggle: '.cl-toggle input[type="checkbox"]',
  searchInput: '#clSearchInput',
  table: 'table.cl-listing-table',
  tableBody: '#clTableBody'
};

const confirmModalSelectors = {
  body: '.cl-confirm-body',
  confirm: '.cl-confirm-confirm-btn',
  modal: '.cl-confirm-modal',
  title: '.cl-confirm-title'
};

const formSelectors = {
  // Mass Change submits under its own name, and the panel it opens carries
  // neither the name nor the alias field — so this button, not host_name, is
  // what tells us the mass change form actually rendered.
  massChangeSubmit: 'input.btc.bt_success[name="submitMC"]',
  saveButton: 'input.btc.bt_success[name^="submit"]',
  sidePanelFrame: '#cfSidePanelFrame'
};

/**
 * DOM ids of the form's collapsible sections, shared by the host and the host
 * template forms (both render formHostOnPrem.ihtml / formHostCloud.ihtml).
 *
 * Scheduling, Relations, Data processing and Extended info ship with the
 * `collapsed` class, so their `.cf-section-body` is display:none on load and a
 * field inside them cannot be typed into until the section is expanded — see
 * cy.expandFormSection.
 */
const formSections = {
  basic: 'cf-sec-basic',
  check: 'cf-sec-check',
  data: 'cf-sec-data',
  extended: 'cf-sec-extended',
  notification: 'cf-sec-notif',
  relations: 'cf-sec-relations',
  scheduling: 'cf-sec-scheduling'
};

/**
 * Yes/No/Default switches are rendered as a button group driving a hidden radio
 * pair: click the button, assert the radio. The radio is what gets submitted, so
 * it is the reliable thing to assert on.
 */
const segmentedButton = (radioName: string, value: string): string =>
  `.cf-segmented[data-radio-name="${radioName}"] button[data-value="${value}"]`;

const segmentedRadio = (radioName: string, value: string): string =>
  // QuickForm renames a group's children to "<group>[<group>]"; a plain radio
  // keeps its own name. CentreonForm._findRadio tries both, so must we.
  `input[name="${radioName}[${radioName}]"][value="${value}"], input[name="${radioName}"][value="${value}"]`;

/**
 * Wait for a listing fetch to land. The "Loading..." row is only re-rendered on
 * the very first fetch (listing.js gates it on firstLoad), so on any later one
 * its absence is satisfied instantly by the rows already on screen — the XHR is
 * the only reliable barrier, and the alias is therefore required. The row check
 * is kept for the first load, where the placeholder is server-rendered.
 */
const waitForListingRefresh = (alias: string): void => {
  cy.wait(alias);
  cy.getIframeBody()
    .find(`${listingSelectors.tableBody} tr td`)
    .should('not.contain', 'Loading');
};

/**
 * Scoped to the name column on purpose: the Templates column renders parent
 * template names as links of their own, so an unscoped lookup can return the
 * row *inheriting* from that name rather than the row itself.
 */
const getListingRow = (name: string): Cypress.Chainable =>
  cy
    .getIframeBody()
    .find(`${listingSelectors.tableBody} tr td:nth-child(2)`)
    .contains(name)
    .parents('tr');

const searchInListing = (term: string, alias: string): void => {
  cy.getIframeBody().find(listingSelectors.searchInput).clear().type(term);
  waitForListingRefresh(alias);
};

export {
  confirmModalSelectors,
  formSections,
  formSelectors,
  listingSelectors,
  getListingRow,
  searchInListing,
  segmentedButton,
  segmentedRadio,
  waitForListingRefresh
};
