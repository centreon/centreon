import { PAGES } from 'fixtures/shared/constants/pages';

import {
  generatePollersField,
  listingAddButton,
  listingSearchInput,
  listingTable,
  listingTableBody,
  rowCheckbox,
  sidePanelFrame
} from './common';

/**
 * The modernized form is rendered inside a side panel iframe, which itself
 * lives inside the legacy #main-content iframe: getIframeBody() alone cannot
 * reach it, so drill one level further down.
 */
Cypress.Commands.add('getTrapSidePanelBody', () => {
  return cy
    .getIframeBody()
    .find(sidePanelFrame)
    .its('0.contentDocument.body', { timeout: 20_000 })
    .should('not.be.empty')
    .then((body) => cy.wrap<JQuery<HTMLElement>>(body));
});

Cypress.Commands.add('openTrapsSnmpListing', () => {
  cy.visit(PAGES.configuration.snmpTrapsLegacy);
  cy.waitForElementInIframe('#main-content', listingTable);
});

Cypress.Commands.add('openTrapVendorsListing', () => {
  cy.visit(PAGES.configuration.snmpTrapsManufacturerLegacy);
  cy.waitForElementInIframe('#main-content', listingTable);
});

Cypress.Commands.add('openTrapGroupsListing', () => {
  cy.visit(PAGES.configuration.snmpTrapsGroupsLegacy);
  cy.waitForElementInIframe('#main-content', listingTable);
});

/** Open the creation form in the side panel, then wait for one of its fields. */
Cypress.Commands.add('openTrapsAddForm', (fieldSelector: string) => {
  cy.getIframeBody().find(listingAddButton).click();
  cy.getTrapSidePanelBody()
    .find(fieldSelector, { timeout: 20_000 })
    .should('be.visible');
});

/** Open an existing row's form in the side panel, then wait for one of its fields. */
Cypress.Commands.add(
  'openTrapsRowForm',
  (name: string, fieldSelector: string) => {
    cy.getIframeBody().find(listingTableBody).contains('a', name).click();
    cy.getTrapSidePanelBody()
      .find(fieldSelector, { timeout: 20_000 })
      .should('be.visible');
  }
);

/**
 * Select a row and run one of the "More actions" entries. The native o1 select
 * is display:none (replaced by the .cl-more-actions menu) and its own onchange
 * opens the confirmation modal, so override it with setO + submit and force the
 * selection.
 */
Cypress.Commands.add('runTrapsBulkAction', (name: string, action: string) => {
  cy.getIframeBody()
    .find(listingTableBody)
    .contains(name)
    .parents('tr')
    // The real checkbox is visibility:hidden behind its md-checkbox label.
    .find(rowCheckbox)
    .click({ force: true });
  cy.getIframeBody()
    .find('select[name="o1"]')
    .invoke(
      'attr',
      'onchange',
      "javascript: { setO(this.form.elements['o1'].value); this.form.submit(); }"
    );
  cy.getIframeBody().find('select[name="o1"]').select(action, { force: true });
});

/** Pick an option in a side-panel select2, addressed by its field label. */
Cypress.Commands.add(
  'selectTrapSidePanelOption',
  (label: string, option: string) => {
    cy.getTrapSidePanelBody()
      .contains('.cf-field', label)
      .find('.select2-selection')
      .click({ force: true });

    cy.getTrapSidePanelBody()
      .find('.select2-results__option', { timeout: 20_000 })
      .contains(option)
      .click({ force: true });
  }
);

/** Type a search term in the listing and wait for the AJAX refresh. */
Cypress.Commands.add('searchInTrapsListing', (term: string) => {
  // Live search (debounced AJAX) — the table refreshes as the user types.
  cy.getIframeBody().find(listingSearchInput).clear().type(term);
  cy.getIframeBody()
    .find(`${listingTableBody} td`)
    .should('not.contain', 'Loading');
});

/**
 * Pick a poller in the Generate page's select2. That page is rendered directly
 * in the legacy iframe, so it needs no side-panel drilling.
 */
Cypress.Commands.add('selectTrapsGeneratePoller', (poller: string) => {
  cy.getIframeBody()
    .find(`${generatePollersField} + .select2, .cf-field .select2-selection`)
    .first()
    .click({ force: true });
  cy.getIframeBody()
    .find('.select2-results__option', { timeout: 20_000 })
    .contains(poller)
    .click({ force: true });
});

declare global {
  // biome-ignore lint/style/noNamespace: matching the existing e2e declarations
  namespace Cypress {
    interface Chainable {
      getTrapSidePanelBody(): Chainable<JQuery<HTMLElement>>;
      openTrapsSnmpListing(): Chainable<void>;
      openTrapVendorsListing(): Chainable<void>;
      openTrapGroupsListing(): Chainable<void>;
      openTrapsAddForm(fieldSelector: string): Chainable<void>;
      openTrapsRowForm(name: string, fieldSelector: string): Chainable<void>;
      runTrapsBulkAction(name: string, action: string): Chainable<void>;
      selectTrapSidePanelOption(label: string, option: string): Chainable<void>;
      searchInTrapsListing(term: string): Chainable<void>;
      selectTrapsGeneratePoller(poller: string): Chainable<void>;
    }
  }
}
