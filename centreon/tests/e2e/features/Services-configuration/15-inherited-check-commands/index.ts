import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { INTERCEPTORS } from 'fixtures/shared/constants/interceptors';
import { PAGES } from 'fixtures/shared/constants/pages';

const services = {
  serviceByHost: {
    host: 'Centreon-Server',
    name: 'Service-A',
    template: 'Ping-LAN'
  },
  serviceByHostGroup: {
    host: 'Firewall',
    name: 'ServiceHG-A',
    template: 'Ping-LAN'
  }
};

before(() => {
  cy.startContainers();
});

beforeEach(() => {
  cy.intercept({
    method: 'GET',
    url: INTERCEPTORS.api.navigation_list
  }).as('getNavigationList');
  cy.intercept({
    method: 'GET',
    url: INTERCEPTORS.pages.time_zone
  }).as('getTimeZone');
  cy.intercept({
    method: 'GET',
    url: `${INTERCEPTORS.api.hosts_configuration}/groups?page=1&limit=*`
  }).as('getGroups');
});

Given('an admin user is logged in Centreon', () => {
  cy.loginByTypeOfUser({ jsonName: 'admin' });
});

Given('a service template with check commands is configured', () => {
  cy.visit(PAGES.configuration.servicesTemplatesLegacy);
  cy.wait('@getTimeZone');
  cy.getIframeBody()
    .contains(services.serviceByHost.template)
    .should('be.visible');
});

Given('a host is configured', () => {
  cy.visit(PAGES.configuration.hostsLegacy);
  cy.wait('@getTimeZone');
  cy.getIframeBody().contains(services.serviceByHost.host).should('be.visible');
});

Given('a host group is configured', () => {
  cy.visit(PAGES.configuration.hostGroups);
  cy.wait('@getGroups');
  cy.contains(services.serviceByHostGroup.host).should('be.visible');
});

When('the admin adds a new service linked to the configured host', () => {
  cy.visitListingAndWait(PAGES.configuration.servicesByHostLegacy);
  cy.clickListingAddButton();
  cy.getListingSidePanelBody()
    .find('input[name="service_description"]', { timeout: 20_000 })
    .should('be.visible')
    .clear()
    .type(services.serviceByHost.name);

  cy.selectFormOption('service_hPars', services.serviceByHost.host);
});

When('the admin selects the configured service template as parent', () => {
  cy.getListingSidePanelBody()
    .find('select#service_template_model_stm_id')
    .next()
    .find('.select2-selection')
    .click({ force: true });
  cy.getListingSidePanelBody()
    .find('.select2-results__option', { timeout: 20_000 })
    .contains(services.serviceByHost.template)
    .click({ force: true });
});

When('the admin saves the configuration', () => {
  cy.getListingSidePanelBody()
    .find('input.btc.bt_success[name^="submit"]')
    .first()
    .click();
  cy.wait('@getTimeZone');
  cy.exportConfig();
});

Then('the service is successfully created', () => {
  cy.waitForElementInIframe('#main-content', 'table.cl-listing-table');
  cy.waitForListingRefresh();
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('a', services.serviceByHost.name)
    .should('be.visible');
});

When(
  'the admin adds a new service by host group linked to the configured host group',
  () => {
    cy.visitListingAndWait(PAGES.configuration.servicesByHostGroupsLegacy);
    cy.clickListingAddButton();
    cy.getListingSidePanelBody()
      .find('input[name="service_description"]', { timeout: 20_000 })
      .should('be.visible')
      .clear()
      .type(services.serviceByHostGroup.name);

    cy.selectFormOption('service_hgPars', services.serviceByHostGroup.host);
  }
);

Then('the service by host group is successfully created', () => {
  cy.waitForElementInIframe('#main-content', 'table.cl-listing-table');
  cy.waitForListingRefresh();
  cy.getIframeBody()
    .find('#clTableBody')
    .contains('a', services.serviceByHostGroup.name)
    .should('be.visible');
});

after(() => {
  cy.stopContainers();
});
