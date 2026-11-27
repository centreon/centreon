import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { INTERCEPTORS } from 'fixtures/shared/constants/interceptors';
import { PAGES } from 'fixtures/shared/constants/pages';

import {
  buildCountHostsQuery,
  buildHostAliasQuery,
  legacyErrorPatterns,
  markupElementOf,
  removeMarkup
} from '../common';

const hostName = 'rbt-websrv1';

let searchedText = '';

beforeEach(() => {
  cy.startContainers();

  searchedText = '';

  cy.intercept({
    method: 'GET',
    url: INTERCEPTORS.api.navigation_list
  }).as('getNavigationList');
  cy.intercept({
    method: 'GET',
    url: INTERCEPTORS.pages.time_zone
  }).as('getTimeZone');
});

afterEach(() => {
  cy.stopContainers();
});

const searchInput = (): Cypress.Chainable<JQuery<HTMLElement>> =>
  cy.getIframeBody().find('input[name="searchH"]');

const listingRows = (): Cypress.Chainable<JQuery<HTMLElement>> =>
  cy.getIframeBody().find('table.ListTable tr:not(.ListHeader)');

const submitSearch = (): void => {
  cy.getIframeBody().find('form[name="form"]').submit();
  cy.wait('@getTimeZone');
  cy.waitForElementInIframe('#main-content', 'table.ListTable');
};

// Name, alias and address of a row: the search applies to these three fields
const rowSearchedFields = (row: HTMLElement): string => {
  const cells = Cypress.$(row).find('td');

  return [1, 3, 4].map((index) => cells.eq(index).text().trim()).join('\n');
};

const checkListingIsFiltered = (): void => {
  const filter = removeMarkup(searchedText).toLowerCase();

  listingRows().each((row) => {
    expect(
      rowSearchedFields(row[0]).toLowerCase(),
      'row of the filtered listing'
    ).to.contain(filter);
  });
};

Given('an administrator is logged in', () => {
  cy.loginByTypeOfUser({ jsonName: 'admin', loginViaApi: true });
});

Given('a host with the alias {string} is configured', (alias: string) => {
  cy.executeActionViaClapi({
    bodyContent: {
      action: 'ADD',
      object: 'HOST',
      values: `${hostName};${alias};127.0.0.1;generic-host;central;`
    }
  });

  cy.requestOnDatabase({
    database: 'centreon',
    query: buildHostAliasQuery(hostName)
  }).then(([rows]) => {
    expect(rows, `host ${hostName}`).to.have.length(1);
    expect(rows[0].host_alias).to.eq(alias);
  });
});

Given('the administrator is on the hosts configuration listing', () => {
  cy.visit(PAGES.configuration.hostsLegacy);
  cy.wait('@getTimeZone');
  cy.waitForElementInIframe('#main-content', 'input[name="searchH"]');
  cy.getIframeBody().find('table.ListTable').should('exist');
});

// A regular expression: a {string} parameter would read the trailing
// backslash of "%_\" as an escaped quote
When(/^the administrator searches the listing for "(.*)"$/, (text: string) => {
  searchedText = text;

  searchInput()
    .clear()
    .type(text, { delay: 0, parseSpecialCharSequences: false });
  submitSearch();
});

Then('no error message is displayed', () => {
  cy.getIframeBody().find('table.ListTable').should('exist');
  cy.getIframeBody()
    .invoke('text')
    .then((text) => {
      legacyErrorPatterns.forEach((pattern) => {
        expect(text).to.not.match(pattern);
      });
    });
});

Then(
  /^the listing only shows the hosts whose name contains "(.*)"$/,
  (text: string) => {
    expect(text).to.eq(searchedText);

    checkListingIsFiltered();
  }
);

Then('the typed text is displayed as plain text', () => {
  searchInput().should('have.value', removeMarkup(searchedText));

  const element = markupElementOf(searchedText);

  if (element === null) {
    return;
  }

  // The listing is loaded: a markup search shown as markup would add the element
  cy.getIframeBody()
    .find(element.tag)
    .filter((_, node) => node.textContent?.trim() === element.content)
    .should('have.length', 0);
});

When(
  'the administrator reloads the listing with a descending sort order',
  () => {
    // Without the "search" parameter the listing restores the saved search
    cy.visit(`${PAGES.configuration.hostsLegacy}&order=sort_desc`);
    cy.wait('@getTimeZone');
    cy.waitForElementInIframe('#main-content', 'table.ListTable');
  }
);

Then('the search filter is still applied', () => {
  searchInput().should('have.value', removeMarkup(searchedText));
  checkListingIsFiltered();
});

When('the administrator clears the search', () => {
  searchedText = '';

  searchInput().clear();
  submitSearch();
});

Then('the full hosts listing is displayed', () => {
  searchInput().should('have.value', '');

  cy.requestOnDatabase({
    database: 'centreon',
    query: buildCountHostsQuery()
  }).then(([rows]) => {
    const total = Number(rows[0].total);

    cy.getIframeBody()
      .find('input#limit')
      .invoke('val')
      .then((limit) => {
        listingRows().should('have.length', Math.min(total, Number(limit)));
      });
  });
});
