import { Given, When, Then } from '@badeball/cypress-cucumber-preprocessor';

import { Contact, Token, columnsFromLabels, durationMap } from '../common';

interface filterOptions {
  creationDate:
    | 'Last 7 days'
    | 'Last 30 days'
    | 'Last 60 days'
    | 'Last 90 days'
    | 'Last 1 year';
  creator: string;
  expirationDate:
    | 'In 7 days'
    | 'In 30 days'
    | 'In 60 days'
    | 'In 90 days'
    | 'In 1 year';
  name: string;
  status: 'Active' | 'Disabled';
  user: string;
}

const tokensToSearch: filterOptions = {
  creationDate: 'Last 7 days',
  creator: 'admin admin',
  expirationDate: 'In 7 days',
  name: 'Token_2',
  status: 'Active',
  user: 'User_1'
};

const getDateBasedOnFilter = (filterValue: string): Date => {
  const today = new Date();
  const date = new Date(today);
  const [prefix, duration, dayOrYear] = filterValue.split(' ');

  const daysToAddOrSubtract = durationMap[`${duration} ${dayOrYear}`];

  prefix === 'In'
    ? date.setDate(today.getDate() + daysToAddOrSubtract)
    : date.setDate(today.getDate() - daysToAddOrSubtract);

  return date;
};

beforeEach(() => {
  cy.startContainers();

  cy.intercept({
    method: 'GET',
    url: '/centreon/api/internal.php?object=centreon_topology&action=navigationList'
  }).as('getNavigationList');
  cy.intercept({
    method: 'GET',
    url: 'centreon/api/latest/administration/tokens?*'
  }).as('getTokens');
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/latest/configuration/users?page=1*'
  }).as('getUsers');

  cy.fixture('api-token/users.json').then((users: Record<string, Contact>) => {
    Object.values(users).forEach((user) => {
      cy.addContact(user);
    });
  });
});

afterEach(() => {
  cy.stopContainers();
});

Given('I am logged in as an administrator', () => {
  cy.loginByTypeOfUser({ jsonName: 'admin' });
  cy.get('.MuiAlert-message').then(($snackbar) => {
    if ($snackbar.text().includes('Login succeeded')) {
      cy.get('.MuiAlert-message').should('not.be.visible');
    }
  });
});

Given('API tokens with predefined details are created', () => {
  cy.fixture('api-token/tokens.json').then((tokens: Record<string, Token>) => {
    Object.values(tokens).forEach((token) => {
      const today = new Date();
      const expirationDate = new Date(today);
      const duration = durationMap[token.duration];
      expirationDate.setDate(today.getDate() + duration);
      const expirationDateISOString = expirationDate.toISOString();

      const payload = {
        expiration_date: expirationDateISOString,
        name: token.name,
        user_id: token.userId
      };
      cy.request({
        body: payload,
        headers: {
          'Content-Type': 'application/json'
        },
        method: 'POST',
        url: '/centreon/api/latest/administration/tokens'
      }).then((response) => {
        expect(response.status).to.eq(201);
      });
    });
  });
});

Given('I am on the API tokens page', () => {
  cy.visit('/centreon/administration/api-token');
  cy.wait('@getTokens');

  cy.getByLabel({ label: 'Refresh', tag: 'button' }).click();
  cy.wait('@getTokens');
});

When('I filter tokens by {string} and click on Search', (filterBy: string) => {
  if (filterBy === 'Name') {
    cy.getByTestId({ tag: 'input', testId: 'inputSearch' }).type(
      tokensToSearch.name
    );
    cy.getByTestId({ tag: 'input', testId: 'inputSearch' }).trigger('keydown', {
      keyCode: 13,
      which: 13
    });

    return;
  }

  cy.getByLabel({ label: 'Filter options', tag: 'button' }).click();

  if (filterBy === 'Status') {
    tokensToSearch.status === 'Active'
      ? cy.contains('Active tokens').click()
      : cy.contains('Disabled tokens').click();
  } else {
    cy.getByLabel({ label: filterBy, tag: 'input' }).click();
    switch (filterBy) {
      case 'Creator':
        cy.wait('@getTokens');
        cy.contains('li', tokensToSearch.creator).click();
        cy.getByLabel({ label: filterBy, tag: 'input' }).click();
        break;
      case 'User':
        cy.wait('@getUsers');
        cy.contains('li', tokensToSearch.user).click();
        cy.getByLabel({ label: filterBy, tag: 'input' }).click();
        break;
      case 'Creation date':
        cy.contains('li', tokensToSearch.creationDate).click();
        break;
      case 'Expiration date':
        cy.contains('li', tokensToSearch.expirationDate).click();
        break;
      default:
        throw new Error(`${filterBy} filter is not managed`);
    }
  }

  cy.getByTestId({ tag: 'button', testId: 'Search' }).click();
  cy.getByLabel({ label: 'Filter options', tag: 'button' }).click();
});

Then(
  'I should see all tokens with a {string} according to the filter',
  (filterBy: string) => {
    cy.wait('@getTokens');

    cy.waitUntil(
      () => {
        const allPromisesResolved: Array<boolean> = [];

        return cy
          .get('.MuiTableBody-root .MuiTableRow-root')
          .each(($row) => {
            cy.wrap($row)
              .find('.MuiTableCell-body')
              .eq(columnsFromLabels.indexOf(filterBy))
              .invoke('text')
              .then((value) => {
                if (filterBy.includes('date')) {
                  const [prefix] = filterBy.split(' ');
                  allPromisesResolved.push(
                    prefix === 'Creation'
                      ? new Date(value.trim()) >=
                          getDateBasedOnFilter(tokensToSearch.creationDate)
                      : new Date(value.trim()) <=
                          getDateBasedOnFilter(tokensToSearch.creationDate)
                  );
                } else {
                  switch (filterBy) {
                    case 'Status':
                      allPromisesResolved.push(value === tokensToSearch.status);
                      break;
                    case 'Name':
                      allPromisesResolved.push(
                        value.includes(tokensToSearch.name)
                      );
                      break;
                    case 'User':
                      allPromisesResolved.push(value === tokensToSearch.user);
                      break;
                    case 'Creator':
                      allPromisesResolved.push(
                        value === tokensToSearch.creator
                      );
                      break;
                    default:
                      throw new Error(`${filterBy} filter is not managed`);
                  }
                }
              });
          })
          .then(() => {
            return cy.wrap(
              allPromisesResolved.every((result) => result === true)
            );
          });
      },
      {
        errorMsg: 'Expected filter failed ',
        interval: 2000,
        timeout: 10000
      }
    );
  }
);
