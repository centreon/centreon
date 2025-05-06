/* eslint-disable cypress/unsafe-to-chain-command */
import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';

before(() => {
  cy.startContainers();
});

beforeEach(() => {
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/internal.php?object=centreon_topology&action=navigationList'
  }).as('getNavigationList');
  cy.intercept({
    method: 'GET',
    url: '/centreon/include/common/userTimezone.php'
  }).as('getTimeZone');
});

after(() => {
  cy.stopContainers();
});

Given('a user is logged in a Centreon server', () => {
  cy.loginByTypeOfUser({
    jsonName: 'admin',
    loginViaApi: false
  });
});

When('the user visits the database informations page', () => {
  cy.navigateTo({
    page: 'Databases',
    rootItemNumber: 4,
    subMenu: 'Platform Status'
  });
  cy.wait('@getTimeZone');
});

Then('partitioning tables are displayed', () => {
  // Wait until the 'Partitioning Properties' area is visible in the DOM page
  cy.waitForElementInIframe('#main-content', 'td:contains("Partitioning Properties")');
  //Check that 'Partitioning tables' are visible
  ['data_bin', 'logs', 'log_archive_host', 'log_archive_service'].forEach(
    (table) => {
        cy.getIframeBody().contains('a', table).should('be.visible');

    }
  );
});

Then('more general information on the state of health of the databases is also present', () => {
  cy.getIframeBody().find('#database_informations').should('exist');
  // Check that a table named 'Database Engine' is displayed
  cy.getIframeBody().contains('Database Engine').should('be.visible');
  // Check that a table named 'Database Engine' is displayed
  cy.getIframeBody().contains('Centreon Databases Statistics').should('be.visible');
});