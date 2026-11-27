import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { INTERCEPTORS } from 'fixtures/shared/constants/interceptors';
import { PAGES } from 'fixtures/shared/constants/pages';

import {
  buildHostCheckQuery,
  buildPollerInstanceQuery,
  buildRemotePollerQuery,
  buildRunningProcessCommand,
  buildServiceCheckQuery,
  enabledPollersCountQuery,
  getPoller,
  listPollersWithIssue,
  type PollersIssues,
  pollerHostnameCommand,
  pollerListColumns
} from '../common';

interface ListedResource {
  last_check: string | null;
  monitoring_server_name: string | null;
  name: string;
  parent?: { name: string } | null;
  status: { name: string };
  type: string;
}

interface CheckRow {
  has_been_checked: number | string;
  last_check: number | string | null;
  now: number | string;
}

const hostName = 'rbt-poller-host';
const serviceName = 'rbt-poller-service';
const checkCommandName = 'rbt_poller_check';
// Check interval given to the host and its service, in minutes
const checkInterval = 1;
// The registration of the poller (container.d/60-register_central.sh of the
// poller image) and its first export can take several minutes
const pollerRegistrationTimeout = 600_000;
const checkResultTimeout = 300_000;
const maxFreshnessSeconds = 120;

let pollerName = '';
let pollerLastAlive = 0;
let pollerLastUpdateText = '';
let hostLastCheck = 0;
let listedHostLastCheck = '';

beforeEach(() => {
  cy.startContainers({ profiles: ['poller'] });

  pollerName = '';
  pollerLastAlive = 0;
  pollerLastUpdateText = '';
  hostLastCheck = 0;
  listedHostLastCheck = '';

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
    url: `${INTERCEPTORS.api.monitor_resources}?page`
  }).as('getResources');
});

afterEach(() => {
  cy.stopContainers();
});

const getPollerInstance = (): Cypress.Chainable =>
  cy
    .requestOnDatabase({
      database: 'centreon_storage',
      query: buildPollerInstanceQuery(pollerName)
    })
    .then(([rows]) => rows[0] ?? null);

const isFresh = (lastTime: number | string | null, now: number | string) =>
  lastTime !== null &&
  Number(lastTime) > 0 &&
  Number(now) - Number(lastTime) < maxFreshnessSeconds;

const waitForRunningPoller = (): void => {
  cy.execInContainer({ command: pollerHostnameCommand, name: 'poller' }).then(
    ({ output }) => {
      pollerName = output.trim();
      expect(pollerName, 'poller hostname').to.not.eq('');
    }
  );

  cy.waitUntil(
    () =>
      cy
        .requestOnDatabase({
          database: 'centreon',
          query: buildRemotePollerQuery(pollerName)
        })
        .then(([rows]) => rows.length === 1),
    {
      errorMsg: 'The poller is not declared on the central',
      interval: 5_000,
      timeout: pollerRegistrationTimeout
    }
  );

  cy.waitUntil(
    () =>
      getPollerInstance().then(
        (instance) =>
          instance !== null &&
          Number(instance.running) === 1 &&
          isFresh(instance.last_alive, instance.now)
      ),
    {
      errorMsg: 'The poller does not report a running status to the central',
      interval: 5_000,
      timeout: pollerRegistrationTimeout
    }
  );
};

const openPollersPage = (): void => {
  cy.visit(PAGES.configuration.pollersLegacy);
  cy.wait('@getTimeZone');
  cy.then(() => {
    cy.waitForElementInIframe('#main-content', `td:contains("${pollerName}")`);
  });
};

const pollerCell = (column: number): Cypress.Chainable =>
  cy
    .getIframeBody()
    .contains('td', pollerName)
    .parent('tr')
    .find('td')
    .eq(column);

const getCheckRow = (query: string): Cypress.Chainable<CheckRow | null> =>
  cy
    .requestOnDatabase({ database: 'centreon_storage', query })
    .then(([rows]) => (rows[0] ?? null) as CheckRow | null);

const waitForCheckResult = (query: string, label: string): void => {
  cy.waitUntil(
    () =>
      getCheckRow(query).then(
        (row) => row !== null && Number(row.has_been_checked) === 1
      ),
    {
      errorMsg: `${label} has not been checked`,
      interval: 5_000,
      timeout: checkResultTimeout
    }
  );
};

const findListed = (
  resources: Array<ListedResource>
): { host?: ListedResource; service?: ListedResource } => ({
  host: resources.find(
    ({ name, type }) => type === 'host' && name === hostName
  ),
  service: resources.find(
    ({ name, parent, type }) =>
      type === 'service' && name === serviceName && parent?.name === hostName
  )
});

type ListedPredicate = (
  host: ListedResource,
  service: ListedResource
) => boolean;

// Next listing response holding the host and its service as expected; the
// listing refreshes itself, so older responses are skipped
const waitForListedResources = (
  predicate: ListedPredicate,
  description: string,
  attempt = 0
): Cypress.Chainable<Array<ListedResource>> =>
  cy.wait('@getResources', { requestTimeout: 60_000 }).then(({ response }) => {
    const resources = (response?.body?.result ?? []) as Array<ListedResource>;
    const { host, service } = findListed(resources);

    if (host && service && predicate(host, service)) {
      return cy.wrap(resources);
    }

    if (attempt >= 20) {
      throw new Error(
        `No listing of ${hostName} and ${serviceName} ${description}`
      );
    }

    return waitForListedResources(predicate, description, attempt + 1);
  });

const isListed: ListedPredicate = () => true;

Given('an administrator is logged in a Centreon server', () => {
  cy.loginByTypeOfUser({ jsonName: 'admin', loginViaApi: false });
});

Given(
  'a poller is declared, exported and its Engine and Gorgone are running',
  () => {
    waitForRunningPoller();

    // A failing generation, test or reload makes the CLAPI call fail
    cy.then(() => cy.applyPollerConfiguration(pollerName));

    cy.execInContainer({
      command: buildRunningProcessCommand('[c]entengine'),
      name: 'poller'
    });
    cy.execInContainer({
      command: buildRunningProcessCommand('[g]orgoned'),
      name: 'poller'
    });

    waitForRunningPoller();
  }
);

Given('a poller is running and reports its status to the central', () => {
  waitForRunningPoller();
});

When('the user opens the pollers configuration page', () => {
  openPollersPage();
});

Then('the poller is displayed as running with a PID and a version', () => {
  getPollerInstance().then((instance) => {
    expect(instance, `instance of ${pollerName}`).to.not.eq(null);

    pollerCell(pollerListColumns.isRunning)
      .find('.service_ok')
      .should('contain', 'Yes');
    pollerCell(pollerListColumns.pid)
      .invoke('text')
      .then((text) => {
        expect(text.trim(), 'PID').to.match(/^\d+$/);
        expect(Number(text.trim()), 'PID').to.be.greaterThan(0);
      });
    pollerCell(pollerListColumns.version)
      .invoke('text')
      .then((text) => {
        expect(text.trim(), 'version').to.match(/\d+\.\d+/);
        expect(text.trim(), 'version').to.contain(String(instance.version));
      });
  });
});

Then('its last update is less than {int} minutes old', (minutes: number) => {
  expect(minutes * 60, 'freshness limit').to.eq(maxFreshnessSeconds);

  getPollerInstance().then((instance) => {
    expect(
      isFresh(instance.last_alive, instance.now),
      `last_alive ${instance.last_alive} (now ${instance.now})`
    ).to.eq(true);

    pollerLastAlive = Number(instance.last_alive);
  });

  pollerCell(pollerListColumns.lastUpdate)
    .invoke('text')
    .then((text) => {
      expect(text.trim(), 'last update').to.not.be.oneOf(['', '-']);

      pollerLastUpdateText = text.trim();
    });
});

When('the user opens the pollers tile of the top banner', () => {
  // Only the poller statuses fetched from now on are checked
  cy.intercept({
    method: 'GET',
    url: `${INTERCEPTORS.api.centreon_topcounter}&action=pollersListIssues`
  }).as('getPollersIssues');

  cy.get('header').getByLabel({ label: 'Pollers', tag: 'button' }).click();
  cy.get('[data-testid="poller-menu"]').should('be.visible');
});

Then(
  'the poller is counted as running without latency or stopped Engine',
  () => {
    // The tile refetches the poller statuses at each refresh interval
    cy.wait('@getPollersIssues', { requestTimeout: 60_000 }).then(
      ({ response }) => {
        const body = response?.body as PollersIssues;

        cy.requestOnDatabase({
          database: 'centreon',
          query: enabledPollersCountQuery
        }).then(([rows]) => {
          expect(body.total, 'pollers counted').to.eq(Number(rows[0].count));
        });

        ['latency', 'stability'].forEach((issue) => {
          expect(
            listPollersWithIssue(body, issue),
            `pollers with a ${issue} issue`
          ).to.not.include(pollerName);
        });
      }
    );

    cy.get('[data-testid="poller-menu"]').should('be.visible');
    cy.get('[data-testid="poller-menu"]')
      .contains('Pollers not running')
      .should('not.exist');
    cy.get('[data-testid="poller-menu"]')
      .contains('Latency detected')
      .should('not.exist');
  }
);

When(
  'the user waits {int} minutes and reloads the pollers configuration page',
  (minutes: number) => {
    cy.wait(minutes * 60_000);
    openPollersPage();
  }
);

Then('the last update of the poller has moved forward', () => {
  getPollerInstance().then((instance) => {
    expect(Number(instance.last_alive), 'last_alive').to.be.greaterThan(
      pollerLastAlive
    );
  });

  pollerCell(pollerListColumns.lastUpdate)
    .invoke('text')
    .then((text) => {
      expect(text.trim(), 'last update').to.not.be.oneOf(['', '-']);
      expect(text.trim(), 'last update').to.not.eq(pollerLastUpdateText);
    });
});

When(
  'the user creates a host monitored by the poller with one active service',
  () => {
    cy.addCheckCommand({
      command: 'echo rbt-poller-check-ok',
      name: checkCommandName
    });

    cy.then(() => {
      cy.addHost({
        activeCheckEnabled: true,
        checkCommand: checkCommandName,
        name: hostName,
        poller: pollerName,
        template: 'generic-host'
      });
    });
    cy.executeActionViaClapi({
      bodyContent: {
        action: 'SETPARAM',
        object: 'HOST',
        values: `${hostName};check_interval;${checkInterval}`
      }
    });

    cy.addService({
      activeCheckEnabled: true,
      checkCommand: checkCommandName,
      host: hostName,
      name: serviceName,
      template: 'generic-service'
    });
    cy.executeActionViaClapi({
      bodyContent: {
        action: 'SETPARAM',
        object: 'SERVICE',
        values: `${hostName};${serviceName};normal_check_interval;${checkInterval}`
      }
    });
  }
);

When('the user exports the poller configuration with reload', () => {
  cy.then(() => getPoller(pollerName)).then((pollerId) => {
    cy.visit(`${PAGES.configuration.pollersExportLegacy}&poller=${pollerId}`);
  });
  cy.wait('@getTimeZone');
  cy.waitForElementInIframe('#main-content', 'input[name="submit"]');

  cy.getIframeBody()
    .find('form span[class="selection"]')
    .eq(0)
    .should('contain', pollerName);

  ['gen', 'debug', 'move', 'restart'].forEach((action) => {
    // legacy checkboxes are hidden behind their label
    cy.getIframeBody()
      .find(`form input[name="${action}"]`)
      .eq(0)
      .check({ force: true });
  });
  cy.getIframeBody()
    .find('form select[name="restart_mode"]')
    .eq(0)
    .select('Reload');

  cy.getIframeBody().find('form input[name="submit"]').eq(0).click();

  cy.waitUntil(
    () =>
      cy
        .getIframeBody()
        .find('label#progressPct')
        .then((label) => label.text().trim() === '100%'),
    { errorMsg: 'The export did not end', interval: 1_000, timeout: 120_000 }
  );
});

Then('the export log reports no error', () => {
  cy.getIframeBody()
    .find('#consoleContent')
    .should('contain', 'OK')
    .invoke('text')
    .then((text) => {
      expect(text, 'export steps').to.not.contain('NOK');
    });

  cy.getIframeBody()
    .find('#consoleDetails')
    .invoke('text')
    .then((text) => {
      expect(text, 'Engine configuration test').to.match(/Total Errors:\s+0\b/);
    });

  cy.getIframeBody()
    .find('#error_log')
    .should('exist')
    .children()
    .should('have.length', 0);
});

When('the user opens the Resources status page and searches the host', () => {
  cy.visit(PAGES.monitoring.resourcesStatus);
  cy.wait('@getResources');

  cy.get('input[placeholder="Search"]').type(
    `{selectall}{backspace}${hostName}{enter}`
  );
});

Then('the host and its service are no longer in PENDING status', () => {
  waitForCheckResult(buildHostCheckQuery(hostName), hostName);
  waitForCheckResult(
    buildServiceCheckQuery(hostName, serviceName),
    serviceName
  );

  cy.get('[aria-label="Refresh"]').click();

  waitForListedResources(
    (host, service) =>
      host.status.name !== 'PENDING' && service.status.name !== 'PENDING',
    'out of PENDING'
  ).then((resources) => {
    const { host, service } = findListed(resources);

    expect(host?.status.name, `status of ${hostName}`).to.not.eq('PENDING');
    expect(service?.status.name, `status of ${serviceName}`).to.not.eq(
      'PENDING'
    );
  });

  cy.contains(serviceName).should('be.visible');
  cy.contains(hostName).should('be.visible');
  cy.contains(/^Pending$/).should('not.exist');
});

Then('their last check is less than {int} minutes old', (minutes: number) => {
  expect(minutes * 60, 'freshness limit').to.eq(maxFreshnessSeconds);

  [
    { label: hostName, query: buildHostCheckQuery(hostName) },
    {
      label: serviceName,
      query: buildServiceCheckQuery(hostName, serviceName)
    }
  ].forEach(({ label, query }) => {
    getCheckRow(query).then((row) => {
      expect(row, label).to.not.eq(null);
      expect(
        isFresh(row?.last_check ?? null, row?.now ?? 0),
        `last check of ${label} (${row?.last_check}, now ${row?.now})`
      ).to.eq(true);

      if (label === hostName) {
        hostLastCheck = Number(row?.last_check);
      }
    });
  });
});

Then('the monitoring server displayed for the host is the poller', () => {
  cy.get('[aria-label="Refresh"]').click();

  waitForListedResources(isListed, 'listed').then((resources) => {
    const { host } = findListed(resources);

    expect(host?.monitoring_server_name, 'monitoring server').to.eq(pollerName);
    expect(host?.last_check, 'last check of the host').to.not.eq(null);

    listedHostLastCheck = host?.last_check ?? '';
  });

  cy.contains(hostName).click();
  cy.contains('Monitoring server')
    .closest('.MuiCard-root')
    .should('contain', pollerName);
});

When('the user waits one check interval and refreshes the page', () => {
  cy.wait(checkInterval * 60_000);

  cy.waitUntil(
    () =>
      getCheckRow(buildHostCheckQuery(hostName)).then(
        (row) => row !== null && Number(row.last_check) > hostLastCheck
      ),
    {
      errorMsg: `${hostName} has not been checked again`,
      interval: 5_000,
      timeout: checkResultTimeout
    }
  );

  cy.reload();
});

Then('the last check of the host has moved forward', () => {
  waitForListedResources(
    (host) =>
      new Date(host.last_check ?? 0).getTime() >
      new Date(listedHostLastCheck).getTime(),
    'with a newer last check of the host'
  ).then((resources) => {
    const { host } = findListed(resources);

    expect(
      new Date(host?.last_check ?? 0).getTime(),
      'last check of the host'
    ).to.be.greaterThan(new Date(listedHostLastCheck).getTime());
  });
});
