import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { checkHostsAreMonitored } from 'commons';
import { INTERCEPTORS } from 'fixtures/shared/constants/interceptors';
import { PAGES } from 'fixtures/shared/constants/pages';

import hostCategories from '../../../fixtures/host-categories/category.json';

const listingPage = PAGES.configuration.hostCategoriesLegacy;
const secondCategoryName = 'host-category-second';

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

// The modernized page renders an AJAX table; wait for it and its rows.
const openListing = (): void => {
  cy.visit(listingPage);
  cy.wait('@getTimeZone');
  cy.waitForElementInIframe('#main-content', 'table.cl-listing-table');
  cy.getIframeBody()
    .find('#clTableBody tr')
    .should('have.length.greaterThan', 0);
};

// The add/edit form loads in a nested iframe (#cfSidePanelFrame) inside the
// legacy #main-content iframe, so getIframeBody() (top-level) cannot reach it.
// Drill one level deeper and return the side-panel document body.
const sidePanelBody = (): Cypress.Chainable =>
  cy
    .getIframeBody()
    .find('#cfSidePanelFrame')
    .its('0.contentDocument.body', { timeout: 20000 })
    .should('not.be.empty')
    .then(cy.wrap);

// Open the side-panel form by clicking a category name link, then wait for it.
const openFormFor = (name: string): void => {
  cy.getIframeBody().find('#clTableBody').contains('a', name).click();
  sidePanelBody()
    .find('input[name="hc_name"]', { timeout: 20000 })
    .should('be.visible');
};

// Pick an option in the select2 multi-select of the field carrying `label`.
// The placeholder is now the generic "Search", so anchor on the field label.
const selectInField = (label: string, option: string): void => {
  // The multi-select is collapsed and its dropdown header overlaps the control,
  // and a previously opened dropdown may still cover it, so force both clicks.
  sidePanelBody()
    .contains('.cf-field', label)
    .find('.select2-selection')
    .click({ force: true });
  sidePanelBody()
    .find('.select2-results__option', { timeout: 20000 })
    .contains(option)
    .click({ force: true });
};

const createCategory = (body: Record<string, unknown>): void => {
  cy.request({
    body,
    headers: { 'Content-Type': 'application/json' },
    method: 'POST',
    url: '/centreon/api/beta/configuration/hosts/categories'
  }).then((response) => {
    expect(response.status).to.eq(201);
  });
};

// ---------------------------------------------------------------------------
// Setup
// ---------------------------------------------------------------------------

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
  cy.addHost({
    hostGroup: 'Linux-Servers',
    name: 'host2',
    template: 'generic-host'
  }).applyPollerConfiguration();

  checkHostsAreMonitored([{ name: 'host2' }]);
});

afterEach(() => {
  cy.stopContainers();
});

// ---------------------------------------------------------------------------
// Background
// ---------------------------------------------------------------------------

Given('an admin user is logged in a Centreon server', () => {
  cy.loginByTypeOfUser({
    jsonName: 'admin',
    loginViaApi: false
  });
});

Given('a host category is configured', () => {
  createCategory(hostCategories.default);
});

Given('a second host category is configured', () => {
  createCategory({
    alias: secondCategoryName,
    comment: 'second',
    is_activated: true,
    name: secondCategoryName
  });
});

// ---------------------------------------------------------------------------
// Listing loads
// ---------------------------------------------------------------------------

When('the user opens the host categories listing', () => {
  openListing();
});

Then(
  'the AJAX listing table is displayed with the configured host category',
  () => {
    cy.getIframeBody().find('table.cl-listing-table').should('exist');
    cy.getIframeBody()
      .find('#clTableBody')
      .contains(hostCategories.default.name)
      .should('exist');
  }
);

// ---------------------------------------------------------------------------
// Search
// ---------------------------------------------------------------------------

When('the user searches for the first host category', () => {
  // Live search (debounced AJAX) — no submit button, the table refreshes on type.
  cy.getIframeBody()
    .find('#clSearchInput')
    .clear()
    .type(hostCategories.default.name);
  cy.getIframeBody().find('#clTableBody td').should('not.contain', 'Loading');
});

Then('only the matching host category is displayed', () => {
  cy.getIframeBody()
    .find('#clTableBody')
    .contains(hostCategories.default.name)
    .should('exist');
  cy.getIframeBody()
    .find('#clTableBody')
    .contains(secondCategoryName)
    .should('not.exist');
});

// ---------------------------------------------------------------------------
// Edit (deep, through the side-panel form)
// ---------------------------------------------------------------------------

When('the user changes the properties of a host category', () => {
  openListing();
  // host2 must be monitored so it is selectable as a linked host.
  cy.waitUntil(
    () =>
      cy
        .getByLabel({ label: 'Up status hosts', tag: 'a' })
        .invoke('text')
        .then((text) => {
          if (text !== '2') {
            cy.exportConfig();
          }

          return text === '2';
        }),
    { interval: 20000, timeout: 100000 }
  );

  openFormFor(hostCategories.default.name);
  sidePanelBody()
    .find('input[name="hc_name"]')
    .clear()
    .type(hostCategories.forTest.name);
  sidePanelBody()
    .find('input[name="hc_alias"]')
    .clear()
    .type(hostCategories.forTest.alias);
  selectInField('Linked Hosts', 'host2');
  selectInField('Linked Host Template', 'generic-host');
  // Disable via the modernized Status toggle (replaces the legacy "Disabled"
  // radio). The real checkbox is hidden behind the slider, so force the click.
  sidePanelBody().find('#cf-hc-activate-toggle').click({ force: true });
  sidePanelBody()
    .find('textarea[name="hc_comment"]')
    .clear()
    .type(hostCategories.forTest.comment);
  sidePanelBody().find('input.btc.bt_success[name^="submit"]').first().click();
  cy.wait('@getTimeZone');
  cy.exportConfig();
});

Then('the properties are updated', () => {
  cy.getIframeBody()
    .find('#clTableBody')
    .contains(hostCategories.forTest.name)
    .should('exist');
  openFormFor(hostCategories.forTest.name);
  sidePanelBody()
    .find('input[name="hc_name"]')
    .should('have.value', hostCategories.forTest.name);
  sidePanelBody()
    .find('input[name="hc_alias"]')
    .should('have.value', hostCategories.forTest.alias);
  sidePanelBody()
    .find('.select2-selection__choice[title="host2"]')
    .should('exist');
  sidePanelBody()
    .find('.select2-selection__choice[title="generic-host"]')
    .should('exist');
  sidePanelBody().find('#cf-hc-activate-toggle').should('not.be.checked');
  sidePanelBody()
    .find('textarea[name="hc_comment"]')
    .should('have.value', hostCategories.forTest.comment);
});

// ---------------------------------------------------------------------------
// Toggle enable/disable from the listing
// ---------------------------------------------------------------------------

When('the user toggles the host category off from the listing', () => {
  cy.intercept({
    method: 'POST',
    url: '**/ajaxHostCategoriesToggle.php'
  }).as('toggleHc');

  cy.getIframeBody()
    .find('#clTableBody')
    .contains(hostCategories.default.name)
    .parents('tr')
    // The real checkbox is 0x0 behind the .cl-toggle slider; force the click.
    .find('.cl-toggle input[type="checkbox"]')
    .should('be.checked')
    .click({ force: true });

  cy.wait('@toggleHc');
});

Then('the toggle request succeeds and the category is disabled', () => {
  cy.get('@toggleHc').its('response.statusCode').should('eq', 200);
  cy.get('@toggleHc')
    .its('response.body')
    .should('have.property', 'success', true);
  cy.getIframeBody()
    .find('#clTableBody')
    .contains(hostCategories.default.name)
    .parents('tr')
    .find('.cl-toggle input[type="checkbox"]')
    .should('not.be.checked');
});

When('the user toggles the host category on from the listing', () => {
  cy.intercept({
    method: 'POST',
    url: '**/ajaxHostCategoriesToggle.php'
  }).as('toggleHcOn');

  cy.getIframeBody()
    .find('#clTableBody')
    .contains(hostCategories.default.name)
    .parents('tr')
    .find('.cl-toggle input[type="checkbox"]')
    .should('not.be.checked')
    .click({ force: true });

  cy.wait('@toggleHcOn').its('response.statusCode').should('eq', 200);
});

Then('the category is enabled again', () => {
  cy.getIframeBody()
    .find('#clTableBody')
    .contains(hostCategories.default.name)
    .parents('tr')
    .find('.cl-toggle input[type="checkbox"]')
    .should('be.checked');
});

// ---------------------------------------------------------------------------
// Duplicate
// ---------------------------------------------------------------------------

const selectRowAndRunBulkAction = (name: string, action: string): void => {
  cy.getIframeBody()
    .find('#clTableBody')
    .contains(name)
    .parents('tr')
    // The real checkbox is visibility:hidden behind its md-checkbox label.
    .find('.cl-col-picker input[type="checkbox"]')
    .click({ force: true });
  cy.getIframeBody()
    .find('select[name="o1"]')
    .invoke(
      'attr',
      'onchange',
      "javascript: { setO(this.form.elements['o1'].value); this.form.submit(); }"
    );
  // The native o1 select is hidden (replaced by the .cl-more-actions menu); the
  // overridden onchange above turns a value change into setO + submit.
  cy.getIframeBody().find('select[name="o1"]').select(action, { force: true });
};

When('the user duplicates a host category', () => {
  openListing();
  selectRowAndRunBulkAction(hostCategories.default.name, 'Duplicate');
  cy.wait('@getTimeZone');
  cy.exportConfig();
});

Then('a new host category is created with identical properties', () => {
  cy.waitForElementInIframe('#main-content', 'table.cl-listing-table');
  cy.getIframeBody()
    .find('#clTableBody')
    .contains(`${hostCategories.default.name}_1`)
    .should('exist');
  openFormFor(`${hostCategories.default.name}_1`);
  sidePanelBody()
    .find('input[name="hc_name"]')
    .should('have.value', `${hostCategories.default.name}_1`);
  sidePanelBody()
    .find('input[name="hc_alias"]')
    .should('have.value', hostCategories.default.alias);
  sidePanelBody().find('#cf-hc-activate-toggle').should('be.checked');
});

// ---------------------------------------------------------------------------
// Delete
// ---------------------------------------------------------------------------

When('the user deletes a host category', () => {
  openListing();
  selectRowAndRunBulkAction(hostCategories.default.name, 'Delete');
  cy.wait('@getTimeZone');
  cy.exportConfig();
});

Then(
  'the deleted host category is not visible anymore on the host category page',
  () => {
    cy.waitForElementInIframe('#main-content', 'table.cl-listing-table');
    cy.getIframeBody()
      .find('#clTableBody')
      .contains(hostCategories.default.name)
      .should('not.exist');
  }
);

// ---------------------------------------------------------------------------
// Pagination
// ---------------------------------------------------------------------------

Then('the pagination information shows the total count', () => {
  cy.getIframeBody()
    .find('#clPaginationTop')
    .invoke('text')
    .should('match', /\d+-\d+ of \d+/);
});

// ---------------------------------------------------------------------------
// Search persistence
// ---------------------------------------------------------------------------

When(
  'the user opens the host category form and comes back to the listing',
  () => {
    openFormFor(hostCategories.default.name);
    openListing();
  }
);

Then('the search field still contains the search term', () => {
  cy.getIframeBody()
    .find('#clSearchInput')
    .should('have.value', hostCategories.default.name);
});
