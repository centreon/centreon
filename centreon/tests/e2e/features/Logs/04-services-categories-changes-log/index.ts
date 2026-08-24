import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { INTERCEPTORS } from 'fixtures/shared/constants/interceptors';
import { PAGES } from 'fixtures/shared/constants/pages';

import categories from '../../../fixtures/services/category.json';

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

Given('a user is logged in a Centreon server via APIv2', () => {
  cy.loginAsAdminViaApiV2();
  cy.visit('/').url().should('include', '/monitoring/resources');
});

When('an apiV2 call is made to "Add" a service category', () => {
  cy.addSubjectViaApiV2(
    categories.default,
    '/centreon/api/latest/configuration/services/categories'
  );
});

Then(
  'a new service category is displayed on the service categories page',
  () => {
    cy.visitListingAndWait(PAGES.configuration.servicesCategoriesLegacy);
    cy.getIframeBody()
      .find('#clTableBody')
      .contains('a', categories.default.name)
      .should('be.visible');
  }
);

Then(
  'a new "ADDED" line of log is getting added to the page Administration > Logs',
  () => {
    cy.visit(PAGES.configuration.logsLegacy);
    cy.wait('@getTimeZone');
    cy.waitForElementInIframe(
      '#main-content',
      'span[class*="badge service_ok"]'
    );
    cy.getIframeBody()
      .contains('span.badge.service_ok', 'Added')
      .should('exist');

    cy.getIframeBody()
      .find('tr.list_one')
      .find('td')
      .eq(2)
      .should('contain.text', 'servicecategories');
  }
);

Then(
  'the informations of the log are the same as those passed to the endpoint',
  () => {
    cy.getIframeBody().contains(categories.default.name).click();
    cy.waitForElementInIframe(
      '#main-content',
      'a[href="./main.php?p=508"].btc.bt_success'
    );
    cy.getIframeBody()
      .find('td.ListColHeaderCenter')
      .eq(0)
      .should('contain.text', categories.default.name);
    cy.getIframeBody().contains('td', 'Create by admin').should('exist');
    cy.checkLogDetails(1, 0, 'Field Name', 'Before', 'After');
    cy.checkLogDetails(1, 1, 'sc_activate', '', '1');
    cy.checkLogDetails(1, 2, 'sc_name', '', categories.default.name);
    cy.checkLogDetails(1, 3, 'sc_alias', '', categories.default.alias);
  }
);

Given('a service category is configured via APIv2', () => {
  cy.addSubjectViaApiV2(
    categories.default,
    '/centreon/api/latest/configuration/services/categories'
  );
});

When(
  'an apiV2 call is made to "Delete" the configured service category',
  () => {
    cy.deleteSubjectViaApiV2(
      '/centreon/api/latest/configuration/services/categories/5'
    );
  }
);

Then(
  'a new "DELETED" line of log is getting added to the page Administration > Log',
  () => {
    cy.visit(PAGES.configuration.logsLegacy);
    cy.wait('@getTimeZone');
    cy.waitForElementInIframe(
      '#main-content',
      'span[class*="badge service_critical"]'
    );
    cy.getIframeBody()
      .contains('span.badge.service_critical', 'Deleted')
      .should('exist');

    cy.getIframeBody()
      .find('tr.list_one')
      .find('td')
      .eq(2)
      .should('contain.text', 'servicecategories');
  }
);

When(
  'the user changes some properties of the configured service category from UI',
  () => {
    cy.visitListingAndWait(PAGES.configuration.servicesCategoriesLegacy);
    cy.openListingRowForm(categories.default.name)
      .find('input[name="sc_name"]', { timeout: 20_000 })
      .should('be.visible')
      .clear()
      .type(categories['service-category-changed'].name);
    cy.getListingSidePanelBody()
      .find('input.btc.bt_success[name^="submit"]')
      .first()
      .click();
    cy.wait('@getTimeZone');
  }
);

Then(
  'a new "CHANGED" line of log is getting added to the page Administration > Logs',
  () => {
    cy.visit(PAGES.configuration.logsLegacy);
    cy.wait('@getTimeZone');
    cy.waitForElementInIframe(
      '#main-content',
      'span[class*="badge service_warning"]'
    );
    cy.getIframeBody()
      .contains('span.badge.service_warning', 'Changed')
      .should('exist');

    cy.getIframeBody()
      .find('tr.list_one')
      .find('td')
      .eq(2)
      .should('contain.text', 'servicecategories');
  }
);

Then(
  'the informations of the log are the same as the changed properties',
  () => {
    cy.getIframeBody()
      .contains(categories['service-category-changed'].name)
      .click();
    cy.waitForElementInIframe(
      '#main-content',
      'a[href="./main.php?p=508"].btc.bt_success'
    );
    cy.getIframeBody()
      .find('td.ListColHeaderCenter')
      .eq(0)
      .should('contain.text', categories['service-category-changed'].name);
    cy.getIframeBody().contains('td', 'Change by admin').should('exist');
    cy.checkLogDetails(1, 0, 'Field Name', 'Before', 'After');
    cy.checkLogDetailsByField(
      1,
      'sc_name',
      categories.default.name,
      categories['service-category-changed'].name
    );
  }
);

Given('an enabled service category is configured via APIv2', () => {
  cy.addSubjectViaApiV2(
    categories.default,
    '/centreon/api/latest/configuration/services/categories'
  );
});

When('the user disables the configured service category from UI', () => {
  cy.visitListingAndWait(PAGES.configuration.servicesCategoriesLegacy);
  cy.getIframeBody()
    .find('#clTableBody')
    .contains(categories.default.name)
    .parents('tr')
    // The real checkbox is 0x0 behind the .cl-toggle slider; force the click.
    .find('.cl-toggle input[type="checkbox"]')
    .should('be.checked')
    .click({ force: true });
  cy.wait('@toggleSc').its('response.statusCode').should('eq', 200);
});

Then(
  'a new "DISABLED" line of log is getting added to the page Administration > Logs',
  () => {
    cy.visit(PAGES.configuration.logsLegacy);
    cy.wait('@getTimeZone');
    cy.waitForElementInIframe(
      '#main-content',
      'span[class*="badge service_critical"]'
    );
    cy.getIframeBody()
      .contains('span.badge.service_critical', 'Disabled')
      .should('exist');

    cy.getIframeBody()
      .find('tr.list_one')
      .find('td')
      .eq(2)
      .should('contain.text', 'servicecategories');
  }
);

Given('a disabled service category is configured via APIv2', () => {
  cy.addSubjectViaApiV2(
    categories['service-category-changed'],
    '/centreon/api/latest/configuration/services/categories'
  );
});

When('the user enables the configured service category from UI', () => {
  cy.visitListingAndWait(PAGES.configuration.servicesCategoriesLegacy);
  cy.getIframeBody()
    .find('#clTableBody')
    .contains(categories['service-category-changed'].name)
    .parents('tr')
    .find('.cl-toggle input[type="checkbox"]')
    .should('not.be.checked')
    .click({ force: true });
  cy.wait('@toggleSc').its('response.statusCode').should('eq', 200);
});

Then(
  'a new "ENABLED" line of log is getting added to the page Administration > Logs',
  () => {
    cy.visit(PAGES.configuration.logsLegacy);
    cy.wait('@getTimeZone');
    cy.waitForElementInIframe(
      '#main-content',
      'span[class*="badge service_ok"]'
    );
    cy.getIframeBody()
      .contains('span.badge.service_ok', 'Enabled')
      .should('exist');

    cy.getIframeBody()
      .find('tr.list_one')
      .find('td')
      .eq(2)
      .should('contain.text', 'servicecategories');
  }
);
