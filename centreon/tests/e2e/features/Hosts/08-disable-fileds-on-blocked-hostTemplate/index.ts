import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { INTERCEPTORS } from 'fixtures/shared/constants/interceptors';

import hostTemplates from '../../../fixtures/host-templates/host-template.json';
import { listingSelectors } from '../common';

/**
 * A frozen QuickForm field is re-rendered as a hidden input. The form now opens
 * in the side panel, so every assertion runs against that nested document.
 */
const isInputFreezed = (name: string) => {
  cy.getSidePanelBody()
    .find(`input[name="${name}"]`)
    .should('have.attr', 'type', 'hidden');
};

/**
 * Sections are reachable through the tab anchors at the top of the form; the
 * anchor expands its section when it is collapsed.
 */
const openFormTab = (label: string) => {
  cy.getSidePanelBody().contains('a', label).click();
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
    url: INTERCEPTORS.ajax.host_template_listing
  }).as('getHostTemplateListing');
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

Given('a blocked host template', () => {
  cy.request({
    body: hostTemplates.defaultHostTemplate,
    headers: {
      'Content-Type': 'application/json'
    },
    method: 'POST',
    url: '/centreon/api/beta/configuration/hosts/templates'
  }).then((response) => {
    expect(response.status).to.eq(201);
  });
  cy.lockHostTemplateWithSql(hostTemplates.defaultHostTemplate.name);
});

When('the user goes to the host template listing page', () => {
  cy.openHostTemplatesListing();
});

Then('the blocked host template is not visible on the page', () => {
  cy.getIframeBody()
    .find(listingSelectors.tableBody)
    .should('not.contain', hostTemplates.defaultHostTemplate.name);
});

When('the user check the checkbox "Locked elements"', () => {
  // The Locked toggle lives in the advanced filters popover.
  cy.getIframeBody().find(listingSelectors.advancedToggle).click();
  cy.getIframeBody().find('#displayLocked').check({ force: true });
});

When('the user clicks on the Search button', () => {
  cy.getIframeBody().find(listingSelectors.advancedSearch).click();
  cy.getIframeBody()
    .find(`${listingSelectors.tableBody} tr td`)
    .should('not.contain', 'Loading');
});

Then('the blocked host template is visible on the page', () => {
  cy.getIframeBody()
    .find(listingSelectors.tableBody)
    .contains(hostTemplates.defaultHostTemplate.name)
    .should('exist');
});

When('the user opens the form of the blocked host template', () => {
  cy.openListingRowForm(hostTemplates.defaultHostTemplate.name);
});

Then('the fields are all frozen', () => {
  [
    'host_name',
    'host_alias',
    'host_snmp_version',
    'command_command_id_arg1',
    'host_max_check_attempts',
    'host_check_interval',
    'host_retry_check_interval'
  ].forEach((name) => {
    isInputFreezed(name);
  });
  // Check that the "Timezone" field is freezed
  cy.getSidePanelBody()
    .find('select[name="host_location"]')
    .should('be.disabled');
  // Check that the "Check Command" field is freezed
  cy.getSidePanelBody()
    .find('select[name="command_command_id"]')
    .should('be.disabled');
  // Check that the "Check Period" field is freezed
  cy.getSidePanelBody()
    .find('select[name="timeperiod_tp_id"]')
    .should('be.disabled');

  openFormTab('Notification');
  // Check that the "Linked Contacts" field is freezed
  cy.getSidePanelBody().find('select[name="host_cs[]"]').should('be.disabled');
  // Check that the "Linked Contact Groups" field is freezed
  cy.getSidePanelBody().find('select[name="host_cgs[]"]').should('be.disabled');
  // Check that the "Notification Period" field is freezed
  cy.getSidePanelBody()
    .find('select[name="timeperiod_tp_id2"]')
    .should('be.disabled');
  [
    'host_notification_interval',
    'host_first_notification_delay',
    'host_recovery_notification_delay'
  ].forEach((name) => {
    isInputFreezed(name);
  });

  openFormTab('Relations');
  // Check that the "Linked Service Templates" field is freezed
  cy.getSidePanelBody()
    .find('select[name="host_svTpls[]"]')
    .should('be.disabled');
  // Check that the "Linked Host Categories" field is freezed
  cy.getSidePanelBody().find('select[name="host_hcs[]"]').should('be.disabled');

  openFormTab('Data Processing');
  [
    'host_acknowledgement_timeout',
    'host_freshness_threshold',
    'host_low_flap_threshold',
    'host_high_flap_threshold',
    'command_command_id_arg2'
  ].forEach((name) => {
    isInputFreezed(name);
  });
  // Check that the "Event handler" field is freezed
  cy.getSidePanelBody()
    .find('select[name="command_command_id2"]')
    .should('be.disabled');

  openFormTab('Host Extended Infos');
  [
    'ehi_notes_url',
    'ehi_notes',
    'ehi_action_url',
    'ehi_icon_image',
    'ehi_icon_image_alt',
    'host_comment'
  ].forEach((name) => {
    isInputFreezed(name);
  });
});
