import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import type { Interception } from 'cypress/types/net-stubbing';
import { INTERCEPTORS } from 'fixtures/shared/constants/interceptors';
import { PAGES } from 'fixtures/shared/constants/pages';

import {
  buildInsertEventLogsQuery,
  runQueryButton,
  waitForSearch
} from '../common';

type ListAction = 'next page' | 'CSV export' | 'XML export';

type PageButtonName = 'previousPage' | 'nextPage';

// A typo in the .feature file must fail, not check another button or state.
const pageButtonNames: Record<string, PageButtonName> = {
  'Next page': 'nextPage',
  'Previous page': 'previousPage'
};

const buttonStates: Record<string, boolean> = {
  disabled: true,
  enabled: false
};

const eventRows = () =>
  cy.getIframeBody().find('#logView4xml table.ListTable tr:not(.ListHeader)');

const pageButtons = (name: PageButtonName) =>
  cy.getIframeBody().find(`#logView4xml input[name="${name}"]`);

const pageSizeSelect = () =>
  cy.getIframeBody().find('#logView4xml select[name="l"]').first();

const requestParam = (
  interception: Interception,
  name: string
): string | null => new URL(interception.request.url).searchParams.get(name);

// The events are inserted a few seconds before the search. A relative period keeps
// them in range whatever the time of day and the timezones of the browser and PHP,
// unlike the default range of today from 00:00 to 24:00.
const searchPeriodInSeconds = '10800';

const searchEvents = (output: string, pageSize: number): void => {
  cy.getIframeBody()
    .find('select[name="period"]')
    .select(searchPeriodInSeconds);
  cy.getIframeBody().find('#output').clear().type(output);
  runQueryButton().click();
  waitForSearch().then((interception) => {
    expect(requestParam(interception, 'period')).to.eq(searchPeriodInSeconds);
  });
  pageSizeSelect().select(String(pageSize));
  waitForSearch();
};

const goToNextPage = (): void => {
  pageButtons('nextPage').first().click();
  waitForSearch();
};

let exportedFile = '';

beforeEach(() => {
  exportedFile = '';
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
    pathname: INTERCEPTORS.pages.event_logs_search
  }).as('eventLogsSearch');
});

Given('an administrator is logged in', () => {
  cy.loginByTypeOfUser({ jsonName: 'admin', loginViaApi: true });
});

Given(
  '{int} events with the output {string} exist',
  (count: number, output: string) => {
    cy.requestOnDatabase({
      database: 'centreon_storage',
      query: buildInsertEventLogsQuery({ count, msgType: 0, output })
    });
  }
);

Given(
  '{int} engine messages with the output {string} exist',
  (count: number, output: string) => {
    cy.requestOnDatabase({
      database: 'centreon_storage',
      query: buildInsertEventLogsQuery({ count, msgType: 4, output })
    });
  }
);

Given('the administrator opens the Event Logs page', () => {
  cy.visit(PAGES.monitoring.eventLogsLegacy);
  cy.wait('@getTimeZone');
  cy.waitForElementInIframe('#main-content', 'select#host_filter');
});

When(
  'the administrator searches the events with the output {string} and {int} events per page',
  searchEvents
);

Given(
  'the administrator searched the events with the output {string} and {int} events per page',
  searchEvents
);

When('the administrator goes to the next page', goToNextPage);

Given('the administrator went to the next page', goToNextPage);

When('the administrator goes to the previous page', () => {
  pageButtons('previousPage').last().click();
  waitForSearch();
});

When('the administrator clicks the "Run query" button', () => {
  runQueryButton().click();
  waitForSearch();
});

When('the administrator changes the page size to {int}', (pageSize: number) => {
  pageSizeSelect().select(String(pageSize));
  waitForSearch();
});

When(
  'the administrator changes the output filter to {string} without running the query',
  (output: string) => {
    cy.getIframeBody().find('#output').clear().type(output);
  }
);

When(
  'the administrator uses the {word} {word}',
  (first: string, second: string) => {
    const action = `${first} ${second}` as ListAction;

    if (action === 'next page') {
      pageButtons('nextPage').first().click();
      waitForSearch().as('listActionRequest');

      return;
    }

    // The export opens a download in place of the page: keep the real file for the
    // checks, and send an empty one to the browser.
    const exportPath =
      action === 'CSV export'
        ? INTERCEPTORS.pages.event_logs_export
        : INTERCEPTORS.pages.event_logs_search;
    cy.intercept(
      { method: 'GET', pathname: exportPath, query: { export: '1' } },
      (request) => {
        request.continue((response) => {
          exportedFile = String(response.body);
          response.send({ body: '' });
        });
      }
    ).as('exportRequest');
    cy.getIframeBody()
      .find(`#logView4xml a[href*="'${action.split(' ')[0]}'"]`)
      .first()
      .click();
    cy.wait('@exportRequest').as('listActionRequest');
  }
);

Then('{int} events are displayed', (count: number) => {
  eventRows().should('have.length', count);
});

Then('the {string} buttons are {word}', (label: string, state: string) => {
  const name = pageButtonNames[label];
  const disabled = buttonStates[state];
  if (name === undefined || disabled === undefined) {
    throw new Error(`Unknown page button "${label}" or state "${state}"`);
  }
  // One button above the list, one below: each one is checked, since
  // `be.disabled` on a set passes as soon as one element is disabled.
  pageButtons(name).should(($buttons) => {
    expect($buttons).to.have.length(2);
    $buttons.each((_, button) => {
      expect((button as HTMLInputElement).disabled, `${label} button`).to.eq(
        disabled
      );
    });
  });
});

Then('the Event Logs search asks for page {int}', (page: number) => {
  cy.get<Interception>('@eventLogsSearch').then((interception) => {
    expect(requestParam(interception, 'num')).to.eq(String(page));
  });
});

Then(
  'the Event Logs search asks for page {int} with {int} events per page',
  (page: number, pageSize: number) => {
    cy.get<Interception>('@eventLogsSearch').then((interception) => {
      expect(requestParam(interception, 'num')).to.eq(String(page));
      expect(requestParam(interception, 'limit')).to.eq(String(pageSize));
    });
  }
);

Then(
  'the request of the {word} {word} filters on the output {string}',
  (_first: string, _second: string, output: string) => {
    cy.get<Interception>('@listActionRequest').then((interception) => {
      expect(requestParam(interception, 'output')).to.eq(output);
    });
  }
);

Then('the XML export contains {int} events', (count: number) => {
  cy.wrap(null).then(() => {
    const lines = new DOMParser()
      .parseFromString(exportedFile, 'text/xml')
      .getElementsByTagName('line');
    expect(lines.length).to.eq(count);
  });
});

When(
  'the engine logs list is requested with {int} messages per page',
  (pageSize: number) => {
    cy.request({
      qs: {
        alert: 'false',
        critical: 'false',
        down: 'false',
        engine: 'true',
        error: 'true',
        id: '',
        limit: pageSize,
        num: 1,
        ok: 'false',
        output: 'E2E_ENGINE',
        period: 86400,
        unknown: 'false',
        unreachable: 'false',
        up: 'false',
        warning: 'false'
      },
      url: INTERCEPTORS.pages.event_logs_search
    }).as('engineLogs');
  }
);

Then('the engine logs list offers numbered pages', () => {
  cy.get('@engineLogs')
    .its('body')
    .then((body: string) => {
      const pages = new DOMParser()
        .parseFromString(body, 'text/xml')
        .getElementsByTagName('page');
      expect(pages.length).to.be.greaterThan(1);
    });
});

afterEach(() => {
  cy.stopContainers();
});
