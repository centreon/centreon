import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { INTERCEPTORS } from 'fixtures/shared/constants/interceptors';
import { PAGES } from 'fixtures/shared/constants/pages';

import {
  checkHostsAreMonitored,
  checkServicesAreMonitored
} from '../../../commons';
import {
  buildServiceLastResultQuery,
  formatEnglishDateTime,
  normalizeSpaces,
  typeToSearchInput
} from '../common';

interface ServiceLastResult {
  last_check: number;
  output: string;
  poller: string;
}

interface ListedResource {
  name: string;
}

interface Interception {
  request: { url: string };
  response?: { body: { result: Array<ListedResource> } };
}

// APPLYCFG reloads Broker a few seconds after Engine: a result sent while
// its SQL output restarts never reaches the real-time tables
const brokerReloadWaitingTime = 15_000;
// The listing reloads one refresh interval after the previous load ended:
// leave room for that load and for the request itself
const refreshMargin = 5_000;
const searchPrefix = 'rbt-view';
const noReloadMarker = 'rbtNoReloadMarker';

const results = {
  critical: { output: 'rbt view critical', status: 'critical' },
  ok: { output: 'rbt view ok', status: 'ok' },
  warning: { output: 'rbt view warning', status: 'warning' }
};

let refreshTimeout = 0;
let currentHost = '';
let currentService = '';

beforeEach(() => {
  cy.startContainers();

  refreshTimeout = 0;
  currentHost = '';
  currentService = '';

  cy.intercept({
    method: 'GET',
    url: INTERCEPTORS.api.navigation_list
  }).as('getNavigationList');
  cy.intercept({
    method: 'GET',
    url: `${INTERCEPTORS.api.monitor_resources}?*`
  }).as('getResources');
});

afterEach(() => {
  cy.stopContainers();
});

// Status chips print the status name capitalized: OK reads "Ok"
const statusLabel = (status: string): string =>
  `${status[0].toUpperCase()}${status.slice(1)}`;

const createPassiveService = (host: string, service: string): void => {
  cy.addHost({
    activeCheckEnabled: false,
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
};

// Waits until the real-time tables hold the result: the page can only show
// it from there
const submitServiceResult = (
  host: string,
  service: string,
  { output, status }: { output: string; status: string }
): void => {
  cy.submitResults([{ host, output, service, status }]);
  checkServicesAreMonitored([{ name: service, output, status }]);
};

const loadRefreshInterval = (): void => {
  cy.request({
    method: 'GET',
    url: INTERCEPTORS.api.administration_parameters
  }).then(({ body }) => {
    const interval = Number(body.monitoring_default_refresh_interval);

    expect(interval, 'refresh interval in seconds').to.be.greaterThan(0);
    refreshTimeout = interval * 1000 + refreshMargin;
  });
};

const selectStateFilter = (filterId: string): void => {
  cy.getByLabel({ label: 'State filter' }).click();
  cy.get(`[data-value="${filterId}"]`).click();
  cy.wait('@getResources');
};

const visitListingOfAllResources = (): void => {
  cy.visit(PAGES.monitoring.resourcesStatus);
  cy.wait('@getResources');
  selectStateFilter('all');
  typeToSearchInput(searchPrefix);
  cy.wait('@getResources');
};

const resourceRow = (name: string, timeout?: number): Cypress.Chainable =>
  cy.contains('[role="row"]', name, timeout ? { timeout } : {});

// Detail panel: the paper around its content
const detailPanel = (timeout?: number): Cypress.Chainable =>
  cy
    .get('#panel-content', timeout ? { timeout } : {})
    .parents('.MuiPaper-root', timeout ? { timeout } : {})
    .first();

const detailTile = (title: string): Cypress.Chainable =>
  cy
    .get('#panel-content')
    .contains(new RegExp(`^${title}$`))
    .closest('.MuiPaper-root');

// Consumes the listing requests sent by the autorefresh until one matches,
// failing once the deadline is passed
const waitForListing = (
  matches: (names: Array<string>, url: string) => boolean,
  deadline: number
): void => {
  cy.wait('@getResources', { timeout: refreshTimeout }).then((interception) => {
    const { request, response } = interception as unknown as Interception;
    const names = (response?.body.result ?? []).map(({ name }) => name);

    if (matches(names, decodeURIComponent(request.url))) {
      return;
    }

    if (Date.now() > deadline) {
      throw new Error('No listing refresh matched within the interval');
    }

    waitForListing(matches, deadline);
  });
};

const lastResultOf = (
  host: string,
  service: string
): Cypress.Chainable<ServiceLastResult> =>
  cy
    .requestOnDatabase({
      database: 'centreon_storage',
      query: buildServiceLastResultQuery(host, service)
    })
    .then(([rows]) => {
      expect(rows, `last result of ${service}`).to.have.length(1);

      return cy.wrap(rows[0] as ServiceLastResult, { log: false });
    });

// MON-214305

Given(
  'a host {word} with a passive service {word} exported and listed in Resources status',
  (host: string, service: string) => {
    currentHost = host;
    currentService = service;
    createPassiveService(host, service);
    submitServiceResult(host, service, results.ok);

    cy.loginByTypeOfUser({ jsonName: 'admin', loginViaApi: true });
    loadRefreshInterval();
    visitListingOfAllResources();
    resourceRow(service).should('contain', statusLabel('ok'));
  }
);

Given('the listing autorefresh is enabled', () => {
  // The button offers to disable the autorefresh only while it runs
  cy.getByLabel({ label: 'Disable autorefresh' }).should('be.visible');

  cy.window().then((win) => {
    (win as unknown as Record<string, boolean>)[noReloadMarker] = true;
  });
});

When(
  'a Critical check result is submitted for {word} outside the UI',
  (service: string) => {
    submitServiceResult(currentHost, service, results.critical);
  }
);

Then(
  'within one refresh interval the row of {word} shows the status Critical and the submitted output without a page reload',
  (service: string) => {
    // Module state set by earlier commands: read once they ran
    cy.then(() => {
      resourceRow(service, refreshTimeout)
        .should('contain', statusLabel('critical'))
        .and('contain', results.critical.output);
    });

    // A reload would have dropped the property set on the window
    cy.window().its(noReloadMarker).should('eq', true);
  }
);

Then(
  '{word} is listed under the Unhandled alerts filter',
  (service: string) => {
    selectStateFilter('unhandled_problems');

    resourceRow(service)
      .should('contain', statusLabel('critical'))
      .and('contain', results.critical.output);
  }
);

When('an Ok check result is submitted for {word}', (service: string) => {
  submitServiceResult(currentHost, service, results.ok);
});

Then(
  'within one refresh interval {word} leaves the Unhandled alerts listing',
  (service: string) => {
    cy.then(() => {
      waitForListing(
        (names, url) =>
          url.includes('unhandled_problems') && !names.includes(service),
        Date.now() + refreshTimeout
      );
    });

    cy.contains('Unhandled alerts').should('be.visible');
    cy.contains('[role="row"]', service).should('not.exist');
    cy.window().its(noReloadMarker).should('eq', true);
  }
);

Then('it is listed Ok under the All filter', () => {
  selectStateFilter('all');
  typeToSearchInput(searchPrefix);
  cy.wait('@getResources');

  resourceRow(currentService)
    .should('contain', statusLabel('ok'))
    .and('contain', results.ok.output);
});

// MON-214306

Given(
  'a service {word} on host {word} listed in Resources status with a known last check result',
  (service: string, host: string) => {
    currentHost = host;
    currentService = service;
    createPassiveService(host, service);
    submitServiceResult(host, service, results.warning);

    cy.loginByTypeOfUser({ jsonName: 'admin', loginViaApi: true });
    loadRefreshInterval();
    visitListingOfAllResources();
    resourceRow(service).should('contain', statusLabel('warning'));
  }
);

When('the user clicks the row of {word}', (service: string) => {
  resourceRow(service).contains(service).click();
});

Then(
  'the detail panel opens on the Details tab with the service name, its host and its status',
  () => {
    detailPanel()
      .find('[role="tab"][aria-label="Details"]')
      .should('have.attr', 'aria-selected', 'true');

    detailPanel()
      .should('contain', currentService)
      .and('contain', currentHost)
      .and('contain', statusLabel('warning'));
  }
);

Then(
  'the Status information, Last check and Monitoring server tiles match the last result stored for the service',
  () => {
    lastResultOf(currentHost, currentService).then(
      ({ last_check, output, poller }) => {
        detailTile('Status information').should('contain', output);
        detailTile('Monitoring server').should('contain', poller);

        cy.request({
          method: 'GET',
          url: INTERCEPTORS.api.users_parameters
        }).then(({ body }) => {
          const expected = formatEnglishDateTime(
            Number(last_check),
            body.timezone
          );

          detailTile('Last check')
            .invoke('text')
            .then((text) => {
              expect(normalizeSpaces(text)).to.include(
                normalizeSpaces(expected)
              );
            });
        });
      }
    );
  }
);

When(
  'a new check result is submitted for {word} outside the UI',
  (service: string) => {
    submitServiceResult(currentHost, service, results.critical);
  }
);

Then(
  'within one refresh interval the panel shows the new status and output',
  () => {
    cy.then(() => {
      cy.get('#panel-content', { timeout: refreshTimeout }).should(
        'contain',
        results.critical.output
      );
      detailTile('Status information').should(
        'contain',
        results.critical.output
      );

      detailPanel(refreshTimeout).should('contain', statusLabel('critical'));
    });
  }
);

When('the user opens the host {word} from the panel', (host: string) => {
  detailPanel()
    .contains('span', new RegExp(`^${host}$`))
    .click();

  detailPanel()
    .find('[role="tab"][aria-label="Services"]')
    .should('be.visible')
    .click();
});

Then('the Services tab lists {word} with its status', (service: string) => {
  detailPanel()
    .find('[role="tab"][aria-label="Services"]')
    .should('have.attr', 'aria-selected', 'true');

  cy.get('#panel-content')
    .contains(service)
    .closest('.MuiPaper-root')
    .should('contain', statusLabel('critical'));
});
