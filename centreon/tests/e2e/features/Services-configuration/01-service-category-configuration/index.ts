import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { INTERCEPTORS } from 'fixtures/shared/constants/interceptors';

import serviceCategories from '../../../fixtures/services/category.json';

const secondCategoryName = 'service-category-second';
const serviceCategoriesApi =
  '/centreon/api/latest/configuration/services/categories';

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
    method: 'POST',
    url: INTERCEPTORS.ajax.service_categories_toggle
  }).as('toggleSc');
});

afterEach(() => {
  cy.stopContainers();
});

Given('an admin user is logged in a Centreon server', () => {
  cy.loginByTypeOfUser({
    jsonName: 'admin',
    loginViaApi: false
  });
});

Given('a service category is configured', () => {
  cy.addSubjectViaApiV2(serviceCategories.default, serviceCategoriesApi);
});

Given('a second service category is configured', () => {
  cy.addSubjectViaApiV2(
    { alias: secondCategoryName, is_activated: true, name: secondCategoryName },
    serviceCategoriesApi
  );
});

When('the user opens the service categories listing', () => {
  cy.openServiceCategoriesListing();
});

Then(
  'the AJAX listing table is displayed with the configured service category',
  () => {
    cy.getIframeBody().find('table.cl-listing-table').should('exist');
    cy.getIframeBody()
      .find('#clTableBody')
      .contains(serviceCategories.default.name)
      .should('exist');
  }
);

When('the user searches for the first service category', () => {
  // Live search (debounced AJAX) — no submit button, the table refreshes on type.
  cy.getIframeBody()
    .find('#clSearchInput')
    .clear()
    .type(serviceCategories.default.name);
  cy.getIframeBody().find('#clTableBody td').should('not.contain', 'Loading');
});

Then('only the matching service category is displayed', () => {
  cy.getIframeBody()
    .find('#clTableBody')
    .contains(serviceCategories.default.name)
    .should('exist');
  cy.getIframeBody()
    .find('#clTableBody')
    .contains(secondCategoryName)
    .should('not.exist');
});

When('the user changes the properties of a service category', () => {
  cy.openServiceCategoriesListing();
  cy.openServiceCategoryForm(serviceCategories.default.name);
  cy.getServiceCategorySidePanelBody()
    .find('input[name="sc_name"]')
    .clear()
    .type(serviceCategories['service-category-changed'].name);
  cy.getServiceCategorySidePanelBody()
    .find('input[name="sc_description"]')
    .clear()
    .type(serviceCategories['service-category-changed'].alias);
  // Disable via the modernized Status toggle. The real checkbox is hidden
  // behind the slider, so force the click.
  cy.getServiceCategorySidePanelBody()
    .find('#cf-sc-activate-toggle')
    .click({ force: true });
  cy.getServiceCategorySidePanelBody()
    .find('input.btc.bt_success[name^="submit"]')
    .first()
    .click();
  cy.wait('@getTimeZone');
});

Then('the properties are updated', () => {
  cy.getIframeBody()
    .find('#clTableBody')
    .contains(serviceCategories['service-category-changed'].name)
    .should('exist');
  cy.openServiceCategoryForm(
    serviceCategories['service-category-changed'].name
  );
  cy.getServiceCategorySidePanelBody()
    .find('input[name="sc_name"]')
    .should('have.value', serviceCategories['service-category-changed'].name);
  cy.getServiceCategorySidePanelBody()
    .find('input[name="sc_description"]')
    .should('have.value', serviceCategories['service-category-changed'].alias);
  cy.getServiceCategorySidePanelBody()
    .find('#cf-sc-activate-toggle')
    .should('not.be.checked');
});

When('the user toggles the service category off from the listing', () => {
  cy.getIframeBody()
    .find('#clTableBody')
    .contains(serviceCategories.default.name)
    .parents('tr')
    // The real checkbox is 0x0 behind the .cl-toggle slider; force the click.
    .find('.cl-toggle input[type="checkbox"]')
    .should('be.checked')
    .click({ force: true });
  cy.wait('@toggleSc');
});

Then('the toggle request succeeds and the category is disabled', () => {
  cy.get('@toggleSc').its('response.statusCode').should('eq', 200);
  cy.get('@toggleSc')
    .its('response.body')
    .should('have.property', 'success', true);
  cy.getIframeBody()
    .find('#clTableBody')
    .contains(serviceCategories.default.name)
    .parents('tr')
    .find('.cl-toggle input[type="checkbox"]')
    .should('not.be.checked');
});

When('the user toggles the service category on from the listing', () => {
  cy.getIframeBody()
    .find('#clTableBody')
    .contains(serviceCategories.default.name)
    .parents('tr')
    .find('.cl-toggle input[type="checkbox"]')
    .should('not.be.checked')
    .click({ force: true });
  cy.wait('@toggleSc').its('response.statusCode').should('eq', 200);
});

Then('the category is enabled again', () => {
  cy.getIframeBody()
    .find('#clTableBody')
    .contains(serviceCategories.default.name)
    .parents('tr')
    .find('.cl-toggle input[type="checkbox"]')
    .should('be.checked');
});

When('the user duplicates a service category', () => {
  cy.openServiceCategoriesListing();
  cy.selectServiceCategoryRowAndRunBulkAction(
    serviceCategories.default.name,
    'Duplicate'
  );
  cy.wait('@getTimeZone');
});

Then('a new service category is created with identical properties', () => {
  cy.waitForElementInIframe('#main-content', 'table.cl-listing-table');
  cy.getIframeBody()
    .find('#clTableBody')
    .contains(`${serviceCategories.default.name}_1`)
    .should('exist');
});

When('the user deletes a service category', () => {
  cy.openServiceCategoriesListing();
  cy.selectServiceCategoryRowAndRunBulkAction(
    serviceCategories.default.name,
    'Delete'
  );
  cy.wait('@getTimeZone');
});

Then(
  'the deleted service category is not visible anymore on the service categories page',
  () => {
    cy.waitForElementInIframe('#main-content', 'table.cl-listing-table');
    cy.getIframeBody()
      .find('#clTableBody')
      .contains(serviceCategories.default.name)
      .should('not.exist');
  }
);

Then('the pagination information shows the total count', () => {
  cy.getIframeBody()
    .find('#clPaginationTop')
    .invoke('text')
    .should('match', /\d+-\d+ of \d+/);
});

When(
  'the user opens the service category form and comes back to the listing',
  () => {
    cy.openServiceCategoryForm(serviceCategories.default.name);
    cy.openServiceCategoriesListing();
  }
);

Then('the search field still contains the search term', () => {
  cy.getIframeBody()
    .find('#clSearchInput')
    .should('have.value', serviceCategories.default.name);
});
