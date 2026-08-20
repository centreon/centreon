// Shared selectors of the modernized listing / form framework
// (www/include/common/listing + www/include/common/form).
const listingTable = 'table.cl-listing-table';
const listingTableBody = '#clTableBody';
const listingSearchInput = '#clSearchInput';
const listingAddButton = 'a.cl-btn-add';
const listingPagination = '#clPaginationTop';
const rowCheckbox = '.cl-col-picker input[type="checkbox"]';
const sidePanelFrame = '#cfSidePanelFrame';
const saveButton = 'input.btc.bt_success[name^="submit"]';

// Generate page (Configuration > SNMP Traps > Generate)
const generatePollersField = '#nhost';
const generateApplyButton = '#applyBtn';
const generateAdvancedSummary = 'details.gen-adv summary';

const trapsSnmpConfiguration = ({
  name,
  oid,
  vendor,
  output,
  string,
  regexp,
  severity
}): Cypress.Chainable => {
  cy.getTrapSidePanelBody().find('input[name="traps_name"]').clear().type(name);
  cy.getTrapSidePanelBody().find('input[name="traps_oid"]').clear().type(oid);
  cy.selectTrapSidePanelOption('Vendor Name', vendor);
  cy.getTrapSidePanelBody()
    .find('input[name="traps_args"]')
    .clear()
    .type(output);
  cy.getTrapSidePanelBody().find('div#matchingrules_add').click();
  cy.getTrapSidePanelBody().find('input#rule_0').clear().type(string);
  cy.getTrapSidePanelBody().find('input#regexp_0').clear().type(regexp);

  return cy.getTrapSidePanelBody().find('select#rulestatus_0').select(severity);
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
  cy.getTrapSidePanelBody().find('input[name="traps_name"]').clear().type(name);
  cy.getTrapSidePanelBody().find('input[name="traps_oid"]').clear().type(oid);
  cy.selectTrapSidePanelOption('Vendor Name', vendor);
  cy.getTrapSidePanelBody()
    .find('input[name="traps_args"]')
    .clear()
    .type(output);
  // Radio pairs are rendered as segmented buttons by CentreonForm; the radios
  // themselves are kept in a hidden holder, so drive them through the buttons.
  clickSegmentedValue('traps_mode', mode);
  cy.getTrapSidePanelBody().find('select[name="traps_status"]').select(status);
  clickToggle('traps_advanced_treatment');
  cy.getTrapSidePanelBody()
    .find('select[name="traps_advanced_treatment_default"]')
    .select(behavior);
  cy.getTrapSidePanelBody().find('div#matchingrules_add').click();
  cy.getTrapSidePanelBody().find('input#rule_0').clear().type(string);
  cy.getTrapSidePanelBody().find('input#regexp_0').clear().type(regexp);
  cy.getTrapSidePanelBody().find('select#rulestatus_0').select(severity);
  clickToggle('traps_reschedule_svc_enable');
  clickToggle('traps_execution_command_enable');
  cy.getTrapSidePanelBody()
    .find('input[name="traps_execution_command"]')
    .clear()
    .type(specialCommand);
  cy.getTrapSidePanelBody()
    .find('textarea[name="traps_comments"]')
    .clear()
    .type(comments);
  // Every section of the modernized form is rendered at once (the tab nav only
  // scrolls), so the relation and advanced fields need no tab click any more.
  cy.selectTrapSidePanelOption('Linked services', serviceName);
  cy.selectTrapSidePanelOption('Linked service templates', serviceTemplates);
  clickToggle('traps_routing_mode');
  cy.getTrapSidePanelBody()
    .find('input[name="traps_routing_value"]')
    .clear()
    .type(routingDefinition);
  cy.getTrapSidePanelBody()
    .find('input[name="traps_routing_filter_services"]')
    .clear()
    .type(filterServices);
  clickToggle('traps_log');
  cy.getTrapSidePanelBody()
    .find('input[name="traps_timeout"]')
    .clear()
    .type(timeout);
  cy.getTrapSidePanelBody()
    .find('input[name="traps_exec_interval"]')
    .clear()
    .type(executionInterval);
  cy.getTrapSidePanelBody()
    .find('input[name*="traps_exec_interval_type"][value="2"]')
    .click({ force: true });
  clickSegmentedValue('traps_downtime', '2');
  cy.getTrapSidePanelBody()
    .find('input[name="traps_output_transform"]')
    .clear()
    .type(outputTransform);

  return cy
    .getTrapSidePanelBody()
    .find('textarea[name="traps_customcode"]')
    .clear()
    .type(customCode);
};

/** Click a segmented button (converted radio pair) by the radio value it sets. */
const clickSegmentedValue = (radioName: string, value: string): void => {
  cy.getTrapSidePanelBody()
    .find(
      `.cf-segmented[data-radio-name="${radioName}"] button[data-value="${value}"]`
    )
    .click({ force: true });
};

/** Click a cl-toggle switch: its real checkbox is hidden behind the slider. */
const clickToggle = (fieldName: string): void => {
  cy.getTrapSidePanelBody()
    .find(`input[name="${fieldName}"]`)
    .click({ force: true });
};

/** Save the side-panel form (Save is the first submit button of .cf-actions). */
const submitForm = (): void => {
  cy.getTrapSidePanelBody().find(saveButton).first().click();
};

const CreateOrUpdateTrapGroup = (body: TrapGroup): Cypress.Chainable => {
  cy.getTrapSidePanelBody().find('input[name="name"]').clear().type(body.name);
  cy.selectTrapSidePanelOption('Traps', body.traps[0]);
  cy.selectTrapSidePanelOption('Traps', body.traps[1]);

  return cy.getTrapSidePanelBody().find(saveButton).first().click();
};

const AddOrUpdateVendor = (body: Vendor): Cypress.Chainable => {
  cy.getTrapSidePanelBody().find('input[name="name"]').clear().type(body.name);
  cy.getTrapSidePanelBody()
    .find('input[name="alias"]')
    .clear()
    .type(body.alias);
  cy.getTrapSidePanelBody()
    .find('textarea[name="description"]')
    .clear()
    .type(body.description);

  const chain = cy.getTrapSidePanelBody().find(saveButton).first().click();

  cy.exportConfig();
  cy.wait('@getTimeZone');

  return chain;
};

const CheckVendorFieldsValues = (
  name: string,
  body: Vendor
): Cypress.Chainable => {
  cy.getTrapSidePanelBody()
    .find('input[name="name"]')
    .should('have.value', `${name}`);
  cy.getTrapSidePanelBody()
    .find('input[name="alias"]')
    .should('have.value', `${body.alias}`);

  return cy
    .getTrapSidePanelBody()
    .find('textarea[name="description"]')
    .should('have.value', `${body.description}`);
};

interface TrapGroup {
  name: string;
  traps: Array<string>;
}

interface Vendor {
  name: string;
  alias: string;
  description: string;
}

export {
  AddOrUpdateVendor,
  CheckVendorFieldsValues,
  clickSegmentedValue,
  clickToggle,
  CreateOrUpdateTrapGroup,
  generateAdvancedSummary,
  generateApplyButton,
  generatePollersField,
  listingAddButton,
  listingPagination,
  listingSearchInput,
  listingTable,
  listingTableBody,
  rowCheckbox,
  saveButton,
  sidePanelFrame,
  submitForm,
  trapsSnmpConfiguration,
  UpdateTrapsSnmpConfiguration
};
