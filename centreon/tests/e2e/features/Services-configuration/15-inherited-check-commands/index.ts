import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { PAGES } from 'e2e/fixtures/shared/constants/pages';

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
    url: '/centreon/include/common/userTimezone.php'
  }).as('getTimeZone');
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/internal.php?object=centreon_topology&action=navigationList'
  }).as('getNavigationList');
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
  cy.visit(PAGES.configuration.hostGroupsLegacy);
  cy.wait('@getTimeZone');
  cy.getIframeBody().contains(services.serviceByHostGroup.host).should('be.visible');
});

When('the admin adds a new service linked to the configured host', () => {
  cy.visit(PAGES.configuration.servicesByHostLegacy);
  cy.wait('@getTimeZone');
  cy.getIframeBody().contains('Add').click();
  cy.waitForElementInIframe(
    '#main-content',
    'input[name="service_description"]'
  );

  cy.getIframeBody()
    .find('input[name="service_description"]')
    .clear()
    .type(services.serviceByHost.name);

  cy.getIframeBody().find('input[placeholder="Hosts"]').click();
  cy.getIframeBody()
    .find(`div[title="${services.serviceByHost.host}"]`)
    .click();
});

When('the admin selects the configured service template as parent', () => {
  cy.getIframeBody()
    .find('td.FormRowValue')
    .find('select#service_template_model_stm_id')
    .next()
    .click();
  cy.getIframeBody().contains(services.serviceByHost.template).click();
});

When('the admin saves the configuration', () => {
  cy.getIframeBody().find('input[value="Save"]').eq(1).click();
  cy.wait('@getTimeZone');
  cy.exportConfig();
});

Then('the service is successfully created', () => {
  cy.getIframeBody()
    .contains('a', services.serviceByHost.name)
    .should('be.visible');
});

When(
  'the admin adds a new service by host group linked to the configured host group',
  () => {
    cy.visit(PAGES.configuration.servicesByHostGroupsLegacy);
    cy.wait('@getTimeZone');
    cy.getIframeBody().contains('Add').click();
    cy.waitForElementInIframe(
      '#main-content',
      'input[name="service_description"]'
    );

    cy.getIframeBody()
      .find('input[name="service_description"]')
      .clear()
      .type(services.serviceByHostGroup.name);

    cy.getIframeBody()
      .find('input[placeholder="Linked with Host Groups"]')
      .click();
    cy.getIframeBody()
      .find(`div[title="${services.serviceByHostGroup.host}"]`)
      .click();
  }
);

Then('the service by host group is successfully created', () => {
  cy.getIframeBody()
    .contains('a', services.serviceByHostGroup.name)
    .should('be.visible');
});

after(() => {
  cy.stopContainers();
});
