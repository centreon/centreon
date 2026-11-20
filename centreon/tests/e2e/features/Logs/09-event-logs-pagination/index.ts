import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { INTERCEPTORS } from 'fixtures/shared/constants/interceptors';
import { PAGES } from 'fixtures/shared/constants/pages';

import { buildInsertEventLogsQuery } from '../common';

type ListAction = 'next page' | 'CSV export' | 'XML export';

interface Interception {
  request: { url: string };
}

const eventRows = () =>
  cy.getIframeBody().find('#logView4xml table.ListTable tr:not(.ListHeader)');

const pageButtons = (name: 'previousPage' | 'nextPage') =>
  cy.getIframeBody().find(`#logView4xml input[name="${name}"]`);

const pageSizeSelect = () =>
  cy.getIframeBody().find('#logView4xml select[name="l"]').first();

const requestParam = (
  interception: Interception,
  name: string
): string | null => new URL(interception.request.url).searchParams.get(name);

const searchEvents = (output: string, pageSize: number): void => {
  cy.getIframeBody().find('#output').clear().type(output);
  cy.getIframeBody().find('#div1 input[name="graph"]').click();
  cy.wait('@eventLogsSearch');
  pageSizeSelect().select(String(pageSize));
  cy.wait('@eventLogsSearch');
};

const goToNextPage = (): void => {
  pageButtons('nextPage').first().click();
  cy.wait('@eventLogsSearch');
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
  cy.wait('@eventLogsSearch');
});

When('the administrator clicks the "Run query" button', () => {
  cy.getIframeBody().find('#div1 input[name="graph"]').click();
  cy.wait('@eventLogsSearch');
});

When('the administrator changes the page size to {int}', (pageSize: number) => {
  pageSizeSelect().select(String(pageSize));
  cy.wait('@eventLogsSearch');
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
      cy.wait('@eventLogsSearch').as('listActionRequest');

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

Then(
  'the {string} buttons are {word}',
  (label: string, state: 'enabled' | 'disabled') => {
    const name = label === 'Previous page' ? 'previousPage' : 'nextPage';
    // One button above the list, one below.
    pageButtons(name)
      .should('have.length', 2)
      .and(state === 'enabled' ? 'not.be.disabled' : 'be.disabled');
  }
);

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
