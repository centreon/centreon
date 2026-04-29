import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { PAGES } from 'e2e/fixtures/shared/constants/pages';

before(() => {
  cy.startContainers();
  cy.setUserTokenApiV1().executeCommandsViaClapi(
    'resources/clapi/pollers/poller-1.json'
  );
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
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/latest/configuration/monitoring-servers/generate-and-reload'
  }).as('generateAndReloadPollers');
});

Given('an admin user is logged in a Centreon server', () => {
  cy.loginByTypeOfUser({
    jsonName: 'admin',
    loginViaApi: false
  });
});

Given('a remote poller is configured', () => {
  cy.visit(PAGES.configuration.pollersLegacy);
  cy.wait('@getNavigationList');
  cy.wait('@getTimeZone');
  cy.getIframeBody().contains('td', 'Poller-1');
});

When('the user duplicates the configured poller', () => {
  cy.getIframeBody()
    .contains('tr', 'Poller-1')
    .find('div.md-checkbox.md-checkbox-inline')
    .click();
  cy.getIframeBody()
    .find('button[name="duplicate_action"]')
    .invoke('attr', 'onclick', "javascript: { setO('m'); submit(); }");
  cy.getIframeBody().find('button[name="duplicate_action"]').click();
  cy.wait('@getTimeZone');
});

Then('a new disabled poller is created with identical properties', () => {
  cy.getIframeBody()
    .find('table tbody tr.row_disabled')
    .within(() => {
      cy.contains('td', 'Poller-1_1').should('exist');
      cy.contains('td', '10.30.2.55').should('exist');
    });
});

When('the user exports the configuration', () => {
  cy.exportConfig();
});

Then('a success message is displayed', () => {
  cy.wait('@generateAndReloadPollers').then(() => {
    cy.contains('Configuration exported and reloaded').should('have.length', 1);
  });
});

after(() => {
  cy.stopContainers();
});
