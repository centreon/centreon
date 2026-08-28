import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { INTERCEPTORS } from 'fixtures/shared/constants/interceptors';
import { PAGES } from 'fixtures/shared/constants/pages';

before(() => {
  cy.startContainers();
  cy.setUserTokenApiV1().executeCommandsViaClapi(
    'resources/clapi/config-ACL/services-readonly-user.json'
  );
});

beforeEach(() => {
  // loginByTypeOfUser({ loginViaApi: false }) waits on this alias internally.
  cy.intercept({
    method: 'GET',
    url: INTERCEPTORS.api.navigation_list
  }).as('getNavigationList');
  cy.intercept('GET', '**/ajaxServiceGroupListing.php*').as('getServiceGroups');
  cy.intercept('GET', '**/ajaxServiceByHostListing.php*').as('getServices');
});

after(() => {
  cy.stopContainers();
});

Given('a read-only user is logged in', () => {
  cy.loginByTypeOfUser({
    jsonName: 'user-readonly-for-services',
    loginViaApi: false
  });
});

When('the read-only user opens the service groups listing', () => {
  cy.visit(PAGES.configuration.servicesGroupsLegacy);
  cy.wait('@getServiceGroups');
});

When('the read-only user opens the services by host listing', () => {
  cy.visit(PAGES.configuration.servicesByHostLegacy);
  cy.wait('@getServices');
});

Then('the listing offers no add button and no bulk actions', () => {
  cy.getIframeBody().find('table.cl-listing-table').should('exist');
  cy.getIframeBody().find('.cl-btn-add').should('not.exist');
  cy.getIframeBody().find('.cl-more-actions-btn').should('not.exist');
});

Then('the row toggles are disabled and carry no duplication field', () => {
  // The framework disables the toggle by rewriting the generated HTML, and the
  // services-by-host page carries its own copy of that rewrite. A regex that
  // stops matching leaves an enabled toggle a read-only user can still click.
  // renderEmptyState() injects a real <tr><td colspan=99>, so counting rows
  // would pass on a listing showing nothing. Require a data row: one that
  // carries the row picker every rendered record has.
  cy.getIframeBody()
    .find('#clTableBody tr .cl-col-picker')
    .should('have.length.at.least', 1);
  cy.getIframeBody().find('#clTableBody .cl-empty-state').should('not.exist');
  cy.getIframeBody()
    .find('#clTableBody input[type="checkbox"][data-row-id]')
    .each(($toggle) => {
      cy.wrap($toggle).should('be.disabled');
    });
  cy.getIframeBody().find('#clTableBody .cl-dup-input').should('not.exist');
});

Then('every row holds as many cells as the header holds columns', () => {
  // The options cell is emitted whenever renderOptions is set, read-only or not,
  // so a header hidden behind mode_access shifts every column after the picker.
  cy.getIframeBody()
    .find('table.cl-listing-table thead tr th')
    .its('length')
    .then((headerCount) => {
      cy.getIframeBody()
        .find('#clTableBody tr')
        .first()
        .find('td')
        .should('have.length', headerCount);
    });
});
