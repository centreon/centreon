import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { INTERCEPTORS } from 'fixtures/shared/constants/interceptors';
import { PAGES } from 'fixtures/shared/constants/pages';

const sgPage = PAGES.configuration.servicesGroupsLegacy;
const byHostPage = PAGES.configuration.servicesByHostLegacy;
const seededPrefix = 'pg_group_';
const seededCount = 12;
const pageSize = 10;

// One service attached to two hosts is what makes the by-host count interesting:
// it renders two rows, so a count over distinct service ids reports one and
// leaves the second row behind a page the user cannot reach.
const sharedHostA = 'pg_host_a';
const sharedHostB = 'pg_host_b';
const sharedService = 'pg_shared_service';

// "1-10 of 42" — the three numbers the count query drives.
const paginationWindow = (): Cypress.Chainable<Array<number>> =>
  cy
    .getIframeBody()
    .find('#clPaginationTop .cl-page-info')
    .invoke('text')
    .then((text) => {
      const match = /(\d+)-(\d+) of (\d+)/.exec(String(text).trim());
      if (match === null) {
        throw new Error(`Unexpected pagination text: ${text}`);
      }

      return [Number(match[1]), Number(match[2]), Number(match[3])];
    });

// The arrows carry a translated title and no id; the test platform runs in
// en_US. Matching the class alone would be positional, which is worse.
// An active control is an <a>, a disabled one a <span> — both keep the class.
const navControl = (title: string): Cypress.Chainable =>
  cy.getIframeBody().find(`#clPaginationTop .cl-page-nav[title="${title}"]`);

// A data row carries the picker cell. renderEmptyState() emits a <tr> without
// one, and the template ships a static "Loading..." <tr> before any fetch.
const dataRows = (): Cypress.Chainable =>
  cy.getIframeBody().find('#clTableBody tr .cl-col-picker');

const rowTexts = (expectedCount: number): Cypress.Chainable<Array<string>> =>
  cy
    .getIframeBody()
    .find('#clTableBody tr')
    .should('have.length', expectedCount)
    .then(($rows) => Cypress._.map($rows, (row) => row.innerText));

// The rendered rows must match the window the counter announces. renderPagination
// derives startRow/endRow from total/num/limit alone, so asserting those against
// each other proves nothing — only the row count can contradict a wrong total.
const assertWindowMatchesRows = (): void => {
  paginationWindow().then(([first, last]) => {
    dataRows().should('have.length', last - first + 1);
  });
};

const openAtTenPerPage = (page: string, alias: string): void => {
  cy.visitListingAndWait(page);
  // visitListingAndWait only waits for a <tr> to exist, which the static
  // "Loading..." row satisfies before any request is issued. Consume the initial
  // fetch here, otherwise every later cy.wait resolves on the previous one.
  cy.wait(alias);
  cy.getIframeBody()
    .find('#clPaginationTop select.cl-limit-select')
    .select(String(pageSize));
  cy.wait(alias);
};

beforeEach(() => {
  cy.startContainers();
  // CLAPI needs the v1 token; without it the seeding helpers answer 403.
  cy.setUserTokenApiV1();
  // loginByTypeOfUser waits on this alias internally, whatever loginViaApi says.
  cy.intercept({
    method: 'GET',
    url: INTERCEPTORS.api.navigation_list
  }).as('getNavigationList');
  cy.intercept('GET', '**/ajaxServiceGroupListing.php*').as('listing');
  cy.intercept('GET', '**/ajaxServiceByHostListing.php*').as('byHostListing');
});

afterEach(() => {
  cy.stopContainers();
});

Given('a user is logged in a Centreon server', () => {
  cy.loginByTypeOfUser({ jsonName: 'admin' });
});

Given('more service groups exist than a single page holds', () => {
  Cypress._.range(seededCount).forEach((index) => {
    const suffix = String(index).padStart(2, '0');
    cy.addServiceGroup({
      alias: `Pagination group ${suffix}`,
      hostsAndServices: [],
      name: `${seededPrefix}${suffix}`
    });
  });
});

Given('a service is attached to two hosts', () => {
  cy.addHost({
    hostGroup: '',
    name: sharedHostA,
    template: 'generic-host'
  }).addService({
    activeCheckEnabled: false,
    host: sharedHostA,
    maxCheckAttempts: 1,
    name: sharedService,
    template: 'Ping-LAN'
  });
  cy.addHost({
    hostGroup: '',
    name: sharedHostB,
    template: 'generic-host'
  });
  // CLAPI attaches a service to a single host; the second link is what makes one
  // service render two rows, so it is inserted directly.
  cy.requestOnDatabase({
    database: 'centreon',
    query: `INSERT INTO host_service_relation (host_host_id, service_service_id)
            SELECT h.host_id, s.service_id
            FROM host h, service s
            WHERE h.host_name = '${sharedHostB}'
              AND s.service_description = '${sharedService}'
              AND s.service_register = '1'`
  });
});

When('the user navigates to the service groups listing', () => {
  cy.visitListingAndWait(sgPage);
  cy.wait('@listing');
});

When('the user changes the rows per page to 10', () => {
  cy.getIframeBody()
    .find('#clPaginationTop select.cl-limit-select')
    .select(String(pageSize));
  cy.wait('@listing');
});

Then('exactly 10 rows are displayed', () => {
  // "at most" would pass on a listing holding fewer rows than the limit, which
  // is why the Background seeds more than a page.
  dataRows().should('have.length', pageSize);
});

Then(
  'the pagination total matches the number of service groups in the database',
  () => {
    cy.requestOnDatabase({
      database: 'centreon',
      query: 'SELECT COUNT(*) AS total FROM servicegroup'
    }).then(([rows]) => {
      const expected = Number(rows[0].total);
      expect(expected, 'seeded groups reached the database').to.be.at.least(
        seededCount
      );
      paginationWindow().then(([first, last, total]) => {
        expect(total, 'reported total').to.equal(expected);
        expect(first, 'first row index').to.equal(1);
        expect(last, 'last row index').to.equal(pageSize);
      });
    });
  }
);

Given('the user is on the first page of ten service groups', () => {
  openAtTenPerPage(sgPage, '@listing');
  rowTexts(pageSize).as('firstPageRows');
});

When('the user goes to the next page', () => {
  navControl('Next page').click();
  cy.wait('@listing');
});

Then('the pagination window moves to the second page', () => {
  paginationWindow().then(([first]) => {
    expect(first, 'first row index').to.equal(pageSize + 1);
  });
  assertWindowMatchesRows();
});

Then('none of the first page rows are shown again', () => {
  // "different from page 1" would also accept an off-by-one offset serving rows
  // 2-11. The listing orders by sg_name, so the two pages must not intersect.
  cy.get('@firstPageRows').then((firstPage) => {
    const seen = firstPage as unknown as Array<string>;
    cy.getIframeBody()
      .find('#clTableBody tr')
      .then(($rows) => {
        const current = Cypress._.map($rows, (row) => row.innerText);
        expect(
          Cypress._.intersection(current, seen),
          'rows repeated across pages'
        ).to.be.empty;
      });
  });
});

When('the user goes to the last page', () => {
  navControl('Last page').click();
  cy.wait('@listing');
});

Then('the last page holds the rows the counter announces', () => {
  // A total larger than the rows the listing can serve leaves this page blank.
  dataRows().should('have.length.at.least', 1);
  assertWindowMatchesRows();
});

Then('the next and last controls are disabled', () => {
  navControl('Next page').should('have.class', 'cl-page-nav--disabled');
  navControl('Last page').should('have.class', 'cl-page-nav--disabled');
});

When('the user opens the services by host listing at ten per page', () => {
  openAtTenPerPage(byHostPage, '@byHostListing');
});

Then('the total counts the service once per host it is attached to', () => {
  // This listing counts over a derived table of (service, host) pairs. Counting
  // distinct service ids instead reported one row for a shared service and left
  // the extra pairs behind an unreachable page.
  cy.requestOnDatabase({
    database: 'centreon',
    query: `SELECT COUNT(*) AS pairs
            FROM host_service_relation hsr
            INNER JOIN service s ON s.service_id = hsr.service_service_id
            INNER JOIN host h ON h.host_id = hsr.host_host_id
            WHERE s.service_register = '1'
              AND s.service_activate = '1'
              AND h.host_register = '1'`
  }).then(([rows]) => {
    const expectedPairs = Number(rows[0].pairs);
    paginationWindow().then(([, , total]) => {
      expect(total, 'reported pair total').to.equal(expectedPairs);
    });
  });
});

Then('the last page of the services by host listing is not empty', () => {
  paginationWindow().then(([, , total]) => {
    const lastPageIndex = Math.ceil(total / pageSize) - 1;
    navControl('Last page').click();
    cy.wait('@byHostListing');
    dataRows().should('have.length.at.least', 1);
    paginationWindow().then(([first]) => {
      expect(first, 'first row index of the last page').to.equal(
        lastPageIndex * pageSize + 1
      );
    });
    assertWindowMatchesRows();
  });
});
