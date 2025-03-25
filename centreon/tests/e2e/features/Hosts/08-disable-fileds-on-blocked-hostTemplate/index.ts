/* eslint-disable cypress/unsafe-to-chain-command */
import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';

import hostTemplates from '../../../fixtures/host-templates/host-template.json';

const isInputFreezed = (name: string) => {
  // Check that input corresponding to the name is freezed
  cy.getIframeBody()
    .find(`input[name="${name}"]`)
    .should('have.attr', 'type', 'hidden');
};

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
  cy.navigateTo({
    page: 'Templates',
    rootItemNumber: 3,
    subMenu: 'Hosts'
  });
  cy.wait('@getTimeZone');
});

Then('the blocked host template is not visible on the page', () => {
  cy.getIframeBody()
    .contains(hostTemplates.defaultHostTemplate.name)
    .should('not.exist');
});

When('the user check the checkbox "Locked elements"', () => {
  // Check the checkbox "Locked elements"
  cy.getIframeBody().find('#displayLocked').click({ force: true });
});

When('the user clicks on the Search button', () => {
  // Click on the search button
  cy.getIframeBody().find('input[type="submit"].btc.bt_success').click();
  cy.wait('@getTimeZone');
});

Then('the blocked host template is visible on the page', () => {
  cy.getIframeBody()
    .contains(hostTemplates.defaultHostTemplate.name)
    .should('exist');
});

When('the user opens the form of the blocked host template', () => {
  // Click on the blocked host template to open details
  cy.getIframeBody().contains(hostTemplates.defaultHostTemplate.name).click();
  cy.wait('@getTimeZone');
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
  cy.getIframeBody().find('select[name="host_location"]').should('be.disabled');
  // Check that the "Check Command" field is freezed
  cy.getIframeBody()
    .find('select[name="command_command_id"]')
    .should('be.disabled');
  // Check that the "Check Period" field is freezed
  cy.getIframeBody()
    .find('select[name="timeperiod_tp_id"]')
    .should('be.disabled');
  // Click on the "Notification" tab
  cy.getIframeBody().contains('a', 'Notification').click();
  // Click outside the form
  cy.get('body').click(0, 0);
  // Check that the "Linked Contacts" field is freezed
  cy.getIframeBody().find('select[name="host_cs[]"]').should('be.disabled');
  // Check that the "Linked Contact Groups" field is freezed
  cy.getIframeBody().find('select[name="host_cgs[]"]').should('be.disabled');
  // Check that the "Notification Period" field is freezed
  cy.getIframeBody()
    .find('select[name="timeperiod_tp_id2"]')
    .should('be.disabled');
  [
    'host_notification_interval',
    'host_first_notification_delay',
    'host_recovery_notification_delay'
  ].forEach((name) => {
    isInputFreezed(name);
  });
  // Click on the "Relations" tab
  cy.getIframeBody().contains('a', 'Notification').click();
  // Click outside the form
  cy.get('body').click(0, 0);
  // Check that the "Linked Service Templates" field is freezed
  cy.getIframeBody().find('select[name="host_svTpls[]"]').should('be.disabled');
  // Check that the "Linked Service Templates" field is freezed
  cy.getIframeBody().find('select[name="host_hcs[]"]').should('be.disabled');
  // Click on the "Data Processing" tab
  cy.getIframeBody().contains('a', 'Data Processing').click();
  // Click outside the form
  cy.get('body').click(0, 0);
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
  cy.getIframeBody()
    .find('select[name="command_command_id2"]')
    .should('be.disabled');
  // Click on the "Host Extended Infos" tab
  cy.getIframeBody().contains('a', 'Host Extended Infos').click();
  // Click outside the form
  cy.get('body').click(0, 0);
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
