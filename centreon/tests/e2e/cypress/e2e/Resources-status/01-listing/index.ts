import { Given, When, Then } from '@badeball/cypress-cucumber-preprocessor';

import {
  insertResourceFixtures,
  searchInput,
  stateFilterContainer,
  setUserFilter,
  deleteUserFilter,
  tearDownResource
} from '../common';
import { loginAsAdminViaApiV2, submitResultsViaClapi } from '../../../commons';

before(() => {
  insertResourceFixtures()
    .then(submitResultsViaClapi)
    .then(loginAsAdminViaApiV2)
    .then(() =>
      cy
        .fixture('resources/filters.json')
        .then((filters) => setUserFilter(filters))
    );
});

beforeEach(() => {
  cy.intercept({
    method: 'POST',
    url: '/centreon/api/latest/authentication/providers/configurations/local'
  }).as('postLocalAuthentication');
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/internal.php?object=centreon_topology&action=navigationList'
  }).as('getNavigationList');
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/latest/users/filters/events-view?page=1&limit=100'
  }).as('getFilters');
});

Then('the unhandled problems filter is selected', (): void => {
  cy.visit(`${Cypress.config().baseUrl}`);
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
  cy.loginByTypeOfUser({
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
  cy.loginByTypeOfUser({
    jsonName: 'admin'
  }).wait('@postLocalAuthentication');

  cy.wait('@getFilters');

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
