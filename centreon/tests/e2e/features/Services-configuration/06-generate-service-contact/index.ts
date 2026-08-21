import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { INTERCEPTORS } from 'fixtures/shared/constants/interceptors';
import { PAGES } from 'fixtures/shared/constants/pages';

import data from '../../../fixtures/services/service.json';

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

Given('a user is logged in Centreon', () => {
  cy.loginByTypeOfUser({ jsonName: 'admin' });
});

Given('a service associated to a host is configured', () => {
  cy.addHostGroup({
    name: data.hostGroups.hostGroup1.name
  })
    .addHost({
      activeCheckEnabled: false,
      checkCommand: 'check_centreon_cpu',
      hostGroup: data.hostGroups.hostGroup1.name,
      name: data.hosts.host1.name,
      template: 'generic-host'
    })
    .addService({
      activeCheckEnabled: false,
      host: data.hosts.host1.name,
      maxCheckAttempts: 1,
      name: data.services.service1.name,
      template: 'Ping-WAN'
    });
});

Given('the user is in the "Notifications" tab', () => {
  cy.visitListingAndWait(PAGES.configuration.servicesByHostLegacy);
  cy.openListingRowForm(data.services.service1.name)
    .find('input[name="service_description"]', { timeout: 20_000 })
    .should('be.visible');
  // The tab strip became accordion sections; 'Notification' is the header the
  // form registers for this block.
  cy.getFormBody().contains('.cf-section-header', 'Notification').click();
});

When('the user checks case yes to enable Notifications', () => {
  cy.getListingSidePanelBody()
    .find('input[name*="service_notifications_enabled"][value="1"]')
    .parent()
    .click();
});

When('the case Inherit contacts is checked', () => {
  cy.getListingSidePanelBody()
    .find('input[name*="service_use_only_contacts_from_host"][value="1"]')
    .parent()
    .click();
});

Then('the field "Implied Contacts" is disabled', () => {
  // Assert on the real <select>: that is what the form disables, the select2
  // search input only mirrors it.
  cy.getFormBody().find('select#service_cs').should('be.disabled');
});

Then('the field "Implied Contact Groups" is disabled', () => {
  cy.getListingSidePanelBody().find('select#service_cgs').should('be.disabled');
});

afterEach(() => {
  cy.stopContainers();
});
