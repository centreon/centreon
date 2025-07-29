const trapsSnmpConfiguration = ({
  name,
  oid,
  vendor,
  output,
  string,
  regexp,
  severity
}): Cypress.Chainable => {
  cy.waitForElementInIframe('#main-content', 'input[name="traps_name"]');
  cy.getIframeBody().find('input[name="traps_name"]').type(name);
  cy.getIframeBody().find('input[name="traps_oid"]').type(oid);
  cy.getIframeBody()
    .find('span[id*="-manufacturer_id-container"]')
    .parent()
    .click();
  cy.getIframeBody().contains(`${vendor}`).click();
  cy.getIframeBody().find('input[name="traps_args"]').type(output);
  cy.getIframeBody().find('div#matchingrules_add').click();
  cy.getIframeBody().find('input#rule_0').clear().type(string);
  cy.getIframeBody().find('input#regexp_0').clear().type(regexp);
  return cy.getIframeBody().find('select#rulestatus_0').select(severity);
};

const UpdateTrapsSnmpConfiguration = ({
  name,
  oid,
  vendor,
  output,
  mode,
  status,
  behavior,
  string,
  regexp,
  severity,
  specialCommand,
  comments,
  serviceName,
  serviceTemplates,
  routingDefinition,
  filterServices,
  timeout,
  executionInterval,
  outputTransform,
  customCode
}): Cypress.Chainable => {
  cy.waitForElementInIframe('#main-content', 'input[name="traps_name"]');
  cy.getIframeBody().find('input[name="traps_name"]').clear().type(name);
  cy.getIframeBody().find('input[name="traps_oid"]').clear().type(oid);
  cy.getIframeBody()
    .find('span[id*="-manufacturer_id-container"]')
    .parent()
    .click();
  cy.getIframeBody().contains(`${vendor}`).click();
  cy.getIframeBody().find('input[name="traps_args"]').clear().type(output);
  cy.getIframeBody()
    .find(`input[name*="traps_mode"][value=${mode}]`)
    .parent()
    .click();
  cy.getIframeBody().find('select[name="traps_status"]').select(status);
  cy.getIframeBody()
    .find(`input[name*="traps_advanced_treatment"][value=${mode}]`)
    .parent()
    .click();
  cy.getIframeBody()
    .find('select[name="traps_advanced_treatment_default"]')
    .select(behavior);
  cy.getIframeBody().find('div#matchingrules_add').click();
  cy.getIframeBody().find('input#rule_0').clear().type(string);
  cy.getIframeBody().find('input#regexp_0').clear().type(regexp);
  cy.getIframeBody().find('select#rulestatus_0').select(severity);
  cy.getIframeBody()
    .find('input[name="traps_reschedule_svc_enable"]')
    .parent()
    .click();
  cy.getIframeBody()
    .find('input[name="traps_execution_command_enable"]')
    .parent()
    .click();
  cy.getIframeBody()
    .find('input[name="traps_execution_command"]')
    .type(specialCommand);
  cy.getIframeBody().find('textarea[name="traps_comments"]').type(comments);
  cy.getIframeBody().find('li#c2').click();
  cy.waitForElementInIframe('#main-content', 'span[name="services"]');
  cy.getIframeBody()
    .find('input[placeholder="Linked Services"]')
    .parent()
    .parent()
    .click();
  cy.getIframeBody().contains(serviceName).click();
  cy.getIframeBody()
    .find('input[placeholder="Linked Service Templates"]')
    .parent()
    .parent()
    .click();
  cy.getIframeBody().contains(serviceTemplates).click();
  cy.getIframeBody().find('li#c3').click();
  cy.waitForElementInIframe(
    '#main-content',
    'input[name="traps_routing_value"]'
  );
  cy.getIframeBody().find('input[name="traps_routing_mode"]').parent().click();
  cy.getIframeBody()
    .find('input[name="traps_routing_value"]')
    .type(routingDefinition);
  cy.getIframeBody()
    .find('input[name="traps_routing_filter_services"]')
    .type(filterServices);
  cy.getIframeBody().find('input[name="traps_log"]').parent().click();
  cy.getIframeBody().find('input[name="traps_timeout"]').type(timeout);
  cy.getIframeBody()
    .find('input[name="traps_exec_interval"]')
    .type(executionInterval);
  cy.getIframeBody()
    .find('input[name*="traps_exec_interval_type"][value="2"]')
    .parent()
    .click();
  cy.getIframeBody()
    .find('input[name*="traps_downtime"][value="2"]')
    .parent()
    .click();
  cy.getIframeBody()
    .find('input[name="traps_output_transform"]')
    .type(outputTransform);
  cy.getIframeBody().find('textarea[name="traps_customcode"]').type(customCode);
  return cy.getIframeBody(); // Ensure a Cypress.Chainable is returned
};

function submitForm() {
  cy.getIframeBody()
    .find('div#validForm')
    .find('p.oreonbutton')
    .find('.btc.bt_success[name="submitA"]')
    .click();
}

const CreateOrUpdateTrapGroup = (body: TrapGroup): Cypress.Chainable => {
  cy.waitForElementInIframe('#main-content', 'input[name="name"]');
  cy.getIframeBody().find('input[name="name"]').clear().type(body.name);
  cy.getIframeBody().find('span[class="clearAllSelect2"]').click();
  cy.getIframeBody().find('input[class="select2-search__field"]').click();
  cy.wait('@listTraps');
  cy.getIframeBody().find(`div[title="${body.traps[0]}"]`).click();
  cy.getIframeBody().find('input[class="select2-search__field"]').click();
  cy.getIframeBody().find(`div[title="${body.traps[1]}"]`).click();
  return cy
    .getIframeBody()
    .find('input.btc.bt_success[name^="submit"]')
    .eq(1)
    .click();
};

const AddOrUpdateVendor = (body: Vendor): Cypress.Chainable => {
  // wait for the name input to be charged on the DOM
  cy.waitForElementInIframe('#main-content', 'input[name="name"]');
  // type a value on the Vendor Name input
  cy.getIframeBody().find('input[name="name"]').clear().type(body.name);
  // type a value on the Vendor Alias input
  cy.getIframeBody().find('input[name="alias"]').clear().type(body.alias);
  // type a value on the Vendor Description textarea
  cy.getIframeBody()
    .find('textarea[name="description"]')
    .clear()
    .type(body.description);
  // click on the first Save button
  const chain = cy
    .getIframeBody()
    .find('input.btc.bt_success[name^="submit"]')
    .eq(0)
    .click();
  // export configuration
  cy.exportConfig();
  cy.wait('@getTimeZone');
  return chain;
};

const CheckVendorFieldsValues = (
  name: string,
  body: Vendor
): Cypress.Chainable => {
  // wait for the name input to be charged on the DOM
  cy.waitForElementInIframe('#main-content', 'input[name="name"]');
  // check that the Vendor Name input contains the right value
  cy.getIframeBody().find('input[name="name"]').should('have.value', `${name}`);
  // check that the Vendor Alias input contains the right value
  cy.getIframeBody()
    .find('input[name="alias"]')
    .should('have.value', `${body.alias}`);
  // check that the Vendor Description textarea contains the right value
  return cy
    .getIframeBody()
    .find('textarea[name="description"]')
    .should('have.value', `${body.description}`);
};

interface TrapGroup {
  name: string;
  traps: string[];
}

interface Vendor {
  name: string;
  alias: string;
  description: string;
}

export {
  submitForm,
  trapsSnmpConfiguration,
  UpdateTrapsSnmpConfiguration,
  CreateOrUpdateTrapGroup,
  AddOrUpdateVendor,
  CheckVendorFieldsValues
};
