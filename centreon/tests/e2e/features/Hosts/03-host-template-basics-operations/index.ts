/* eslint-disable cypress/unsafe-to-chain-command */
import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';

import hostTemplates from '../../../fixtures/host-templates/host-template.json';

const checkFirstHostTemplateFromListing = () => {
  cy.navigateTo({
    page: 'Templates',
    rootItemNumber: 3,
    subMenu: 'Hosts'
  });
  cy.wait('@getTimeZone');
  cy.getIframeBody().find('div.md-checkbox.md-checkbox-inline').eq(2).click();
  cy.getIframeBody()
    .find('select')
    .eq(0)
    .invoke(
      'attr',
      'onchange',
      "javascript: { setO(this.form.elements['o1'].value); submit(); }"
    );
};

beforeEach(() => {
  cy.startContainers();
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/internal.php?object=centreon_topology&action=navigationList'
  }).as('getNavigationList');
  cy.intercept({
    method: 'GET',
    url: '/centreon/include/common/userTimezone.php'
  }).as('getTimeZone');
});

afterEach(() => {
  cy.stopContainers();
});

Given('an admin user is logged in a Centreon server', () => {
  cy.loginByTypeOfUser({
    jsonName: 'admin',
    loginViaApi: false
  });
});

When('a host template is configured', () => {
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
});

When('the user changes the properties of the configured host template', () => {
  cy.navigateTo({
    page: 'Templates',
    rootItemNumber: 3,
    subMenu: 'Hosts'
  });
  cy.wait('@getTimeZone');
  cy.getIframeBody().contains(hostTemplates.defaultHostTemplate.name).click();
  cy.waitForElementInIframe('#main-content', 'input[name="host_name"]');

  cy.getIframeBody()
    .find('input[name="host_name"]')
    .clear()
    .type(hostTemplates.hostTemplate.name);
  cy.getIframeBody()
    .find('input[name="host_alias"]')
    .clear()
    .type(hostTemplates.hostTemplate.alias);
  cy.getIframeBody()
    .find('input[name="host_snmp_community"]')
    .clear()
    .type(hostTemplates.hostTemplate.snmp_community);
  cy.getIframeBody()
    .find('select[name="host_snmp_version"]')
    .select(hostTemplates.hostTemplate.snmp_version);

  cy.getIframeBody().find('span[id="select2-host_location-container"]').click();

  cy.getIframeBody().find('div[title="Africa/Algiers"]').click();

  cy.getIframeBody()
    .find('span[id="select2-command_command_id-container"]')
    .click();
  cy.getIframeBody().find('div[title="check_http"]').click();

  cy.getIframeBody()
    .find('span[id="select2-timeperiod_tp_id-container"]')
    .click();
  cy.getIframeBody().find('div[title="none"]').click();

  cy.getIframeBody()
    .find('input[name="host_max_check_attempts"]')
    .clear()
    .type(hostTemplates.hostTemplate.max_check_attempts.toString());

  cy.getIframeBody()
    .find('input[name="host_check_interval"]')
    .clear()
    .type(hostTemplates.hostTemplate.normal_check_interval.toString());

  cy.getIframeBody()
    .find('input[name="host_retry_check_interval"]')
    .clear()
    .type(hostTemplates.hostTemplate.retry_check_interval.toString());

  cy.getIframeBody().contains('label', 'Yes').eq(0).click();

  cy.getIframeBody().find('input.btc.bt_success[name^="submit"]').eq(1).click();
  cy.wait('@getTimeZone');
  cy.exportConfig();
});

Then('the properties are updated', () => {
  cy.getIframeBody().contains(hostTemplates.hostTemplate.name).should('exist');
  cy.getIframeBody().contains(hostTemplates.hostTemplate.name).click();
  cy.waitForElementInIframe('#main-content', 'input[name="host_name"]');

  cy.getIframeBody()
    .find('input[name="host_name"]')
    .should('have.value', hostTemplates.hostTemplate.name);

  cy.getIframeBody()
    .find('input[name="host_alias"]')
    .should('have.value', hostTemplates.hostTemplate.alias);

  cy.getIframeBody()
    .find('select[name="host_snmp_version"]')
    .should('have.value', '3');

  cy.getIframeBody()
    .find('span[id="select2-host_location-container"]')
    .should('have.attr', 'title', 'Africa/Algiers');

  cy.getIframeBody()
    .find('span[id="select2-command_command_id-container"]')
    .should('have.attr', 'title', 'check_http');

  cy.getIframeBody()
    .find('span[id="select2-timeperiod_tp_id-container"]')
    .should('have.attr', 'title', 'none');

  cy.getIframeBody()
    .find('input[name="host_max_check_attempts"]')
    .should('have.value', hostTemplates.hostTemplate.max_check_attempts);

  cy.getIframeBody()
    .find('input[name="host_check_interval"]')
    .should('have.value', hostTemplates.hostTemplate.normal_check_interval);

  cy.getIframeBody()
    .find('input[name="host_retry_check_interval"]')
    .should('have.value', hostTemplates.hostTemplate.retry_check_interval);

  cy.checkLegacyRadioButton('Yes');
});

When('the user duplicates the configured host template', () => {
  checkFirstHostTemplateFromListing();
  cy.getIframeBody().find('select').eq(0).select('Duplicate');
  cy.wait('@getTimeZone');
  cy.exportConfig();
});

Then('a new host template is created with identical properties', () => {
  cy.getIframeBody()
    .contains(`${hostTemplates.defaultHostTemplate.name}_1`)
    .should('exist');
  cy.getIframeBody()
    .contains(`${hostTemplates.defaultHostTemplate.name}_1`)
    .click();

  cy.waitForElementInIframe('#main-content', 'input[name="host_name"]');

  cy.getIframeBody()
    .find('input[name="host_name"]')
    .should('have.value', `${hostTemplates.defaultHostTemplate.name}_1`);

  cy.getIframeBody()
    .find('input[name="host_alias"]')
    .should('have.value', hostTemplates.defaultHostTemplate.alias);

  cy.getIframeBody()
    .find('select[name="host_snmp_version"]')
    .should('have.value', hostTemplates.defaultHostTemplate.snmp_version);

  cy.getIframeBody()
    .find('span[id="select2-host_location-container"]')
    .should('have.attr', 'title', 'Africa/Abidjan');

  cy.getIframeBody()
    .find('span[id="select2-command_command_id-container"]')
    .should('have.attr', 'title', 'check_host_alive');

  cy.getIframeBody()
    .find('span[id="select2-timeperiod_tp_id-container"]')
    .should('have.attr', 'title', '24x7');

  cy.getIframeBody()
    .find('input[name="host_max_check_attempts"]')
    .should('have.value', hostTemplates.defaultHostTemplate.max_check_attempts);

  cy.getIframeBody()
    .find('input[name="host_check_interval"]')
    .should(
      'have.value',
      hostTemplates.defaultHostTemplate.normal_check_interval
    );

  cy.getIframeBody()
    .find('input[name="host_retry_check_interval"]')
    .should(
      'have.value',
      hostTemplates.defaultHostTemplate.retry_check_interval
    );

  cy.checkLegacyRadioButton('No');
});

When('the user deletes the configured host template', () => {
  checkFirstHostTemplateFromListing();
  cy.getIframeBody().find('select').eq(0).select('Delete');
  cy.wait('@getTimeZone');
  cy.exportConfig();
});

Then(
  'the deleted host template is not visible anymore on the host template page',
  () => {
    cy.getIframeBody()
      .contains(hostTemplates.defaultHostTemplate.name)
      .should('not.exist');
  }
);
