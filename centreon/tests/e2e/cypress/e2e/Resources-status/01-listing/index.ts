import { Given, When, Then } from '@badeball/cypress-cucumber-preprocessor';

import {
  insertResourceFixtures,
  searchInput,
  stateFilterContainer,
  setUserFilter,
  deleteUserFilter,
  tearDownResource
} from '../common';
import { submitResultsViaClapi } from '../../../commons';

before(() => {
  insertResourceFixtures().then(() =>
    cy
      .fixture('resources/filters.json')
      .then((filters) => setUserFilter(filters))
  ).then(submitResultsViaClapi);
});

beforeEach(() => {
  cy.intercept({
    method: 'POST',
    url: '/centreon/api/latest/authentication/providers/configurations/local'
  }).as('getLoginResponse');
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/internal.php?object=centreon_topology&action=navigationList'
  }).as('getNavigationList');
});

Then('the unhandled problems filter is selected', (): void => {
  cy.contains('Unhandled problems');
});

Then('only non-ok resources are displayed', () => {
  cy.contains('service_test_ack');
  cy.contains('service_test_ok').should('not.exist');
  cy.contains('Critical');
  cy.get('header').parent().children().eq(1).contains('Ok').should('not.exist');
  cy.get('header').parent().children().eq(1).contains('Up').should('not.exist');
});

When('I put in some criterias', () => {
  cy.visit(`${Cypress.config().baseUrl}`).loginByTypeOfUser({
    jsonName: 'admin',
    preserveToken: true
  });
  const searchValue = `type:service s.description:(ok|dt)$`;

  cy.get(searchInput).clear().type(searchValue).type('{enter}');
});

Then(
  'only the Resources matching the selected criterias are displayed in the result',
  () => {
    cy.contains('1-2 of 2');
    cy.contains('service_test_dt');
    cy.contains('service_test_ok');
  }
);

Given('a saved custom filter', () => {
  cy.visit(`${Cypress.config().baseUrl}`)
    .loginByTypeOfUser({
      jsonName: 'admin',
      preserveToken: true
    })
    .wait('@getLoginResponse');
  cy.get(stateFilterContainer).click();
  cy.contains('OK services').should('exist');
});

When('I select the custom filter', () => {
  cy.contains('OK services').click();
});

Then(
  'only Resources matching the selected filter are displayed in the result',
  () => {
    cy.contains('1-1 of 1');
    cy.contains('service_test_ok');
  }
);

after(() => {
  deleteUserFilter()
    .then(tearDownResource)
    .then(() => cy.reload());
});
