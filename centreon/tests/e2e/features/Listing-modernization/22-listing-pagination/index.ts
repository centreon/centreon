import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { INTERCEPTORS } from 'fixtures/shared/constants/interceptors';
import { PAGES } from 'fixtures/shared/constants/pages';

const sgPage = PAGES.configuration.servicesGroupsLegacy;
const seededPrefix = 'pg_group_';
const seededCount = 12;
const pageSize = 10;

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

const displayedNames = (): Cypress.Chainable<Array<string>> =>
  cy
    .getIframeBody()
    .find('#clTableBody tr')
    .then(($rows) => Cypress._.map($rows, (row) => row.innerText));

const goToFirstPageOfTen = (): void => {
  cy.visitListingAndWait(sgPage);
  cy.getIframeBody()
    .find('#clPaginationTop select.cl-limit-select')
    .select(String(pageSize));
  cy.wait('@listing');
};

beforeEach(() => {
  cy.startContainers();
  // CLAPI needs the v1 token; without it cy.addServiceGroup answers 403 and the
  // Background seeds nothing. startContainers alone does not set it.
  cy.setUserTokenApiV1();
  // loginByTypeOfUser waits on this alias internally, whatever loginViaApi says.
  cy.intercept({
    method: 'GET',
    url: INTERCEPTORS.api.navigation_list
  }).as('getNavigationList');
  // waitForListingRefresh() only asserts that 'Loading' is gone, which is already
  // true between two fetches. Every navigation below waits on the request itself.
  cy.intercept('GET', '**/ajaxServiceGroupListing.php*').as('listing');
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

When('the user navigates to the service groups listing', () => {
  cy.visitListingAndWait(sgPage);
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
  cy.getIframeBody().find('#clTableBody tr').should('have.length', pageSize);
});

Then(
  'the pagination total matches the number of service groups in the database',
  () => {
    cy.requestOnDatabase({
      database: 'centreon',
      query: 'SELECT COUNT(*) AS total FROM servicegroup'
    }).then(([rows]) => {
      const expected = Number(rows[0].total);
      paginationWindow().then(([first, last, total]) => {
        expect(total, 'reported total').to.equal(expected);
        expect(first, 'first row index').to.equal(1);
        expect(last, 'last row index').to.equal(pageSize);
      });
    });
  }
);

Given('the user is on the first page of ten service groups', () => {
  goToFirstPageOfTen();
  displayedNames().as('firstPageRows');
});

When('the user goes to the next page', () => {
  navControl('Next page').click();
  cy.wait('@listing');
});

Then('the pagination window moves to the second page', () => {
  paginationWindow().then(([first, last, total]) => {
    expect(first, 'first row index').to.equal(pageSize + 1);
    expect(last, 'last row index').to.be.at.most(total);
    expect(last, 'last row index').to.be.greaterThan(pageSize);
  });
});

Then('the rows differ from the first page', () => {
  cy.get('@firstPageRows').then((firstPage) => {
    displayedNames().then((secondPage) => {
      expect(secondPage).not.to.deep.equal(firstPage);
    });
  });
});

When('the user goes to the last page', () => {
  navControl('Last page').click();
  cy.wait('@listing');
});

Then('the last page holds at least one row', () => {
  // The defect this guards against reports a total larger than the rows the
  // listing can actually serve, which leaves the last page blank.
  // A row picker means a real record: renderEmptyState() injects a <tr> too.
  cy.getIframeBody()
    .find('#clTableBody tr .cl-col-picker')
    .should('have.length.at.least', 1);
  paginationWindow().then(([first, last, total]) => {
    expect(last, 'last row index').to.equal(total);
    expect(first, 'first row index').to.be.at.most(total);
  });
});

Then('the next and last controls are disabled', () => {
  navControl('Next page').should('have.class', 'cl-page-nav--disabled');
  navControl('Last page').should('have.class', 'cl-page-nav--disabled');
});
