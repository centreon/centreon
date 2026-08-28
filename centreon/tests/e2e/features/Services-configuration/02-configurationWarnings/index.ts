import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { INTERCEPTORS } from 'fixtures/shared/constants/interceptors';
import { PAGES } from 'fixtures/shared/constants/pages';

import data from '../../../fixtures/notifications/data-for-notification.json';

beforeEach(() => {
  cy.startContainers();
  cy.intercept({
    method: 'GET',
    url: INTERCEPTORS.pages.time_zone
  }).as('getUserTimezone');
  cy.intercept({
    method: 'GET',
    url: INTERCEPTORS.api.navigation_list
  }).as('getNavigationList');
});

Given('An admin user is logged in Centreon', () => {
  cy.loginByTypeOfUser({ jsonName: 'admin' });
});

Given('a service with notifications enabled', () => {
  cy.addHostGroup({
    name: data.hostGroups.hostGroup1.name
  });

  cy.addHost({
    activeCheckEnabled: false,
    checkCommand: 'check_centreon_cpu',
    hostGroup: data.hostGroups.hostGroup1.name,
    name: data.hosts.host1.name,
    template: 'generic-host'
  });
  cy.visitListingAndWait(PAGES.configuration.servicesByHostLegacy);
  cy.clickListingAddButton();
  cy.getListingSidePanelBody()
    .find('input[name="service_description"]', { timeout: 20_000 })
    .should('be.visible')
    .type('service');
  cy.openFormSelect2('service_hPars');
  cy.getListingSidePanelBody().find('button.btc.bt_info').click();
  cy.getListingSidePanelBody().find('button.btc.bt_success').click();
  cy.getListingSidePanelBody()
    .find('span[aria-labelledby="select2-command_command_id-container"]')
    .click();
  cy.getListingSidePanelBody()
    .find('.select2-results__option', { timeout: 20_000 })
    .contains('check_centreon_ping')
    .click({ force: true });
  cy.getListingSidePanelBody()
    .find('input.btc.bt_success[name^="submit"]')
    .first()
    .click();
  cy.setUserTokenApiV1();
  cy.setServiceParameters({
    name: data.hosts.host1.name,
    paramName: 'notifications_enabled',
    paramValue: '1'
  });
});

Given('the service has no notification period', () => {
  cy.setServiceParameters({
    name: data.hosts.host1.name,
    paramName: 'notification_period',
    paramValue: 'none'
  });
});

When('the configuration is exported', () => {
  cy.visit(PAGES.configuration.pollersLegacy);
  cy.wait('@getUserTimezone');
  cy.waitForElementInIframe('#main-content', 'input[name="searchP"]');
  cy.getIframeBody().find('h4').contains('Poller').should('exist');
  cy.getIframeBody().find('#exportConfigurationLink').should('be.visible');
  cy.getIframeBody().find('#exportConfigurationLink').click();

  cy.url().should('include', 'poller=');
  cy.wait('@getUserTimezone');
  cy.waitForElementInIframe(
    '#main-content',
    'input.select2-search__field[placeholder="Pollers"]'
  );
  cy.getIframeBody()
    .find('input.select2-search__field[placeholder="Pollers"]')
    .click();
  cy.getIframeBody().contains('Central').click();

  cy.getIframeBody().find('input[name="move"]').parent().click();
  cy.getIframeBody().find('input[name="restart"]').parent().click();
  cy.getIframeBody().find('input[id="exportBtn"]').click();
});

Then('a warning message is printed', () => {
  cy.waitUntil(
    () => {
      cy.getIframeBody().find('div[id="console"]').should('be.visible');
      return cy
        .getIframeBody()
        .find('label[id="progressPct"]')
        .invoke('text')
        .then((text) => text === '100%');
    },
    { interval: 6000, timeout: 10000 }
  );
  cy.getIframeBody()
    .find('div#debug_1')
    .contains('Warning')
    .should('be.visible');
});

afterEach(() => {
  cy.stopContainers();
});
