import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { INTERCEPTORS } from 'fixtures/shared/constants/interceptors';
import { PAGES } from 'fixtures/shared/constants/pages';

import { submitResultsViaClapi } from '../../../commons';
import { downtimeLifecycleQuery, monitoredServiceQuery } from '../common';

interface ResolvedService {
  hostId: number;
  hostName: string;
  serviceId: number;
  serviceDescription: string;
}

// One downtimes row of centreon_storage, as read back through cy.requestOnDatabase.
// actual_start_time / actual_end_time are set by the engine when the downtime
// actually activates / ends; they are unset (null or 0) while still pending.
interface DowntimeRow {
  actual_start_time: number | null;
  actual_end_time: number | null;
}

type DowntimeLifecycle = 'pending' | 'active' | 'over';

// Open the window now for a couple of minutes so the downtime is immediately
// due; flexible downtimes also carry a short duration so their end is
// observable without a long real wait.
const windowMinutes = 2;
const flexibleDurationSeconds = 30;

let service: ResolvedService;

// Date (MM/DD/YYYY) and time (HH:mm) of "now + offsetMinutes" in Europe/Paris,
// the timezone pinned on the user so the form reads the values as intended.
const parisFormDateTime = (
  offsetMinutes: number
): { date: string; time: string } => {
  const instant = new Date(Date.now() + offsetMinutes * 60000);
  const parts = new Intl.DateTimeFormat('en-US', {
    day: '2-digit',
    hour: '2-digit',
    hour12: false,
    minute: '2-digit',
    month: '2-digit',
    timeZone: 'Europe/Paris',
    year: 'numeric'
  })
    .formatToParts(instant)
    .reduce(
      (acc, part) => ({ ...acc, [part.type]: part.value }),
      {} as Record<string, string>
    );

  return {
    date: `${parts.month}/${parts.day}/${parts.year}`,
    // hour12:false can format midnight as "24" in some engines; normalise to 00.
    time: `${parts.hour === '24' ? '00' : parts.hour}:${parts.minute}`
  };
};

// Submit a passive check result for the service through the web API (the same
// path the product uses), driving its state to OK ('0') or CRITICAL ('2').
const submitServiceStatus = (status: '0' | '2', output: string): void => {
  submitResultsViaClapi([
    {
      host: service.hostName,
      output,
      service: service.serviceDescription,
      status,
      updatetime: `${Math.floor(Date.now() / 1000)}`
    }
  ]);
};

// Latest non-cancelled downtime lifecycle row for the service (or null).
const readDowntime = (): Cypress.Chainable<DowntimeRow | null> =>
  cy
    .requestOnDatabase({
      database: 'centreon_storage',
      query: downtimeLifecycleQuery(service.hostId, service.serviceId)
    })
    .then(([rows]) => (rows.length === 0 ? null : (rows[0] as DowntimeRow)));

// Classify a downtimes row into its lifecycle state. The engine writes
// actual_start_time / actual_end_time as epoch seconds when the downtime
// activates / ends; "unset" can surface as null, 0 or "0" depending on the DB
// driver, so treat "present and > 0" as set. Check "over" before "active".
const downtimeState = (row: DowntimeRow | null): DowntimeLifecycle => {
  if (row === null) {
    return 'pending';
  }

  const isSet = (value: number | null): boolean =>
    value != null && Number(value) > 0;

  if (isSet(row.actual_end_time)) {
    return 'over';
  }
  if (isSet(row.actual_start_time)) {
    return 'active';
  }

  return 'pending';
};

// Poll the database until the latest downtime reaches the expected state.
const waitForDowntimeState = (expected: DowntimeLifecycle): void => {
  cy.waitUntil(
    () => readDowntime().then((row) => downtimeState(row) === expected),
    {
      errorMsg: `the downtime never reached the '${expected}' state`,
      interval: 5000,
      // Bounded by the worst case: the "fixed ends" scenario must outlast the
      // full windowMinutes window (2 min) before the engine ends the downtime,
      // plus engine/broker processing margin.
      timeout: 180000
    }
  );
};

// Schedule a downtime on the service through the legacy monitoring form (the
// proven web -> gorgone -> engine path), open from now for windowMinutes.
const scheduleDowntime = (fixed: boolean): void => {
  const start = parisFormDateTime(0);
  const end = parisFormDateTime(windowMinutes);

  cy.visit(
    `${PAGES.monitoring.downtimesLegacy}&o=a&host_id=${service.hostId}&service_id=${service.serviceId}`
  );
  cy.waitForElementInIframe('#main-content', 'input[name="start_time"]');

  cy.getIframeBody().find('input[name="start"]').clear().type(start.date);
  cy.getIframeBody().find('input[name="start_time"]').clear().type(start.time);
  cy.getIframeBody().find('input[name="end"]').clear().type(end.date);
  cy.getIframeBody().find('input[name="end_time"]').clear().type(end.time);

  // Drive the "Fixed" flag explicitly rather than trusting its default state
  // (its input name is "persistant"; the id is "fixed"). An unchecked box means
  // a flexible downtime, which would never activate without a non-OK result.
  if (fixed) {
    cy.getIframeBody().find('#fixed').check({ force: true });
  } else {
    cy.getIframeBody().find('#fixed').uncheck({ force: true });
    // Flexible: give the downtime a short duration (field is in seconds).
    cy.getIframeBody()
      .find('input[name="duration"]')
      .clear()
      .type(`${flexibleDurationSeconds}`);
  }

  cy.getIframeBody().find('input[name="submitA"]').click();
};

beforeEach(() => {
  cy.startContainers();

  // loginByTypeOfUser waits on this alias, so it must be registered up front.
  cy.intercept({ method: 'GET', url: INTERCEPTORS.api.navigation_list }).as(
    'getNavigationList'
  );
});

Given('a passive service is monitored', () => {
  // The downtime form reads start/end in the user timezone; pin it to
  // Europe/Paris BEFORE logging in so the session picks the timezone up.
  cy.executeActionViaClapi({
    bodyContent: {
      action: 'SETPARAM',
      object: 'CONTACT',
      values: 'admin;timezone;Europe/Paris'
    }
  });
  cy.loginByTypeOfUser({ jsonName: 'admin', loginViaApi: true });

  // Reuse the first monitored service of the central host from the dataset: it
  // is already known to the monitoring engine, so the downtime form can schedule
  // on it (a freshly CLAPI-created service is not yet monitorable).
  cy.requestOnDatabase({
    database: 'centreon',
    query: monitoredServiceQuery()
  }).then(([rows]) => {
    expect(
      rows.length,
      'expected at least one active service on the central host'
    ).to.be.greaterThan(0);

    service = {
      hostId: Number(rows[0].host_id),
      hostName: String(rows[0].host_name),
      serviceDescription: String(rows[0].service_description),
      serviceId: Number(rows[0].service_id)
    };
  });
});

Given('a fixed downtime is scheduled on the service for the next minutes', () =>
  scheduleDowntime(true)
);
Given(
  'a flexible downtime is scheduled on the service for the next minutes',
  () => scheduleDowntime(false)
);

When('the downtime start time is reached', () =>
  waitForDowntimeState('active')
);

When('the service becomes critical within the downtime window', () =>
  submitServiceStatus('2', 'CRITICAL - e2e')
);

When('the downtime end time is reached', () => waitForDowntimeState('over'));

When('the downtime duration has elapsed', () => waitForDowntimeState('over'));

// Single binding for "the service downtime is active" — used both as a Then
// (scenarios 1 & 3) and as a Given precondition (scenarios 2 & 4); Given/When/Then
// are interchangeable in the preprocessor, so one definition matches every usage.
Then('the service downtime is active', () => {
  waitForDowntimeState('active');

  // UI check: the active downtime is listed on the monitoring downtimes page.
  cy.visit(PAGES.monitoring.downtimesLegacy);
  cy.waitForElementInIframe('#main-content', 'table');
  cy.getIframeBody().contains('td', service.hostName).should('be.visible');
});

Then('the service downtime is over', () => waitForDowntimeState('over'));

afterEach(() => {
  cy.stopContainers();
});
