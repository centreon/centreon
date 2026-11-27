import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { INTERCEPTORS } from 'fixtures/shared/constants/interceptors';
import { PAGES } from 'fixtures/shared/constants/pages';

import {
  buildHostGroupsByNameQuery,
  buildNameRange,
  decodeListingUrl,
  type HostGroupRow
} from '../common';

interface ListingInterception {
  request: { url: string };
  response?: { body: { result: Array<{ name: string }> } };
}

const listingTimeout = 20_000;
const namePattern = /^rbt-ui-hg-\d{2}$/;

let hostGroupNames: Array<string> = [];
let lastListedNames: Array<string> = [];

beforeEach(() => {
  cy.startContainers();

  hostGroupNames = [];
  lastListedNames = [];

  cy.intercept({
    method: 'GET',
    url: INTERCEPTORS.api.navigation_list
  }).as('getNavigationList');
  cy.intercept({
    method: 'GET',
    url: `${INTERCEPTORS.api.hosts_configuration}/groups?*`
  }).as('getHostGroups');
  cy.intercept({
    method: 'POST',
    url: `${INTERCEPTORS.api.hosts_configuration}/groups/_disable`
  }).as('disableHostGroups');
});

afterEach(() => {
  cy.stopContainers();
});

// Consumes the listing requests until one matches, then keeps its names
const waitForListing = (
  matches: (url: string) => boolean,
  deadline = Date.now() + listingTimeout
): void => {
  cy.wait('@getHostGroups').then((interception) => {
    const { request, response } =
      interception as unknown as ListingInterception;

    if (matches(decodeListingUrl(request.url))) {
      lastListedNames = (response?.body.result ?? []).map(({ name }) => name);

      return;
    }

    if (Date.now() > deadline) {
      throw new Error('No host group listing request matched');
    }

    waitForListing(matches, deadline);
  });
};

// Names shown by the listing, in the order of its rows
const namesOf = ($elements: JQuery<HTMLElement>): Array<string> =>
  $elements
    .toArray()
    .filter((element) => element.children.length === 0)
    .map((element) => element.textContent?.trim() ?? '')
    .filter((text) => namePattern.test(text));

const hostGroupsByName = (
  names: Array<string>
): Cypress.Chainable<Array<HostGroupRow>> =>
  cy
    .requestOnDatabase({
      database: 'centreon',
      query: buildHostGroupsByNameQuery(names)
    })
    .then(([rows]) => cy.wrap(rows as Array<HostGroupRow>, { log: false }));

const expectPageToList = (expected: Array<string>): void => {
  cy.then(() => {
    expect(lastListedNames, 'names returned by the API').to.deep.eq(expected);
  });

  cy.get('p, span').should(($elements) => {
    expect(namesOf($elements), 'names displayed in order').to.deep.eq(expected);
  });
};

Given('an admin user is logged in a Centreon server', () => {
  cy.loginByTypeOfUser({ jsonName: 'admin', loginViaApi: false });
});

Given(
  '{int} host groups named from {string} to {string} exist',
  (count: number, first: string, last: string) => {
    hostGroupNames = buildNameRange(first, last);
    expect(hostGroupNames).to.have.length(count);

    hostGroupNames.forEach((name) => {
      // A distinct alias, so the alias column never repeats a name
      cy.addHostGroup({ alias: `${name} alias`.replace(/-/g, ' '), name });
    });
  }
);

When(
  'the user searches {string} in the host groups listing',
  (search: string) => {
    cy.visit(PAGES.configuration.hostGroups);
    cy.wait('@getHostGroups');

    cy.get('input[placeholder="Search"]').type(search);
    waitForListing((url) => url.includes(search));
  }
);

When(
  'the user shows {int} rows per page sorted by {string} in descending order',
  (limit: number, column: string) => {
    cy.get('[id="Rows per page"]').click();
    cy.get(`li[data-value="${limit}"]`).click();
    waitForListing((url) => url.includes(`limit=${limit}`));

    cy.getByLabel({ label: `Column ${column}` }).click();
    waitForListing(
      (url) =>
        url.includes(`limit=${limit}`) &&
        url.includes(`"${column.toLowerCase()}":"desc"`)
    );
  }
);

Then(
  'the first page lists {string} down to {string}',
  (first: string, last: string) => {
    expectPageToList(buildNameRange(last, first).reverse());
  }
);

When('the user goes to the next page', () => {
  cy.getByLabel({ label: 'Next page' }).click();
  waitForListing((url) => url.includes('page=2'));
});

Then(
  'the page lists only {string} and {string}',
  (first: string, second: string) => {
    expectPageToList([first, second]);

    // The rest of the range is on the first page only
    hostGroupNames
      .filter((name) => ![first, second].includes(name))
      .forEach((name) => {
        cy.contains(name).should('not.exist');
      });
  }
);

When(
  'the user hides the {string} column and reloads the page',
  (column: string) => {
    cy.getByLabel({ label: `Column ${column}` }).should('be.visible');

    cy.getByLabel({ label: 'Add columns' }).click();
    cy.get(`li[role="menuitem"][value="${column}"]`).click();
    cy.getByLabel({ label: 'Add columns' }).click();
    cy.getByLabel({ label: `Column ${column}` }).should('not.exist');

    cy.reload();
    cy.wait('@getHostGroups');
  }
);

Then('the {string} column is still hidden', (column: string) => {
  cy.getByLabel({ label: 'Column Name' }).should('be.visible');
  cy.contains(hostGroupNames[0]).should('be.visible');

  cy.getByLabel({ label: `Column ${column}` }).should('not.exist');
  cy.contains(`${hostGroupNames[0]} alias`.replace(/-/g, ' ')).should(
    'not.exist'
  );
});

When(
  'the user selects {string} and {string} and disables them from {string}',
  (first: string, second: string, menu: string) => {
    hostGroupsByName([first, second]).then((rows) => {
      expect(rows).to.have.length(2);

      rows.forEach(({ hg_id }) => {
        cy.getByLabel({ label: `Select row ${hg_id}`, tag: 'input' }).click();
      });

      cy.getByLabel({ label: menu, tag: 'button' }).first().click();
      cy.getByTestId({ testId: 'listing-massive-disable' }).first().click();

      cy.wait('@disableHostGroups').then(({ request }) => {
        expect(request.body.ids).to.have.members(
          rows.map(({ hg_id }) => Number(hg_id))
        );
      });
    });
  }
);

Then(
  'the message {string} is displayed and both rows are disabled',
  (message: string) => {
    cy.contains(message).should('be.visible');

    const disabledNames = hostGroupNames.slice(-2);

    hostGroupsByName(hostGroupNames).then((rows) => {
      rows.forEach(({ hg_activate, hg_id, hg_name }) => {
        const isDisabled = disabledNames.includes(hg_name);

        expect(hg_activate, `${hg_name} activation`).to.eq(
          isDisabled ? '0' : '1'
        );

        cy.getByTestId({ testId: `listing-action-enable-disable_${hg_id}` })
          .find('input[type="checkbox"]')
          .should(isDisabled ? 'not.be.checked' : 'be.checked');
      });
    });
  }
);
