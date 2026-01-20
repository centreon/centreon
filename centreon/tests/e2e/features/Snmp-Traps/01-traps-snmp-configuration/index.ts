import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';

import { PAGES } from 'fixtures/shared/constants/pages';
import data from '../../../fixtures/snmp-traps/snmp-trap.json';
import {
  submitForm,
  trapsSnmpConfiguration,
  UpdateTrapsSnmpConfiguration
} from '../common';

beforeEach(() => {
  cy.startContainers();
  cy.intercept({
    method: 'GET',
    url: '/centreon/include/common/userTimezone.php'
  }).as('getUserTimezone');
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/internal.php?object=centreon_topology&action=navigationList'
  }).as('getNavigationList');
});

Given('a user is logged in Centreon', () => {
  cy.loginByTypeOfUser({ jsonName: 'admin' });
});

// Scenario: Creating SNMP trap with advanced matching rule
When(
  'the user adds a new SNMP trap definition with an advanced matching rule',
  () => {
    cy.visit(PAGES.configuration.snmpTrapsLegacy);
    cy.waitForElementInIframe('#main-content', 'input[name="searchT"]');
    cy.getIframeBody().find('a.bt_success').contains('Add').click();
    trapsSnmpConfiguration({
      name: data.snmp1.name,
      oid: data.snmp1.oid,
      output: data.snmp1.output,
      regexp: data.snmp1.rule.regexp,
      severity: data.snmp1.rule.status,
      string: data.snmp1.rule.string,
      vendor: data.snmp1.vendor
    });
  }
);

Then(
  'the trap definition is saved with its properties, especially the content of Regexp field',
  () => {
    submitForm();
    cy.waitForElementInIframe('#main-content', 'input[name="searchT"]');
    cy.getIframeBody().contains(data.snmp1.name).click();
    cy.waitForElementInIframe('#main-content', 'input[name="traps_name"]');
    cy.getIframeBody()
      .find('input[name="traps_name"]')
      .should('have.value', data.snmp1.name);
    cy.getIframeBody()
      .find('input[name="traps_oid"]')
      .should('have.value', data.snmp1.oid);
    cy.getIframeBody()
      .find(`span[title=${data.snmp1.vendor}]`)
      .contains(data.snmp1.vendor)
      .should('exist');
    cy.getIframeBody()
      .find('input[name="traps_args"]')
      .should('have.value', data.snmp1.output);
    cy.getIframeBody().find('div#matchingrules_add').click();
    cy.waitForElementInIframe('#main-content', 'input#rule_0');
    cy.getIframeBody()
      .find('input#rule_0')
      .should('have.value', data.snmp1.rule.string);
    cy.getIframeBody()
      .find('input#regexp_0')
      .should('have.value', data.snmp1.rule.regexp);
    cy.getIframeBody().find('select#rulestatus_0').should('have.value', '2');
  }
);

// Scenario: Modify SNMP trap definition
When(
  'the user modifies some properties of an existing SNMP trap definition',
  () => {
    cy.setUserTokenApiV1();
    cy.addHost({
      activeCheckEnabled: false,
      address: '1.2.3.4',
      alias: data.snmp2.hostName,
      checkCommand: 'check_centreon_cpu',
      name: data.snmp2.hostName,
      template: 'generic-host'
    });
    cy.addService({
      activeCheckEnabled: false,
      host: data.snmp2.hostName,
      maxCheckAttempts: 1,
      name: data.snmp2.serviceName,
      template: 'Ping-LAN'
    });
    cy.addServiceTemplate({
      name: data.snmp2.service_templates,
      template: 'generic-service'
    });
    cy.visit(PAGES.configuration.snmpTrapsLegacy);
    cy.waitForElementInIframe('#main-content', 'input[name="searchT"]');
    cy.getIframeBody().find('a.bt_success').contains('Add').click();
    trapsSnmpConfiguration({
      name: data.snmp1.name,
      oid: data.snmp1.oid,
      output: data.snmp1.output,
      regexp: data.snmp1.rule.regexp,
      severity: data.snmp1.rule.status,
      string: data.snmp1.rule.string,
      vendor: data.snmp1.vendor
    });
    submitForm();
    cy.waitForElementInIframe('#main-content', 'input[name="searchT"]');
    cy.getIframeBody().contains(data.snmp1.name).click();
    UpdateTrapsSnmpConfiguration({
      behavior: data.snmp2.behavior,
      comments: data.snmp2.comments,
      customCode: data.snmp2.custom_code,
      executionInterval: data.snmp2.execution_interval,
      filterServices: data.snmp2.filter_services,
      mode: data.snmp2.mode,
      name: data.snmp2.name,
      oid: data.snmp2.oid,
      output: data.snmp2.output,
      outputTransform: data.snmp2.output_transform,
      regexp: data.snmp2.rule.regexp,
      routingDefinition: data.snmp2.routing_definition,
      serviceName: data.snmp2.serviceName,
      serviceTemplates: data.snmp2.service_templates,
      severity: data.snmp2.rule.status,
      specialCommand: data.snmp2.special_command,
      status: data.snmp2.status,
      string: data.snmp2.rule.string,
      timeout: data.snmp2.timeout,
      vendor: data.snmp2.vendor
    });
    cy.getIframeBody()
      .find('div#validForm')
      .find('p.oreonbutton')
      .find('.btc.bt_success[name="submitC"]')
      .click();
  }
);

Then('all changes are saved', () => {
  cy.waitForElementInIframe('#main-content', 'input[name="searchT"]');
  cy.getIframeBody().contains(data.snmp2.name).click();
  cy.waitForElementInIframe('#main-content', 'input[name="traps_name"]');
  cy.getIframeBody()
    .find('input[name="traps_name"]')
    .should('have.value', data.snmp2.name);
  cy.getIframeBody()
    .find('input[name="traps_oid"]')
    .should('have.value', data.snmp2.oid);
  cy.getIframeBody()
    .find(`span[title=${data.snmp2.vendor}]`)
    .contains(data.snmp2.vendor)
    .should('exist');
  cy.getIframeBody()
    .find('input[name="traps_args"]')
    .should('have.value', data.snmp2.output);
  cy.getIframeBody()
    .find('select[name="traps_status"]')
    .find('option:selected')
    .should('have.value', '2');
  cy.getIframeBody()
    .find('select[name="traps_advanced_treatment_default"]')
    .should('have.value', '2');
  cy.getIframeBody().find('div#matchingrules_add').click();
  cy.getIframeBody()
    .find('input#rule_0')
    .should('have.value', data.snmp2.rule.string);
  cy.getIframeBody()
    .find('input#regexp_0')
    .should('have.value', data.snmp2.rule.regexp);
  cy.getIframeBody().find('select#rulestatus_0').should('have.value', '2');
  cy.getIframeBody()
    .find('input[name="traps_reschedule_svc_enable"]')
    .should('have.value', data.snmp2.reschedule);
  cy.getIframeBody()
    .find('input[name="traps_execution_command_enable"]')
    .should('have.value', '1');
  cy.getIframeBody()
    .find('input[name="traps_execution_command"]')
    .should('have.value', data.snmp2.special_command);
  cy.getIframeBody()
    .find('input[name="traps_execution_command"]')
    .should('have.value', data.snmp2.special_command);
  cy.getIframeBody()
    .find('textarea[name="traps_comments"]')
    .should('have.value', data.snmp2.comments);
  cy.getIframeBody().find('li#c2').click();
  cy.waitForElementInIframe('#main-content', 'span[name="services"]');
  cy.getIframeBody()
    .find(`span[title*=${data.snmp2.serviceName}]`)
    .contains(data.snmp2.serviceName)
    .should('exist');
  cy.getIframeBody()
    .find(`span[title*=${data.snmp2.service_templates}]`)
    .contains(`${data.snmp2.service_templates}`)
    .should('exist');
  cy.getIframeBody().find('li#c3').click();
  cy.waitForElementInIframe(
    '#main-content',
    'input[name="traps_routing_value"]'
  );
  cy.getIframeBody()
    .find('input[name="traps_routing_mode"]')
    .should('have.value', data.snmp2.routing);
  cy.getIframeBody()
    .find('input[name="traps_routing_value"]')
    .should('have.value', data.snmp2.routing_definition);
  cy.getIframeBody()
    .find('input[name="traps_routing_filter_services"]')
    .should('have.value', data.snmp2.filter_services);
  cy.getIframeBody()
    .find('input[name="traps_timeout"]')
    .should('have.value', data.snmp2.timeout);
  cy.getIframeBody()
    .find('input[name="traps_exec_interval"]')
    .should('have.value', data.snmp2.execution_interval);
  cy.getIframeBody()
    .find('input[name="traps_output_transform"]')
    .should('have.value', data.snmp2.output_transform);
  cy.getIframeBody()
    .find('textarea[name="traps_customcode"]')
    .should('have.value', data.snmp2.custom_code);
});

// Scenario: Duplicate SNMP trap definition
When('the user has duplicated one existing SNMP trap definition', () => {
  cy.visit(PAGES.configuration.snmpTrapsLegacy);
  cy.waitForElementInIframe('#main-content', 'input[name="searchT"]');
  cy.getIframeBody().find('a.bt_success').contains('Add').click();
  trapsSnmpConfiguration({
    name: data.snmp1.name,
    oid: data.snmp1.oid,
    output: data.snmp1.output,
    regexp: data.snmp1.rule.regexp,
    severity: data.snmp1.rule.status,
    string: data.snmp1.rule.string,
    vendor: data.snmp1.vendor
  });
  submitForm();
  cy.waitForElementInIframe('#main-content', 'input[name="searchT"]');
  cy.getIframeBody()
    .find('td.ListColLeft')
    .contains('a', data.snmp1.name)
    .parents('tr')
    .within(() => {
      cy.get('td.ListColPicker').find('div.md-checkbox').click();
    });
  cy.getIframeBody()
    .find('select[name="o2"]')
    .invoke(
      'attr',
      'onchange',
      "javascript: { setO(this.form.elements['o2'].value); this.form.submit(); }"
    );
  cy.getIframeBody().find('select[name="o2"]').select('Duplicate');
});

Then('all SNMP trap properties are unchanged except the name', () => {
  cy.reload();
  cy.getIframeBody().contains(`${data.snmp1.name}_1`).click();
  cy.waitForElementInIframe('#main-content', 'input[name="traps_name"]');
  cy.getIframeBody()
    .find('input[name="traps_name"]')
    .should('have.value', `${data.snmp1.name}_1`);
  cy.getIframeBody()
    .find('input[name="traps_oid"]')
    .should('have.value', data.snmp1.oid);
  cy.getIframeBody()
    .find(`span[title=${data.snmp1.vendor}]`)
    .contains(data.snmp1.vendor)
    .should('exist');
  cy.getIframeBody()
    .find('input[name="traps_args"]')
    .should('have.value', data.snmp1.output);
});

// Scenario: Delete SNMP trap definition
When('the user has deleted one existing SNMP trap definition', () => {
  cy.visit(PAGES.configuration.snmpTrapsLegacy);
  cy.waitForElementInIframe('#main-content', 'input[name="searchT"]');
  cy.getIframeBody().find('a.bt_success').contains('Add').click();
  trapsSnmpConfiguration({
    name: data.snmp1.name,
    oid: data.snmp1.oid,
    output: data.snmp1.output,
    regexp: data.snmp1.rule.regexp,
    severity: data.snmp1.rule.status,
    string: data.snmp1.rule.string,
    vendor: data.snmp1.vendor
  });
  submitForm();
  cy.waitForElementInIframe('#main-content', 'input[name="searchT"]');
  cy.getIframeBody()
    .find('td.ListColLeft')
    .contains('a', data.snmp1.name)
    .parents('tr')
    .within(() => {
      cy.get('td.ListColPicker').find('div.md-checkbox').click();
    });
  cy.getIframeBody()
    .find('select[name="o2"]')
    .invoke(
      'attr',
      'onchange',
      "javascript: { setO(this.form.elements['o2'].value); this.form.submit(); }"
    );
  cy.getIframeBody().find('select[name="o2"]').select('Delete');
});

Then('this definition disappears from the SNMP trap list', () => {
  cy.enterIframe('iframe#main-content')
    .find('table.ListTable tbody')
    .contains(data.snmp1.name)
    .should('not.exist');
});

afterEach(() => {
  cy.stopContainers();
});
