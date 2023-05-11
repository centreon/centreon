import { Given } from '@badeball/cypress-cucumber-preprocessor';

import { searchInput } from '../common';

beforeEach(() => {
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/internal.php?object=centreon_topology&action=navigationList'
  }).as('getNavigationList');
});

Given('the user have the necessary rights to page Resource Status', () => {
  cy.loginByTypeOfUser({
    jsonName: 'admin',
    loginViaApi: true
  });

  cy.get(searchInput).should('exist');
});

Given('the user have the necessary rights to set downtime', () => {
  cy.getByTestId({ testId: 'Multiple Set Downtime' }).should('be.visible');
});
