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
  // The tab strip became scroll anchors over one long form: target the anchor by
  // its section id, the labels are translated (and 'Extended Info' is now 'Misc').
  cy.getFormBody().find('.cf-tab-nav a[href="#cf-sec-notif"]').click();
});

When('the user checks case yes to enable Notifications', () => {
  cy.selectFormSegment('service_notifications_enabled', '1');
});

When('the case Inherit contacts is checked', () => {
  cy.selectFormSegment('service_use_only_contacts_from_host', '1');
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
