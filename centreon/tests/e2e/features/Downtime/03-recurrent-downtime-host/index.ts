import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { INTERCEPTORS } from 'fixtures/shared/constants/interceptors';
import { PAGES } from 'fixtures/shared/constants/pages';

import {
  centralHost,
  readEmittedDowntimeCommands,
  scheduledWindowFor
} from '../common';

// Fixed mid-day window (no DST, no midnight wrap), with the cron frozen a few
// minutes before the start so the period is reliably due — deterministic
// regardless of the real wall-clock time when the test runs.
const startTime = '12:00';
const endTime = '18:00';
const cronTime = '11:56:00';

// Today's date (YYYY-MM-DD) in Europe/Paris, for the faketime clock.
const todayInParis = (): string =>
  new Intl.DateTimeFormat('en-CA', { timeZone: 'Europe/Paris' }).format(
    new Date()
  );

beforeEach(() => {
  cy.startContainers();

  // loginByTypeOfUser waits on this alias, so it must be registered up front.
  cy.intercept({ method: 'GET', url: INTERCEPTORS.api.navigation_list }).as(
    'getNavigationList'
  );

  // The legacy downtime form alert()s when its moment.js (browser TZ) treats the
  // wall-clock time as inconsistent. Accept it deterministically.
  cy.on('window:alert', () => true);
});

Given('a user logged in with the Europe\\/Paris timezone', () => {
  // Match the step wording: the logged-in user is in Europe/Paris. Kept aligned
  // with the DST recurrent test so identical step text behaves identically.
  cy.executeActionViaClapi({
    bodyContent: {
      action: 'SETPARAM',
      object: 'CONTACT',
      values: 'admin;timezone;Europe/Paris'
    }
  });
  // The recurrent-downtime cron resolves the period in the host timezone; set it
  // to Europe/Paris so "now" (the faked clock) and the period agree.
  cy.executeActionViaClapi({
    bodyContent: {
      action: 'SETPARAM',
      object: 'HOST',
      values: `${centralHost};timezone;Europe/Paris`
    }
  });
  cy.loginByTypeOfUser({ jsonName: 'admin', loginViaApi: true });
});

When('a recurrent downtime on a host is applied', () => {
  cy.visit(`${PAGES.monitoring.recurrentDowntimesLegacy}&o=a`);
  cy.waitForElementInIframe('#main-content', 'input[name="downtime_name"]');

  cy.getIframeBody()
    .find('input[name="downtime_name"]')
    .clear()
    .type('dt-recurrent-host');

  // apply on every day of the week
  cy.getIframeBody()
    .find('input[name="periods[1][days][]"]')
    .check({ force: true });

  cy.getIframeBody()
    .find('input[name="periods[1][start_period]"]')
    .clear()
    .type(startTime);
  cy.getIframeBody()
    .find('input[name="periods[1][end_period]"]')
    .clear()
    .type(endTime);

  // Link the host via the host_relation select2 (multiple: the real
  // <select id="host_relation"> is hidden, its widget is the next sibling).
  cy.getIframeBody()
    .find('#host_relation')
    .next('.select2-container')
    .find('input.select2-search__field')
    .type(centralHost, { force: true });
  cy.getIframeBody()
    .find('ul[id="select2-host_relation-results"] li')
    .contains(centralHost)
    .first()
    .click();

  cy.getIframeBody().find('input[name="submitA"]').eq(0).click();

  // Freeze the clock a few minutes before the start so the period is due, then
  // run the recurrent-downtime cron (it writes SCHEDULE_HOST_DOWNTIME).
  cy.execInContainer({
    command: `TZ='Europe/Paris' faketime '${todayInParis()} ${cronTime}' php /usr/share/centreon/cron/downtimeManager.php`,
    name: 'web'
  });
});

Then('a host downtime is scheduled', () => {
  // The cron must emit a host downtime for the targeted host.
  readEmittedDowntimeCommands().then((commands) => {
    const scheduled = scheduledWindowFor(
      commands,
      `SCHEDULE_HOST_DOWNTIME;${centralHost};`
    );
    expect(
      scheduled,
      'the cron should emit a SCHEDULE_HOST_DOWNTIME command for the host'
    ).to.not.be.null;
    expect(
      (scheduled?.end ?? 0) - (scheduled?.start ?? 0),
      'the scheduled downtime has a positive duration'
    ).to.be.greaterThan(0);
  });
});

afterEach(() => {
  cy.stopContainers();
});
