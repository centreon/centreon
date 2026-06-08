import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { INTERCEPTORS } from 'fixtures/shared/constants/interceptors';
import { PAGES } from 'fixtures/shared/constants/pages';

import {
  cases,
  centralHost,
  expectedSeconds,
  formDate,
  grepCountInEngineLog,
  latestDowntimeQuery,
  monitoredServiceQuery,
  transitionDay,
  truncateEngineLog
} from '../common';

interface ResolvedService {
  hostId: number;
  serviceId: number;
}

let service: ResolvedService;
let appliedKey: string;

beforeEach(() => {
  cy.startContainers();

  // loginByTypeOfUser waits on this alias, so it must be registered up front.
  cy.intercept({ method: 'GET', url: INTERCEPTORS.api.navigation_list }).as(
    'getNavigationList'
  );

  // The legacy downtime form alert()s when its moment.js (browser TZ) treats the
  // DST wall-clock time as inconsistent. Accept it deterministically.
  cy.on('window:alert', () => true);

  // Start each scenario with an empty engine log, so the downtime command can
  // be detected deterministically (see the "not scheduled" assertion).
  cy.execInContainer({
    command: truncateEngineLog(),
    name: 'web'
  });
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
  cy.loginByTypeOfUser({ jsonName: 'admin', loginViaApi: true });
});

Given('a passive service is monitored', () => {
  // reuse the first monitored service of the central host from the dataset
  cy.requestOnDatabase({
    database: 'centreon',
    query: monitoredServiceQuery()
  }).then(([rows]) => {
    service = {
      hostId: Number(rows[0].host_id),
      serviceId: Number(rows[0].service_id)
    };
  });
});

const applyRealtimeDowntime = (key: string): void => {
  appliedKey = key;
  const { transition, start, end } = cases[key];
  const day = transitionDay(transition);

  cy.visit(
    `${PAGES.monitoring.downtimesLegacy}&o=a&host_id=${service.hostId}&service_id=${service.serviceId}`
  );
  cy.waitForElementInIframe('#main-content', 'input[name="start_time"]');

  cy.getIframeBody()
    .find('input[name="start"]')
    .clear()
    .type(formDate(day, start.dayOffset));
  cy.getIframeBody().find('input[name="start_time"]').clear().type(start.time);
  cy.getIframeBody()
    .find('input[name="end"]')
    .clear()
    .type(formDate(day, end.dayOffset));
  cy.getIframeBody().find('input[name="end_time"]').clear().type(end.time);

  // Guard: the datepicker JS can silently recompute end_time from the duration.
  // Fail loudly if our value was overwritten rather than submit wrong data.
  cy.getIframeBody()
    .find('input[name="end_time"]')
    .should('have.value', end.time);

  cy.getIframeBody().find('input[name="submitA"]').click();
};

When(
  'a realtime downtime covering the whole spring-forward day is applied',
  () => applyRealtimeDowntime('fullSpring')
);
When('a realtime downtime covering the whole fall-back day is applied', () =>
  applyRealtimeDowntime('fullFall')
);
When(
  'a realtime downtime starting at the non-existent spring-forward time is applied',
  () => applyRealtimeDowntime('nonExistentStart')
);
When('a realtime downtime fully inside the spring-forward gap is applied', () =>
  applyRealtimeDowntime('insideGap')
);

Then(
  'the scheduled downtime matches the expected start, end and duration',
  () => {
    const { transition, expected } = cases[appliedKey];
    if (expected === null) {
      throw new Error(`Case '${appliedKey}' has no expected scheduled window`);
    }
    const day = transitionDay(transition);
    const expectedStart = expectedSeconds(day, expected.start);
    const expectedEnd = expectedSeconds(day, expected.end);

    cy.waitUntil(
      () =>
        cy
          .requestOnDatabase({
            database: 'centreon_storage',
            query: latestDowntimeQuery(service.hostId, service.serviceId)
          })
          .then(([rows]) => {
            if (rows.length === 0) return false;
            const start = Number(rows[0].start_time);
            const end = Number(rows[0].end_time);

            return (
              start === expectedStart &&
              end === expectedEnd &&
              end - start === expected.durationSeconds
            );
          }),
      {
        errorMsg:
          'Scheduled downtime does not match expected start/end/duration',
        timeout: 60000
      }
    );

    // UI check: the scheduled downtime is visible in the monitoring list.
    cy.visit(PAGES.monitoring.downtimesLegacy);
    cy.waitForElementInIframe('#main-content', 'table');
    cy.getIframeBody().contains('td', centralHost).should('be.visible');
  }
);

Then('the downtime is not scheduled', () => {
  // Wait until the engine has processed the downtime command (proof it had its
  // chance), then assert no downtime row was created. The external command is
  // logged on receipt even when the engine ends up scheduling nothing.
  cy.waitUntil(
    () =>
      cy
        .execInContainer({
          command: grepCountInEngineLog('SCHEDULE_SVC_DOWNTIME'),
          name: 'web'
        })
        .then(({ output }) => Number(output.trim()) >= 1),
    {
      errorMsg: 'engine never processed the downtime command',
      timeout: 60000
    }
  );

  cy.requestOnDatabase({
    database: 'centreon_storage',
    query: latestDowntimeQuery(service.hostId, service.serviceId)
  }).then(([rows]) => {
    expect(
      rows.length,
      'no downtime should be scheduled inside the DST gap'
    ).to.eq(0);
  });

  // UI check: the monitoring list confirms nothing was scheduled.
  cy.visit(PAGES.monitoring.downtimesLegacy);
  cy.waitForElementInIframe('#main-content', 'table');
  cy.getIframeBody().contains('No downtime scheduled').should('be.visible');
});

afterEach(() => {
  cy.stopContainers();
});
