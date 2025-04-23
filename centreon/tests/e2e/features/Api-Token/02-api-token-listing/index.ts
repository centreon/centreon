import { Given, When, Then } from '@badeball/cypress-cucumber-preprocessor';

import { Contact, durationMap } from '../common';

const tokenName = '';

// starting from the User_1
const userId = 20;

before(() => {
  cy.startContainers();

  cy.fixture('api-token/users.json').then((users: Record<string, Contact>) => {
    Object.values(users).forEach((user) => {
      cy.addContact(user);
    });
  });
});

beforeEach(() => {
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/internal.php?object=centreon_topology&action=navigationList'
  }).as('getNavigationList');
  cy.intercept({
    method: 'GET',
    url: 'centreon/api/latest/administration/tokens?*'
  }).as('getTokens');
});

after(() => {
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

// Given('API tokens with the following details are created', (dataTable: any) => {
//   dataTable.hashes().forEach((row) => {
//     const today = new Date();
//     const expirationDate = new Date(today);
//     const duration = durationMap[row.Duration];
//     expirationDate.setDate(today.getDate() + duration);
//     const expirationDateISOString = expirationDate.toISOString();
//
//     const payload = {
//       expiration_date: expirationDateISOString,
//       name: row.Name,
//       user_id: userId
//     };
//     cy.request({
//       method: 'POST',
//       url: '/centreon/api/latest/administration/tokens',
//       body: payload,
//       headers: {
//         'Content-Type': 'application/json'
//       }
//     }).then((response) => {
//       expect(response.status).to.eq(201);
//       userId++;
//     });
//   });
// });
//
// When('I navigate to API tokens page', () => {
//   cy.visitApiTokens();
// });
//
// Then(
//   'a list of API tokens is displayed with the following fields',
//   (dataTable: any) => {
//     dataTable.hashes().forEach((row) => {
//       tokenName = row.Name;
//       cy.get('.MuiTableBody-root .MuiTableRow-root')
//         .contains(row.Name)
//         .parent()
//         .parent()
//         .within(() => {
//           Object.values(row).forEach((cell, cellIndex) => {
//             // User and Creator
//             if (cellIndex >= 2) {
//               cy.get('.MuiTableCell-body')
//                 .eq(cellIndex + 2)
//                 .should('contain.text', cell);
//               return;
//             }
//
//             cy.get('.MuiTableCell-body')
//               .eq(cellIndex)
//               .should('contain.text', cell);
//           });
//         });
//     });
//   }
// );
//
// Then('the Creation Date field has the current day as value', () => {
//   const creationDate = new Date().toLocaleDateString('en-US', {
//     month: '2-digit',
//     day: '2-digit',
//     year: 'numeric'
//   });
//
//   cy.get('.MuiTableBody-root .MuiTableRow-root')
//     .contains(tokenName)
//     .parent()
//     .parent()
//     .within(() => {
//       cy.get('.MuiTableCell-body').eq(2).should('contain.text', creationDate);
//     });
// });
//
// Then(
//   'the Expiration Date field has the current day plus {string} as value',
//   (duration: string) => {
//     const today = new Date();
//     const expirationDate = new Date(today);
//     const durationToADD = durationMap[duration];
//     expirationDate.setDate(today.getDate() + durationToADD);
//     const parsedExpirationDate = expirationDate.toLocaleDateString('en-US', {
//       month: '2-digit',
//       day: '2-digit',
//       year: 'numeric'
//     });
//
//     cy.get('.MuiTableBody-root .MuiTableRow-root')
//       .contains(tokenName)
//       .parent()
//       .parent()
//       .within(() => {
//         cy.get('.MuiTableCell-body')
//           .eq(3)
//           .should('contain.text', parsedExpirationDate);
//       });
//   }
// );
