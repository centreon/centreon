import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { INTERCEPTORS } from 'fixtures/shared/constants/interceptors';
import { PAGES } from 'fixtures/shared/constants/pages';

import {
  expectedSeconds,
  isoDay,
  monitoredServiceQuery,
  readEmittedDowntimeCommands,
  recurrentCases,
  scheduledWindowFor,
  transitionDay
} from '../common';

interface ResolvedService {
  hostId: number;
  hostName: string;
  serviceId: number;
  serviceDescription: string;
}

let service: ResolvedService;
let appliedKey: string;

// Configure a recurrent downtime (every day, given start/end wall-clock times)
// on the monitored service, then run downtimeManager.php with the clock frozen
// just before the downtime is due. The cron emits the SCHEDULE_SVC_DOWNTIME
// external command (with the DST-resolved timestamps) into the centcore command
// file, which the assertions read back — no dependency on the engine pipeline.
const applyRecurrentDowntime = (key: string): void => {
  appliedKey = key;
  const { transition, start, end, faketime } = recurrentCases[key];
  // faketime is optional on DstCase (realtime cases omit it) but mandatory here.
  if (!faketime) {
    throw new Error(`Recurrent case '${key}' is missing a faketime`);
  }
  const day = transitionDay(transition);

  cy.visit(`${PAGES.monitoring.recurrentDowntimesLegacy}&o=a`);
  cy.waitForElementInIframe('#main-content', 'input[name="downtime_name"]');

  cy.getIframeBody()
    .find('input[name="downtime_name"]')
    .clear()
    .type(`dst-${key}`);

  // apply on every day of the week
  cy.getIframeBody()
    .find('input[name="periods[1][days][]"]')
    .check({ force: true });

  cy.getIframeBody()
    .find('input[name="periods[1][start_period]"]')
    .clear()
    .type(start.time);
  cy.getIframeBody()
    .find('input[name="periods[1][end_period]"]')
    .clear()
    .type(end.time);

  // Link the monitored service via the svc_relation select2 (multiple: the real
  // <select id="svc_relation"> is hidden, its widget is the next sibling). Scope
  // the inline search to that widget, then pick from svc_relation's own results.
  const serviceLabel = `${service.hostName} - ${service.serviceDescription}`;
  cy.getIframeBody()
    .find('#svc_relation')
    .next('.select2-container')
    .find('input.select2-search__field')
    .type(serviceLabel, { force: true });
  cy.getIframeBody()
    .find('ul[id="select2-svc_relation-results"] li')
    .contains(serviceLabel)
    .first()
    .click();

  cy.getIframeBody().find('input[name="submitA"]').eq(0).click();

  // Freeze the clock to the faketime wall time on the transition day and run the
  // recurrent-downtime cron so it emits SCHEDULE_SVC_DOWNTIME for that day.
  const frozenMoment = `${isoDay(day, faketime.dayOffset)} ${faketime.time}:00`;
  cy.execInContainer({
    command: `TZ='Europe/Paris' faketime '${frozenMoment}' php /usr/share/centreon/cron/downtimeManager.php`,
    name: 'web'
  });
};

beforeEach(() => {
  cy.startContainers();

  // loginByTypeOfUser waits on this alias, so it must be registered up front.
  cy.intercept({ method: 'GET', url: INTERCEPTORS.api.navigation_list }).as(
    'getNavigationList'
  );

  // The legacy downtime form alert()s when its moment.js (browser TZ) treats the
  // DST wall-clock time as inconsistent. Accept it deterministically.
  cy.on('window:alert', () => true);
});

// NB: the slash in "Europe/Paris" must be escaped — in a Cucumber Expression an
// unescaped "/" means alternation, so the literal step text would never match.
Given('a user logged in with the Europe\\/Paris timezone', () => {
  cy.executeActionViaClapi({
    bodyContent: {
      action: 'SETPARAM',
      object: 'CONTACT',
      values: 'admin;timezone;Europe/Paris'
    }
  });
  // The recurrent-downtime cron resolves the period in the HOST timezone
  // (getHostCurrentDatetime). It must be Europe/Paris so the DST gap is applied
  // and the 600s scheduling window catches the (clamped) start.
  cy.executeActionViaClapi({
    bodyContent: {
      action: 'SETPARAM',
      object: 'HOST',
      values: 'Centreon-Server;timezone;Europe/Paris'
    }
  });
  cy.loginByTypeOfUser({ jsonName: 'admin', loginViaApi: true });
});

Given('a passive service is monitored', () => {
  // reuse the first monitored service of the central host from the dataset
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

When(
  'a recurrent downtime starting at the non-existent spring-forward time is applied',
  () => applyRecurrentDowntime('springStartClamped')
);
When(
  'a recurrent downtime ending at the non-existent spring-forward time is applied',
  () => applyRecurrentDowntime('springEndClamped')
);
When(
  'a recurrent downtime fully inside the spring-forward gap is applied',
  () => applyRecurrentDowntime('springInsideGap')
);
When(
  'a recurrent downtime covering the whole spring-forward day is applied',
  () => applyRecurrentDowntime('springFullDay')
);
When(
  'a recurrent downtime covering the day after the spring-forward transition is applied',
  () => applyRecurrentDowntime('springNextDay')
);
When(
  'a recurrent downtime starting in the repeated fall-back hour is applied',
  () => applyRecurrentDowntime('fallStart')
);
When(
  'a recurrent downtime ending in the repeated fall-back hour is applied',
  () => applyRecurrentDowntime('fallEnd')
);
When(
  'a recurrent downtime fully inside the repeated fall-back hour is applied',
  () => applyRecurrentDowntime('fallInsideHour')
);
When('a recurrent downtime covering the whole fall-back day is applied', () =>
  applyRecurrentDowntime('fallFullDay')
);
When(
  'a recurrent downtime covering the day after the fall-back transition is applied',
  () => applyRecurrentDowntime('fallNextDay')
);

Then(
  'the scheduled downtime matches the expected start, end and duration',
  () => {
    const { transition, expected } = recurrentCases[appliedKey];
    if (expected === null) {
      throw new Error(`Case '${appliedKey}' has no expected scheduled window`);
    }
    const day = transitionDay(transition);
    // start opens on the earlier occurrence of an ambiguous time, end closes on
    // the later one (how the engine widens a downtime across the repeated hour).
    const expectedStart = expectedSeconds(day, expected.start, 'earlier');
    const expectedEnd = expectedSeconds(day, expected.end, 'later');

    readEmittedDowntimeCommands().then((commands) => {
      const scheduled = scheduledWindowFor(
        commands,
        `SCHEDULE_SVC_DOWNTIME;${service.hostName};${service.serviceDescription};`
      );
      expect(scheduled, 'the cron should emit a SCHEDULE_SVC_DOWNTIME command')
        .to.not.be.null;
      expect(scheduled?.start, 'downtime start').to.equal(expectedStart);
      expect(scheduled?.end, 'downtime end').to.equal(expectedEnd);
      expect(
        (scheduled?.end ?? 0) - (scheduled?.start ?? 0),
        'downtime duration'
      ).to.equal(expected.durationSeconds);
    });
  }
);

Then('the downtime is not scheduled', () => {
  // A window fully inside the spring-forward gap is rejected when the cron
  // converts the wall-clock times: no SCHEDULE_SVC_DOWNTIME command is emitted.
  readEmittedDowntimeCommands().then((commands) => {
    const scheduled = scheduledWindowFor(
      commands,
      `SCHEDULE_SVC_DOWNTIME;${service.hostName};${service.serviceDescription};`
    );
    expect(scheduled, 'no downtime should be scheduled inside the DST gap').to
      .be.null;
  });
});

afterEach(() => {
  cy.stopContainers();
});
