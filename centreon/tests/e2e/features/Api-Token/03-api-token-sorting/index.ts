import { Given, When, Then } from '@badeball/cypress-cucumber-preprocessor';

import { Contact, Token, columns, durationMap } from '../common';

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
  cy.visitApiTokens();
});

When('I click on the {string} column header', (columnHeader: string) => {
  cy.contains(columnHeader).click();
  cy.wait('@getTokens');
});

Then(
  'the tokens are sorted by {string} in descending order',
  (orderBy: string) => {
    let previousValue: string = null;
    const values: Array<string> = [];
    const parsedDates: Array<Date> = [];
    cy.get('.MuiTableBody-root .MuiTableRow-root').each((row) => {
      cy.wrap(row)
        .find('.MuiTableCell-body')
        .eq(columns.indexOf(orderBy))
        .invoke('text')
        .then((value) => {
          const nextValue = value.trim();

          if (previousValue !== null) {
            if (orderBy.toLowerCase().includes('date')) {
              expect(new Date(previousValue).getTime()).to.be.gte(
                new Date(nextValue).getTime()
              );
            } else {
              const lastOrderedValue = [previousValue, nextValue].sort().pop();
              expect(lastOrderedValue).to.be.eq(nextValue);
              values.push(value.trim());
            }
          }

          previousValue = nextValue;
        });
    });
    // For Date columns
    if (orderBy.toLowerCase().includes('date')) {
      const sortedParsedDates = [...parsedDates].sort(
        (a, b) => b.getTime() - a.getTime()
      );
      expect(parsedDates).to.deep.equal(sortedParsedDates);
    } else {
      const sortedValues = [...values].sort().reverse();
      expect(values).to.deep.equal(sortedValues);
    }
  }
);
