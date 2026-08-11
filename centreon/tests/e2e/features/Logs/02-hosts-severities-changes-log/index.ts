import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { INTERCEPTORS } from 'fixtures/shared/constants/interceptors';
import { PAGES } from 'fixtures/shared/constants/pages';

import severities from '../../../fixtures/host-categories/severity.json';
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

When('an apiV2 call is made to "Add" a host severity', () => {
  cy.addSubjectViaApiV2(
    severities.default,
    '/centreon/api/latest/configuration/hosts/severities'
  );
});

Then('a new severity is displayed on the hosts severities page', () => {
  cy.visit(PAGES.configuration.hostCategoriesLegacy);
  cy.wait('@getTimeZone');
  cy.waitForElementInIframe(
    '#main-content',
    `a:contains("${severities.default.name}")`
  );
  cy.getIframeBody()
    .contains('a', severities.default.name)
    .should('be.visible');
});

Then(
  'a new "ADDED" ligne of log is getting added to the page Administration > Logs',
  () => {
    openChangelogListing();
    assertLatestChangelogRow('service_ok', 'Added', 'Host severity');
  }
);

Then(
  'the informations of the log are the same as those passed to the endpoint',
  () => {
    openObjectTimeline(severities.default.name);
    cy.expandTimelineCard('Added');
    cy.checkLogDetail('hc_activate', '', '1');
    cy.checkLogDetail('hc_name', '', severities.default.name);
    cy.checkLogDetail('hc_alias', '', severities.default.alias);
    cy.checkLogDetail('hc_severity_level', '', `${severities.default.level}`);
    cy.checkLogDetail('hc_severity_icon', '', `${severities.default.icon_id}`);
  }
);

Given('a host severity is configured via APIv2', () => {
  cy.addSubjectViaApiV2(
    severities.default,
    '/centreon/api/latest/configuration/hosts/severities'
  );
});

When('an apiV2 call is made to "Delete" the configured host severity', () => {
  cy.deleteSubjectViaApiV2(
    '/centreon/api/latest/configuration/hosts/severities/1'
  );
});

Then(
  'a new "DELETED" ligne of log is getting added to the page Administration > Log',
  () => {
    openChangelogListing();
    assertLatestChangelogRow('service_critical', 'Deleted', 'Host severity');
  }
);

When(
  'an apiV2 call is made to "Update" the parameters of the configured host severity',
  () => {
    cy.updateSubjectViaApiV2(
      severities.changed_severity,
      '/centreon/api/latest/configuration/hosts/severities/1'
    );
  }
);

Then(
  'a new "CHANGED" ligne of log is getting added to the page Administration > Logs',
  () => {
    openChangelogListing();
    assertLatestChangelogRow('service_warning', 'Changed', 'Host severity');
  }
);

Then(
  'the informations of the log are the same as those of the updated host severity',
  () => {
    openObjectTimeline(severities.changed_severity.name);
    cy.expandTimelineCard('Changed');
    cy.checkLogDetail(
      'hc_name',
      severities.default.name,
      severities.changed_severity.name
    );
    cy.checkLogDetail(
      'hc_alias',
      severities.default.alias,
      severities.changed_severity.alias
    );
  }
);

Given('an enabled host severity is configured via APIv2', () => {
  cy.addSubjectViaApiV2(
    severities.default,
    '/centreon/api/latest/configuration/hosts/severities'
  );
});

When('an apiV2 call is made to "Disable" the configured host severity', () => {
  cy.updateSubjectViaApiV2(
    severities.disabled_severity,
    '/centreon/api/latest/configuration/hosts/severities/1'
  );
});

Then(
  'a new "DISABLED" ligne of log is getting added to the page Administration > Logs',
  () => {
    openChangelogListing();
    assertLatestChangelogRow('service_critical', 'Disabled', 'Host severity');
  }
);

Given('a disabled host severity is configured via APIv2', () => {
  cy.addSubjectViaApiV2(
    severities.disabled_severity,
    '/centreon/api/latest/configuration/hosts/severities'
  );
});

When('an apiV2 call is made to "Enable" the configured host severity', () => {
  cy.updateSubjectViaApiV2(
    severities.enabled_severity,
    '/centreon/api/latest/configuration/hosts/severities/1'
  );
});

Then(
  'a new "ENABLED" ligne of log is getting added to the page Administration > Logs',
  () => {
    openChangelogListing();
    assertLatestChangelogRow('service_ok', 'Enabled', 'Host severity');
  }
);
