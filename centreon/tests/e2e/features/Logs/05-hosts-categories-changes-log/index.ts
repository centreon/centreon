import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { INTERCEPTORS } from 'fixtures/shared/constants/interceptors';
import { PAGES } from 'fixtures/shared/constants/pages';

import categories from '../../../fixtures/host-categories/category.json';
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

const hostCategoriesUrl = '/centreon/api/latest/configuration/hosts/categories';

// Id of the category created by the current scenario, shared across its steps.
let categoryId = 0;

Given('a user is logged in a Centreon server via APIv2', () => {
  cy.loginAsAdminViaApiV2();
  cy.visit('/').url().should('include', '/monitoring/resources');
});

When('an apiV2 call is made to "Add" a host category', () => {
  cy.addSubjectViaApiV2(categories.default, hostCategoriesUrl).then((id) => {
    expect(id, 'created host category id').to.be.a('number');
    categoryId = id;
  });
});

Then('a new host category is displayed on the host categories page', () => {
  cy.visit(PAGES.configuration.hostCategoriesLegacy);
  cy.wait('@getTimeZone');
  cy.waitForElementInIframe(
    '#main-content',
    `a:contains("${categories.default.name}")`
  );
  cy.getIframeBody()
    .contains('a', categories.default.name)
    .should('be.visible');
});

Then(
  'a new "Added" line of log is getting added to the page Administration > Logs',
  () => {
    openChangelogListing();
    assertLatestChangelogRow('service_ok', 'Added', 'Host Categories');
  }
);

Then(
  'the informations of the log are the same as those passed to the endpoint',
  () => {
    openObjectTimeline(categories.default.name);
    cy.expandTimelineCard('Added');
    cy.checkLogDetail('hc_activate', '', '1');
    cy.checkLogDetail('hc_comment', '', categories.default.comment);
    cy.checkLogDetail('hc_name', '', categories.default.name);
    cy.checkLogDetail('hc_alias', '', categories.default.alias);
  }
);

Given('a host category is configured via APIv2', () => {
  cy.addSubjectViaApiV2(categories.default, hostCategoriesUrl).then((id) => {
    expect(id, 'created host category id').to.be.a('number');
    categoryId = id;
  });
});

When('an apiV2 call is made to "Delete" the configured host category', () => {
  cy.deleteSubjectViaApiV2(`${hostCategoriesUrl}/${categoryId}`);
});

Then(
  'a new "Deleted" line of log is getting added to the page Administration > Log',
  () => {
    openChangelogListing();
    assertLatestChangelogRow('service_critical', 'Deleted', 'Host Categories');
  }
);

When('an APIv2 call is made to "Update" the configured host category', () => {
  cy.updateSubjectViaApiV2(
    categories.forTest,
    `${hostCategoriesUrl}/${categoryId}`
  );
});

Then(
  'a new "Changed" line of log is getting added to the page Administration > Logs',
  () => {
    openChangelogListing();
    assertLatestChangelogRow('service_warning', 'Changed', 'Host Categories');
  }
);

Then(
  'the informations of the log are the same as those passed to te "PUT" api call',
  () => {
    openObjectTimeline(categories.forTest.name);
    cy.expandTimelineCard('Changed');
    cy.checkLogDetail(
      'hc_comment',
      categories.default.comment,
      categories.forTest.comment
    );
    cy.checkLogDetail(
      'hc_name',
      categories.default.name,
      categories.forTest.name
    );
    cy.checkLogDetail(
      'hc_alias',
      categories.default.alias,
      categories.forTest.alias
    );
  }
);

Given('an enabled host category is configured via APIv2', () => {
  cy.addSubjectViaApiV2(categories.default, hostCategoriesUrl).then((id) => {
    expect(id, 'created host category id').to.be.a('number');
    categoryId = id;
  });
});

When('an APIv2 call is made to "Disable" the configured host category', () => {
  cy.updateSubjectViaApiV2(
    categories.disabled,
    `${hostCategoriesUrl}/${categoryId}`
  );
});

Then(
  'a new "DISABLED" line of log is getting added to the page Administration > Logs',
  () => {
    openChangelogListing();
    assertLatestChangelogRow('service_critical', 'Disabled', 'Host Categories');
  }
);

Given('a disabled host category is configured via APIv2', () => {
  cy.addSubjectViaApiV2(categories.disabled, hostCategoriesUrl).then((id) => {
    expect(id, 'created host category id').to.be.a('number');
    categoryId = id;
  });
});

When('an APIv2 call is made to "Enable" the disabled host category', () => {
  cy.updateSubjectViaApiV2(
    categories.default,
    `${hostCategoriesUrl}/${categoryId}`
  );
});

Then(
  'a new "ENABLED" line of log is getting added to the page Administration > Logs',
  () => {
    openChangelogListing();
    assertLatestChangelogRow('service_ok', 'Enabled', 'Host Categories');
  }
);
