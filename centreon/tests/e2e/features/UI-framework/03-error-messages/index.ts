import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { INTERCEPTORS } from 'fixtures/shared/constants/interceptors';
import { PAGES } from 'fixtures/shared/constants/pages';

import { buildHostGroupsByNameQuery, type HostGroupRow } from '../common';

// Paths no route nor topology entry answers to, built on known pages
const unknownPage = PAGES.configuration.login.replace(
  /login$/,
  'rbt-ui-unknown-page'
);
const unknownLegacyPage = PAGES.configuration.hostsLegacy.replace(
  /p=\d+$/,
  'p=99999'
);
const unknownHostGroupId = 999999;
const listedHostGroup = 'rbt-ui-error-hg';

beforeEach(() => {
  cy.startContainers();

  cy.intercept({
    method: 'GET',
    url: INTERCEPTORS.api.navigation_list
  }).as('getNavigationList');
  cy.intercept({
    method: 'GET',
    url: `${INTERCEPTORS.api.hosts_configuration}/groups?*`
  }).as('getHostGroups');
  cy.intercept({
    method: 'GET',
    url: `${INTERCEPTORS.api.hosts_configuration}/groups/${unknownHostGroupId}`
  }).as('getUnknownHostGroup');
  cy.intercept({
    method: 'GET',
    url: `${INTERCEPTORS.api.monitor_resources}?*`
  }).as('getResources');
});

afterEach(() => {
  cy.stopContainers();
});

const mainMenu = (): Cypress.Chainable => cy.get('div[data-testid="sidebar"]');

Given('an admin user is logged in a Centreon server', () => {
  cy.loginByTypeOfUser({ jsonName: 'admin', loginViaApi: false });
});

When('the user opens an unknown page of the application', () => {
  cy.visit(unknownPage);
});

Then(
  'the page {string} with {string} is displayed with the main menu',
  (title: string, message: string) => {
    cy.contains(title).should('be.visible');
    cy.contains(message).should('be.visible');
    mainMenu().should('be.visible');
  }
);

When('the user opens an unknown legacy page', () => {
  cy.visit(unknownLegacyPage);
});

Then(
  'the message {string} is displayed inside the application',
  (message: string) => {
    cy.waitForElementInIframe('#main-content', 'div.msg');
    cy.getIframeBody().find('div.msg').should('contain', message);

    mainMenu().should('be.visible');
  }
);

When('the user opens the edit form of a host group that does not exist', () => {
  // The listing shows rows, not its welcome page, once one host group exists
  cy.addHostGroup({ name: listedHostGroup });
  cy.requestOnDatabase({
    database: 'centreon',
    query: buildHostGroupsByNameQuery([listedHostGroup])
  }).then(([rows]) => {
    expect(rows as Array<HostGroupRow>).to.have.length(1);
  });

  cy.visit(
    `${PAGES.configuration.hostGroups}?mode=edit&id=${unknownHostGroupId}`
  );
});

Then(
  'the error message {string} is displayed and the listing stays displayed',
  (message: string) => {
    cy.wait('@getUnknownHostGroup')
      .its('response.statusCode')
      .should('eq', 404);

    cy.contains(message).should('be.visible');

    cy.wait('@getHostGroups');
    cy.contains('Host groups').should('be.visible');
    cy.contains('p', listedHostGroup).should('be.visible');
    mainMenu().should('be.visible');
  }
);

When('the user clicks {string} in the main menu', (page: string) => {
  // Close the form opened by the URL, so the menu is not under its backdrop
  // (the focus stays trapped inside the open modal)
  cy.contains('Modify a host group').should('be.visible');
  cy.focused().type('{esc}');
  cy.contains('Modify a host group').should('not.exist');

  // Resources Status sits under the Monitoring root item of the menu
  mainMenu()
    .find('li')
    .contains('Monitoring')
    .trigger('mouseover', { force: true });
  cy.get('div[data-cy="collapse"]').contains(page).click();
});

Then('the Resources Status page is displayed', () => {
  cy.url().should('include', PAGES.monitoring.resourcesStatus);
  cy.wait('@getResources').its('response.statusCode').should('eq', 200);
  cy.get('input[placeholder="Search"]').should('be.visible');
});
