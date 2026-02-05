import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';

import { Contact, columns, durationMap, Token } from '../common';

beforeEach(() => {
  cy.startContainers();

  cy.intercept({
    method: 'GET',
    url: '/centreon/api/internal.php?object=centreon_topology&action=navigationList'
  }).as('getNavigationList');
  cy.intercept({
    method: 'GET',
    url: 'centreon/api/latest/administration/tokens?*desc*'
  }).as('getDescendingOrderedTokens');

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
  cy.get('.MuiAlert-message').then((snackbar) => {
    if (snackbar.text().includes('Login succeeded')) {
      cy.get('.MuiAlert-message').should('not.be.visible');
    }
  });
});

Given('Authentication tokens with predefined details are created', () => {
  cy.fixture('api-token/tokens.json').then((tokens: Record<string, Token>) => {
    Object.values(tokens).forEach((token) => {
      const today = new Date();
      const expirationDate = new Date(today);
      const duration = durationMap[token.duration];
      expirationDate.setDate(today.getDate() + duration);
      // Get the ISO string without milliseconds
      const expirationDateIsoString = `${expirationDate.toISOString().split('.')[0]}Z`;

      const payload = {
        expiration_date: expirationDateIsoString,
        name: token.name,
        type: token.type,
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

Given('I am on the Authentication tokens page', () => {
  cy.visitApiTokens();
});

When('I click on the {string} column header', (columnHeader: string) => {
  cy.contains(columnHeader).click();
  cy.wait('@getDescendingOrderedTokens');
});

Then(
  'the tokens are sorted by {string} in descending order',
  (orderBy: string) => {
    let errorMessage = 'Wrong order';

    cy.waitUntil(
      () => {
        let previousValue: string | null = null;
        let isOrdered = true;

        return cy
          .get('.MuiTableBody-root .MuiTableRow-root')
          .spread((...rows) => {
            rows.forEach((row) => {
              cy.wrap(row)
                .find('.MuiTableCell-body')
                .eq(columns.indexOf(orderBy))
                .invoke('text')
                .then((value) => {
                  const nextValue = value.trim();

                  errorMessage = `${nextValue} should be listed before ${previousValue}`;

                  if (previousValue !== null) {
                    if (orderBy.toLowerCase().includes('date')) {
                      isOrdered =
                        new Date(previousValue).getTime() >=
                        new Date(nextValue).getTime();
                    } else {
                      isOrdered =
                        [previousValue, nextValue].sort().reverse().pop() ===
                        nextValue;
                    }
                  }

                  previousValue = nextValue;
                });
            });

            return cy.wrap(isOrdered);
          });
      },
      {
        errorMsg: () => errorMessage
      }
    );
  }
);
