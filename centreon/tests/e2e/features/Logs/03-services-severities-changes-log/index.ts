import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { INTERCEPTORS } from 'fixtures/shared/constants/interceptors';
import { PAGES } from 'fixtures/shared/constants/pages';

import severities from '../../../fixtures/services/severity.json';
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

When('an apiV2 call is made to "Add" a service severity', () => {
  cy.addSubjectViaApiV2(
    severities.enabled_severity,
    'centreon/api/latest/configuration/services/severities'
  );
});

Then(
  'a new service severity is displayed on the service severities page',
  () => {
    cy.visit(PAGES.configuration.servicesCategoriesLegacy);
    cy.wait('@getTimeZone');
    cy.waitForElementInIframe(
      '#main-content',
      `a:contains("${severities.enabled_severity.name}")`
    );
    cy.getIframeBody()
      .contains('a', severities.enabled_severity.name)
      .should('be.visible');
  }
);

Then(
  'a new "Added" ligne of log is getting added to the page Administration > Logs',
  () => {
    openChangelogListing();
    assertLatestChangelogRow('service_ok', 'Added', 'Service severity');
  }
);

Then(
  'the informations of the log are the same as those passed to the endpoint',
  () => {
    openObjectTimeline(severities.enabled_severity.name);
    cy.expandTimelineCard('Added');
    cy.checkLogDetail('sc_activate', '', '1');
    cy.checkLogDetail('sc_name', '', severities.enabled_severity.name);
    cy.checkLogDetail('sc_description', '', severities.enabled_severity.alias);
    cy.checkLogDetail(
      'sc_severity_level',
      '',
      `${severities.enabled_severity.level}`
    );
    cy.checkLogDetail(
      'sc_severity_icon',
      '',
      `${severities.enabled_severity.icon_id}`
    );
  }
);

Given('a service severity is configured via APIv2', () => {
  cy.addSubjectViaApiV2(
    severities.enabled_severity,
    '/centreon/api/latest/configuration/services/severities'
  );
});

When(
  'an apiV2 call is made to "Delete" the configured service severity',
  () => {
    cy.deleteSubjectViaApiV2(
      '/centreon/api/latest/configuration/services/severities/5'
    );
  }
);

Then(
  'a new "Deleted" ligne of log is getting added to the page Administration > Log',
  () => {
    openChangelogListing();
    assertLatestChangelogRow('service_critical', 'Deleted', 'Service severity');
  }
);

When(
  'an apiV2 call is made to "Update" the parameters of the configured severity',
  () => {
    cy.updateSubjectViaApiV2(
      severities.changed_severity,
      '/centreon/api/latest/configuration/services/severities/5'
    );
  }
);

Then(
  'a new "Changed" ligne of log is getting added to the page Administration > Logs',
  () => {
    openChangelogListing();
    assertLatestChangelogRow('service_warning', 'Changed', 'Service severity');
  }
);

Then(
  'the informations of the log are the same as those of the updated service severity',
  () => {
    openObjectTimeline(severities.changed_severity.name);
    cy.expandTimelineCard('Changed');
    cy.checkLogDetail(
      'sc_name',
      severities.enabled_severity.name,
      severities.changed_severity.name
    );
    cy.checkLogDetail(
      'sc_description',
      severities.enabled_severity.alias,
      severities.changed_severity.alias
    );
    cy.checkLogDetail(
      'sc_severity_level',
      `${severities.enabled_severity.level}`,
      `${severities.changed_severity.level}`
    );
  }
);

Given('an enabled service severity is configured via APIv2', () => {
  cy.addSubjectViaApiV2(
    severities.enabled_severity,
    '/centreon/api/latest/configuration/services/severities'
  );
});

When(
  'an apiV2 call is made to "Disable" the configured service severity',
  () => {
    cy.updateSubjectViaApiV2(
      severities.disabled_severity,
      '/centreon/api/latest/configuration/services/severities/5'
    );
  }
);

Then(
  'a new "DISABLED" ligne of log is getting added to the page Administration > Logs',
  () => {
    openChangelogListing();
    assertLatestChangelogRow('service_critical', 'Disabled', 'Service severity');
  }
);

Given('a disabled service severity is configured via APIv2', () => {
  cy.addSubjectViaApiV2(
    severities.disabled_severity,
    '/centreon/api/latest/configuration/services/severities'
  );
});

When(
  'an apiV2 call is made to "Enable" the configured service severity',
  () => {
    cy.updateSubjectViaApiV2(
      severities.enabled_severity,
      '/centreon/api/latest/configuration/services/severities/5'
    );
  }
);

Then(
  'a new "ENABLED" ligne of log is getting added to the page Administration > Logs',
  () => {
    openChangelogListing();
    assertLatestChangelogRow('service_ok', 'Enabled', 'Service severity');
  }
);
