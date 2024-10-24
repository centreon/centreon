const TrapsSNMPConfiguration = ({name, oid, vendor, output}): Cypress.Chainable => {
  cy.waitForElementInIframe("#main-content", 'input[name="traps_name"]');
  cy.getIframeBody().find('input[name="traps_name"]').type(name);
  cy.getIframeBody().find('input[name="traps_oid"]').type(oid);
  cy.getIframeBody()
    .find('span[id*="-manufacturer_id-container"]')
    .parent()
    .click();
  cy.contains(`${vendor}`).click();
  cy.getIframeBody().find('input[name="traps_args"]').type(output);
};

const UpdateTrapsSNMPConfiguration = ({
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
}): Cypress.Chainable => {
  cy.waitForElementInIframe("#main-content", 'input[name="traps_name"]');
  cy.getIframeBody().find('input[name="traps_name"]').type(name);
  cy.getIframeBody().find('input[name="traps_oid"]').type(oid);
  cy.getIframeBody()
    .find('span[id*="-manufacturer_id-container"]')
    .parent()
    .click();
  cy.contains(`${vendor}`).click();
  cy.getIframeBody().find('input[name="traps_args"]').type(output);
  cy.getIframeBody()
    .find(`input[name*="traps_mode"][value=${mode}]`)
    .parent()
    .click();
  cy.getIframeBody().find('select[name="traps_status"]').select(status);
  cy.getIframeBody()
    .find(`input[name*="traps_advanced_treatment"][value=${status}]`)
    .parent()
    .click();
  cy.getIframeBody()
    .find('select[name="traps_advanced_treatment_default"]')
    .select(behavior);
  cy.getIframeBody().find("div#matchingrules_add").click();
  cy.getIframeBody().find("input#rule_0").clear().type(string);
  cy.getIframeBody().find("input#regexp_0").clear().type(regexp);
  cy.getIframeBody().find("select#rulestatus_0").select(severity);
};


function submitForm() {
  cy.getIframeBody()
    .find("div#validForm")
    .find("p.oreonbutton")
    .find('.btc.bt_success[name="submitA"]')
    .click();
}

export { submitForm, TrapsSNMPConfiguration };
