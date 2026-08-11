import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { INTERCEPTORS } from 'fixtures/shared/constants/interceptors';
import { PAGES } from 'fixtures/shared/constants/pages';

import periods from '../../../fixtures/time-periods/time-period.json';
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
  cy.intercept({
    method: 'GET',
    url: `${INTERCEPTORS.pages.time_period_object}&object_id=5&searchU=&searchO=&otype=`
  }).as('getTimePeriod');
});

afterEach(() => {
  cy.stopContainers();
});

Given('a user is logged in a Centreon server via APIv2', () => {
  cy.loginAsAdminViaApiV2();
  cy.visit('/').url().should('include', '/monitoring/resources');
});

When('a call to the endpoint "Add" a time period is done via APIv2', () => {
  cy.addTimePeriodViaApi({
    ...periods.default,
    days: periods.default.days.map((day) => ({
      ...day,
      timeRange: day.time_range
    })),
    exceptions: periods.default.exceptions,
    templates: periods.default.templates
  });
});

Then('a new time period is displayed on the time periods page', () => {
  cy.visit(PAGES.configuration.timePeriodsLegacy);
  cy.wait('@getTimeZone');
  cy.getIframeBody().contains('a', periods.default.name).should('be.visible');
});

Then(
  'a new "Added" ligne of log is getting added to the page Administration > Logs',
  () => {
    openChangelogListing();
    assertLatestChangelogRow('service_ok', 'Added', 'Time period');
  }
);

Then(
  'the informations of the log are the same as those passed to the endpoint',
  () => {
    openObjectTimeline(periods.default.name);
    cy.expandTimelineCard('Added');
    // Added diff has no "Before" column, so `before` is '' for every field.
    cy.checkLogDetail('name', '', periods.default.name);
    cy.checkLogDetail('alias', '', periods.default.alias);
    cy.checkLogDetail('monday', '', periods.default.days[0].time_range);
    cy.checkLogDetail('tuesday', '', periods.default.days[1].time_range);
    cy.checkLogDetail('wednesday', '', periods.default.days[2].time_range);
    cy.checkLogDetail('thursday', '', periods.default.days[3].time_range);
    cy.checkLogDetail('friday', '', periods.default.days[4].time_range);
    cy.checkLogDetail('saturday', '', periods.default.days[5].time_range);
    cy.checkLogDetail('sunday', '', periods.default.days[6].time_range);
  }
);

Given('a time period is configured via APIv2', () => {
  cy.addTimePeriodViaApi({
    ...periods.default,
    days: periods.default.days.map((day) => ({
      ...day,
      timeRange: day.time_range
    })),
    exceptions: periods.default.exceptions,
    templates: periods.default.templates
  });
});

When(
  'a call to the endpoint "Update" a time period is done on the configured time period via APIv2',
  () => {
    cy.updateTimePeriodViaApi(periods.default.name, {
      ...periods.time_period1,
      days: periods.time_period1.days.map((day) => ({
        ...day,
        timeRange: day.time_range
      })),
      exceptions: periods.time_period1.exceptions,
      templates: periods.time_period1.templates
    });
  }
);

Then(
  'a new "Changed" ligne of log is getting added to the page Administration > Logs',
  () => {
    openChangelogListing();
    assertLatestChangelogRow('service_warning', 'Changed', 'Time period');
  }
);

Then(
  'the informations of the log are the same as those of the updated time period',
  () => {
    openObjectTimeline(periods.time_period1.name);
    cy.expandTimelineCard('Changed');
    cy.checkLogDetail('name', periods.default.name, periods.time_period1.name);
    cy.checkLogDetail(
      'alias',
      periods.default.alias,
      periods.time_period1.alias
    );
  }
);

When(
  'a call to the endpoint "Delete" a time period is done on the configured time period via APIv2',
  () => {
    cy.deleteTimePeriodViaApi(periods.default.name);
  }
);

Then(
  'a new "Deleted" ligne of log is getting added to the page Administration > Logs',
  () => {
    openChangelogListing();
    assertLatestChangelogRow('service_critical', 'Deleted', 'Time period');
  }
);
