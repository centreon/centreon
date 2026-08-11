import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { INTERCEPTORS } from 'fixtures/shared/constants/interceptors';
import { PAGES } from 'fixtures/shared/constants/pages';

import categories from '../../../fixtures/services/category.json';
import {
  assertLatestChangelogRow,
  openChangelogListing,
  openObjectTimeline
} from '../common';

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
    cy.visit(PAGES.configuration.servicesCategoriesLegacy);
    cy.wait('@getTimeZone');
    cy.waitForElementInIframe(
      '#main-content',
      `a:contains("${categories.default.name}")`
    );
    cy.getIframeBody()
      .contains('a', categories.default.name)
      .should('be.visible');
  }
);

Then(
  'a new "ADDED" line of log is getting added to the page Administration > Logs',
  () => {
    openChangelogListing();
    assertLatestChangelogRow('service_ok', 'Added', 'Service Categories');
  }
);

Then(
  'the informations of the log are the same as those passed to the endpoint',
  () => {
    openObjectTimeline(categories.default.name);
    cy.expandTimelineCard('Added');
    cy.checkLogDetail('sc_activate', '', '1');
    cy.checkLogDetail('sc_name', '', categories.default.name);
    cy.checkLogDetail('sc_alias', '', categories.default.alias);
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
    openChangelogListing();
    assertLatestChangelogRow('service_critical', 'Deleted', 'Service Categories');
  }
);

When(
  'the user changes some properties of the configured service category from UI',
  () => {
    cy.visit(PAGES.configuration.servicesCategoriesLegacy);
    cy.wait('@getTimeZone');
    cy.waitForElementInIframe(
      '#main-content',
      `a:contains("${categories.default.name}")`
    );
    cy.getIframeBody().contains('a', categories.default.name).click();
    cy.getIframeBody().waitForElementInIframe(
      '#main-content',
      'input[name="sc_name"]'
    );
    cy.getIframeBody()
      .find('input[name="sc_name"]')
      .clear()
      .type(categories['service-category-changed'].name);
    cy.getIframeBody()
      .find('input.btc.bt_success[name^="submit"]')
      .eq(0)
      .click();
    cy.wait('@getTimeZone');
  }
);

Then(
  'a new "CHANGED" line of log is getting added to the page Administration > Logs',
  () => {
    openChangelogListing();
    assertLatestChangelogRow('service_warning', 'Changed', 'Service Categories');
  }
);

Then(
  'the informations of the log are the same as the changed properties',
  () => {
    openObjectTimeline(categories['service-category-changed'].name);
    cy.expandTimelineCard('Changed');
    cy.checkLogDetail(
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
  cy.visit(PAGES.configuration.servicesCategoriesLegacy);
  cy.wait('@getTimeZone');
  cy.waitForElementInIframe(
    '#main-content',
    `a:contains("${categories.default.name}")`
  );
  cy.getIframeBody().find('img[alt="Disabled"]').eq(1).click();
  cy.wait('@getTimeZone');
});

Then(
  'a new "DISABLED" line of log is getting added to the page Administration > Logs',
  () => {
    openChangelogListing();
    assertLatestChangelogRow('service_critical', 'Disabled', 'Service Categories');
  }
);

Given('a disabled service category is configured via APIv2', () => {
  cy.addSubjectViaApiV2(
    categories['service-category-changed'],
    '/centreon/api/latest/configuration/services/categories'
  );
});

When('the user enables the configured service category from UI', () => {
  cy.visit(PAGES.configuration.servicesCategoriesLegacy);
  cy.wait('@getTimeZone');
  cy.waitForElementInIframe(
    '#main-content',
    `a:contains("${categories.default.name}")`
  );
  cy.getIframeBody().find('img[alt="Enabled"]').eq(2).click();
  cy.wait('@getTimeZone');
});

Then(
  'a new "ENABLED" line of log is getting added to the page Administration > Logs',
  () => {
    openChangelogListing();
    assertLatestChangelogRow('service_ok', 'Enabled', 'Service Categories');
  }
);
