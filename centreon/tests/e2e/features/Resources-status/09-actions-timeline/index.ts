import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { INTERCEPTORS } from 'fixtures/shared/constants/interceptors';
import { PAGES } from 'fixtures/shared/constants/pages';

import {
  checkHostsAreMonitored,
  checkServicesAreMonitored
} from '../../../commons';
import {
  buildHostLastCheckQuery,
  buildServiceAcknowledgementQuery,
  buildServiceCommentQuery,
  buildServiceDowntimeQuery,
  buildSetDefaultDowntimeDurationQuery,
  typeToSearchInput
} from '../common';

interface DowntimeRow {
  actual_end_time: number | null;
  end_time: number;
  fixed: number;
  start_time: number;
  started: number;
}

interface TimelineEvent {
  content: string;
  type: string;
}

// APPLYCFG reloads Broker a few seconds after Engine: a result sent while
// its SQL output restarts never reaches the real-time tables
const brokerReloadWaitingTime = 15_000;
const downtimeDuration = 180;
// The fixed downtime ends 3 minutes after it is set: leave room for Engine
// to end it and Broker to write it
const downtimeEndTimeout = 300_000;
const engineTimeout = 60_000;
const timelineTimeout = 30_000;

const comments = {
  acknowledgement: 'rbt action acknowledgement',
  downtime: 'rbt action downtime',
  timeline: 'rbt action timeline comment'
};

let actionHost = '';
let actionService = '';
let lastCheckBeforeForcedCheck = 0;

beforeEach(() => {
  cy.startContainers();

  actionHost = '';
  actionService = '';
  lastCheckBeforeForcedCheck = 0;

  cy.intercept({
    method: 'GET',
    url: INTERCEPTORS.api.navigation_list
  }).as('getNavigationList');
  cy.intercept({
    method: 'GET',
    url: `${INTERCEPTORS.api.monitor_resources}?*`
  }).as('getResources');
  cy.intercept({
    method: 'POST',
    url: `${INTERCEPTORS.api.monitor_resources}/acknowledge`
  }).as('postAcknowledge');
  cy.intercept({
    method: 'POST',
    url: `${INTERCEPTORS.api.monitor_resources}/downtime`
  }).as('postDowntime');
  cy.intercept({
    method: 'POST',
    url: `${INTERCEPTORS.api.monitor_resources}/comments`
  }).as('postComment');
  cy.intercept({
    method: 'POST',
    url: `${INTERCEPTORS.api.monitor_resources}/check`
  }).as('postCheck');
  cy.intercept({
    method: 'DELETE',
    url: `${INTERCEPTORS.api.monitor_resources}/acknowledgements`
  }).as('deleteAcknowledgement');
  cy.intercept({
    method: 'GET',
    url: `${INTERCEPTORS.api.service_timeline}?*`
  }).as('getTimeline');
});

afterEach(() => {
  cy.stopContainers();
});

const queryCount = (query: string): Cypress.Chainable<number> =>
  cy
    .requestOnDatabase({ database: 'centreon_storage', query })
    .then(([rows]) => cy.wrap(Number(rows[0]?.count ?? 0), { log: false }));

const waitForStoredRow = (query: string, label: string): void => {
  cy.waitUntil(() => queryCount(query).then((count) => count > 0), {
    errorMsg: `${label} not stored in the real-time database`,
    interval: 2_000,
    timeout: engineTimeout
  });
};

const hostLastCheck = (host: string): Cypress.Chainable<number> =>
  cy
    .requestOnDatabase({
      database: 'centreon_storage',
      query: buildHostLastCheckQuery(host)
    })
    .then(([rows]) => {
      expect(rows, `host ${host}`).to.have.length(1);

      return cy.wrap(Number(rows[0].last_check ?? 0), { log: false });
    });

const lastDowntimeOf = (
  host: string,
  service: string
): Cypress.Chainable<DowntimeRow | undefined> =>
  cy
    .requestOnDatabase({
      database: 'centreon_storage',
      query: buildServiceDowntimeQuery(host, service)
    })
    .then(([rows]) =>
      cy.wrap(rows[0] as DowntimeRow | undefined, { log: false })
    );

// Lists only the resource of that type and name, whatever its state
const listOnly = (type: 'host' | 'service', name: string): void => {
  typeToSearchInput(`type:${type} ${name}`);
  cy.wait('@getResources');
};

const resourceRow = (name: string): Cypress.Chainable =>
  cy.contains('[role="row"]', name);

const selectRow = (name: string): void => {
  resourceRow(name).find('input[type="checkbox"]').first().click();
};

const detailPanel = (): Cypress.Chainable =>
  cy.get('#panel-content').parents('.MuiPaper-root').first();

const openTimelineOf = (service: string): void => {
  listOnly('service', service);
  resourceRow(service).contains(service).click();

  detailPanel().find('[role="tab"][aria-label="Timeline"]').click();
  detailPanel()
    .find('[role="tab"][aria-label="Timeline"]')
    .should('have.attr', 'aria-selected', 'true');
};

const closeDetailPanel = (): void => {
  detailPanel().find('button[aria-label="Close"]').first().click();
  cy.get('#panel-content').should('not.exist');
};

const stateChip = (service: string, state: string): Cypress.Chainable =>
  cy.getByLabel({ label: `${service} ${state}` });

// Consumes the timeline requests until one lists every expected content
const waitForTimelineContents = (
  expected: Array<string>,
  deadline: number
): void => {
  cy.wait('@getTimeline', { timeout: timelineTimeout }).then(({ response }) => {
    const events = (response?.body.result ?? []) as Array<TimelineEvent>;
    const contents = events.map(({ content }) => content);

    if (expected.every((content) => contents.includes(content))) {
      return;
    }

    if (Date.now() > deadline) {
      throw new Error(`The timeline does not list ${expected.join(', ')}`);
    }

    waitForTimelineContents(expected, deadline);
  });
};

Given(
  'a host {word} with an active ping check and its passive service {word} are monitored',
  (host: string, service: string) => {
    actionHost = host;
    actionService = service;

    cy.addHost({
      activeCheckEnabled: true,
      checkCommand: 'check_centreon_ping',
      name: host,
      template: 'generic-host'
    });
    cy.addService({
      activeCheckEnabled: false,
      host,
      maxCheckAttempts: 1,
      name: service,
      template: 'Ping-LAN'
    });
    cy.applyPollerConfiguration();
    checkHostsAreMonitored([{ name: host }]);
    checkServicesAreMonitored([{ name: service }]);
    cy.wait(brokerReloadWaitingTime);

    // The downtime form proposes a fixed downtime of this duration: set
    // before the page loads its parameters
    cy.requestOnDatabase({
      database: 'centreon',
      query: buildSetDefaultDowntimeDurationQuery(downtimeDuration)
    });
  }
);

Given('{word} is Critical after a submitted status', (service: string) => {
  cy.submitResults([
    {
      host: actionHost,
      output: 'rbt action critical',
      service,
      status: 'critical'
    }
  ]);
  checkServicesAreMonitored([
    { name: service, output: 'rbt action critical', status: 'critical' }
  ]);

  cy.loginByTypeOfUser({ jsonName: 'admin', loginViaApi: true });
  cy.visit(PAGES.monitoring.resourcesStatus);
  cy.wait('@getResources');

  // The State column carries the acknowledged and in downtime chips
  cy.getByLabel({ label: 'Add columns' }).click();
  cy.get('li[role="menuitem"][value="State"]').click();
  cy.getByLabel({ label: 'Add columns' }).click();

  listOnly('service', service);
  resourceRow(service).should('contain', 'Critical');
});

When(
  'the user acknowledges {word} from Resources Status with a comment',
  (service: string) => {
    selectRow(service);
    cy.getByTestId({ testId: 'mainAcknowledge' }).click();

    cy.get('textarea[data-testid="Comment"]')
      .clear()
      .type(comments.acknowledgement);
    cy.get('button[data-testid="Confirm"]').contains('Acknowledge').click();

    cy.wait('@postAcknowledge').its('response.statusCode').should('eq', 204);
    cy.contains('Acknowledge command sent').should('be.visible');
  }
);

Then(
  'the service is marked as acknowledged in the listing and the acknowledgement is stored by Engine',
  () => {
    checkServicesAreMonitored([{ acknowledged: true, name: actionService }]);
    waitForStoredRow(
      buildServiceAcknowledgementQuery(
        actionHost,
        actionService,
        comments.acknowledgement
      ),
      'The acknowledgement'
    );

    cy.refreshListing();
    stateChip(actionService, 'Acknowledged').should('be.visible');
  }
);

When(
  'the user sets a fixed downtime of {int} minutes on {word}',
  (minutes: number, service: string) => {
    expect(minutes * 60, 'default downtime duration').to.eq(downtimeDuration);

    selectRow(service);
    cy.getByTestId({ testId: 'mainSetDowntime' }).click();

    cy.getByLabel({ label: 'Fixed', tag: 'input' }).should('be.checked');
    cy.get('textarea[data-testid="Comment"]').clear().type(comments.downtime);
    cy.get('button[data-testid="Confirm"]').contains('Set downtime').click();

    cy.wait('@postDowntime').then(({ request, response }) => {
      expect(response?.statusCode).to.eq(204);

      const { downtime } = request.body;
      const lasting =
        (Date.parse(downtime.end_time) - Date.parse(downtime.start_time)) /
        1000;

      expect(downtime.is_fixed).to.eq(true);
      expect(lasting).to.be.within(downtimeDuration - 1, downtimeDuration + 1);
    });
    cy.contains('Downtime command sent').should('be.visible');
  }
);

Then(
  'the service is marked as in downtime and the downtime is started by Engine',
  () => {
    cy.waitForDowntime({ host: actionHost, service: actionService });
    checkServicesAreMonitored([{ inDowntime: true, name: actionService }]);

    lastDowntimeOf(actionHost, actionService).then((downtime) => {
      expect(Number(downtime?.started)).to.eq(1);
      expect(Number(downtime?.fixed)).to.eq(1);
    });

    cy.refreshListing();
    stateChip(actionService, 'In downtime').should('be.visible');
  }
);

When(
  'the user adds a comment from the Timeline tab of {word}',
  (service: string) => {
    openTimelineOf(service);
    cy.wait('@getTimeline');

    cy.get('#panel-content').find('[data-testid="addComment"]').click();
    cy.get('textarea[data-testid="commentArea"]').type(comments.timeline);
    cy.get('#panel-content').find('[data-testid="Save"]').click();

    cy.wait('@postComment').its('response.statusCode').should('eq', 204);
    cy.contains('Your comment has been saved.').should('be.visible');
  }
);

Then('the comment is saved and stored by Engine', () => {
  waitForStoredRow(
    buildServiceCommentQuery(actionHost, actionService, comments.timeline),
    'The comment'
  );

  closeDetailPanel();
});

When('the user forces a check on {word}', (host: string) => {
  hostLastCheck(host).then((lastCheck) => {
    lastCheckBeforeForcedCheck = lastCheck;
  });

  listOnly('host', host);
  selectRow(host);
  cy.getByTestId({ testId: 'mainCheck' })
    .should('have.attr', 'aria-label', 'Forced check')
    .click();

  cy.wait('@postCheck').then(({ request, response }) => {
    expect(response?.statusCode).to.eq(204);
    expect(request.body.check.is_forced).to.eq(true);
  });
  cy.contains('Forced check command sent').should('be.visible');
});

Then('the last check time of the host is refreshed', () => {
  cy.waitUntil(
    () =>
      hostLastCheck(actionHost).then(
        (lastCheck) => lastCheck > lastCheckBeforeForcedCheck
      ),
    {
      errorMsg: 'The last check time of the host did not change',
      interval: 2_000,
      timeout: engineTimeout
    }
  );
});

Then(
  'the Timeline tab of {word} lists the acknowledgement, the downtime and the comment',
  (service: string) => {
    const expected = [
      comments.acknowledgement,
      comments.downtime,
      comments.timeline
    ];

    openTimelineOf(service);
    cy.then(() => {
      waitForTimelineContents(expected, Date.now() + timelineTimeout);
    });

    expected.forEach((content) => {
      cy.get('#panel-content').should('contain', content);
    });

    closeDetailPanel();
  }
);

When(
  'the downtime end time is reached and the user disacknowledges {word}',
  (service: string) => {
    cy.waitUntil(
      () =>
        lastDowntimeOf(actionHost, service).then(
          (downtime) => !!downtime?.actual_end_time
        ),
      {
        errorMsg: 'Engine did not end the downtime',
        interval: 5_000,
        timeout: downtimeEndTimeout
      }
    );

    listOnly('service', service);
    selectRow(service);
    cy.getByLabel({ label: 'More actions' }).click();
    // The More actions tooltip covers the menu item
    cy.getByTestId({ tag: 'li', testId: 'Multiple Disacknowledge' }).click({
      force: true
    });
    cy.get('button[data-testid="Confirm"]').contains('Disacknowledge').click();

    cy.wait('@deleteAcknowledgement')
      .its('response.statusCode')
      .should('eq', 204);
  }
);

Then('the service is no longer marked as in downtime nor acknowledged', () => {
  checkServicesAreMonitored([
    { acknowledged: false, inDowntime: false, name: actionService }
  ]);

  cy.refreshListing();
  resourceRow(actionService).should('contain', 'Critical');
  stateChip(actionService, 'Acknowledged').should('not.exist');
  stateChip(actionService, 'In downtime').should('not.exist');
});
