Cypress.Commands.add('addOrUpdateVirtualMetric', (body: VirtualMetric) => {
    cy.wait('@getTimeZone');
    cy.waitForElementInIframe('#main-content', 'input[name="vmetric_name"]');
    cy.getIframeBody()
      .find('input[name="vmetric_name"]')
      .clear()
      .type(body.name);
    cy.getIframeBody()
      .find('span[id="select2-host_id-container"]')
      .click();
    cy.getIframeBody()
      .find(`div[title='${body.linkedHostServices}']`)
      .click();
    cy.getIframeBody()
      .find('textarea[name="rpn_function"]')
      .clear();
    cy.getIframeBody()
      .find('span[id="select2-sl_list_metrics-container"]')
      .click();
    cy.wait('@getListOfMetricsByService')
    cy.waitUntil(
        () => {
          return cy.getIframeBody()
          .find('.select2-results')
          .find('li')
            .then(($lis) => {
              const count = $lis.length;
              if (count <= 1) {
                cy.exportConfig();
                cy.getIframeBody()
                  .find('span[title="Clear field"]')
                  .eq(1)
                  .click();
                cy.getIframeBody()
                  .find('span[id="select2-sl_list_metrics-container"]')
                  .click();
                cy.wait('@getListOfMetricsByService')
              }
              return count > 1;
            });
        },
        { interval: 10000, timeout: 600000 }
    );
    
    cy.getIframeBody()
      .find('span[title="Clear field"]')
      .eq(1)
      .click();
    cy.getIframeBody()
      .find('span[id="select2-sl_list_metrics-container"]')
      .click();
    cy.wait('@getListOfMetricsByService')
    cy.getIframeBody()
      .find(`div[title='${body.knownMetric}']`)
      .click();  
    cy.getIframeBody()
      .find('#td_list_metrics img')
      .eq(0)
      .click();
    cy.getIframeBody()
      .find('input[name="unit_name"]')
      .clear()
      .type(body.unit); 
    cy.getIframeBody()
      .find('input[name="warn"]')
      .clear()
      .type(body.warning_threshold); 
    cy.getIframeBody()
      .find('input[name="crit"]')
      .clear()
      .type(body.critical_threshold); 
    cy.getIframeBody().find('div.md-checkbox.md-checkbox-inline').eq(0).click();
    cy.getIframeBody()
      .find('textarea[name="comment"]')
      .clear()
      .type(body.comments);
    cy.getIframeBody().find('input.btc.bt_success[name^="submit"]').eq(0).click();
    cy.wait('@getTimeZone');
    cy.exportConfig();
});

Cypress.Commands.add('checkFieldsOfVM', (body: VirtualMetric) => {
  cy.waitForElementInIframe('#main-content', 'input[name="vmetric_name"]');
  cy.getIframeBody()
    .find('input[name="vmetric_name"]')
    .should('have.value', body.name);
  cy.getIframeBody()
    .find('#host_id')
    .find('option:selected')
    .should('have.length', 1)
    .and('have.text', body.linkedHostServices);
  cy.getIframeBody()
    .find('textarea[name="rpn_function"]')
    .should('have.value', body.knownMetric);
  cy.getIframeBody()
    .find('input[name="unit_name"]')
    .should('have.value', body.unit);
  cy.getIframeBody()
    .find('input[name="warn"]')
    .should('have.value', body.warning_threshold);
  cy.getIframeBody()
    .find('input[name="crit"]')
    .should('have.value', body.critical_threshold);
  cy.getIframeBody()
    .find('textarea[name="comment"]')
    .should('have.value', body.comments);
});

Cypress.Commands.add('addMetaService', (body: MetaService)  => {
  cy.getIframeBody().find('a.bt_success').contains('Add').click();
  cy.wait('@getTimeZone');
  cy.waitForElementInIframe('#main-content', 'input[name="meta_name"]');
  cy.getIframeBody().find('input[name="meta_name"]').type(body.name);
  cy.getIframeBody()
    .find('input[name="max_check_attempts"]')
    .type(body.max_check_attempts);
  cy.getIframeBody().find('input.btc.bt_success[name^="submit"]').eq(0).click();
  cy.wait('@getTimeZone');
});

Cypress.Commands.add('addMSDependency', (body: MetaServiceDependency) => {
  cy.waitForElementInIframe('#main-content', 'input[name="dep_name"]');
  cy.getIframeBody()
    .find('input[name="dep_name"]')
    .type(body.name);
  cy.getIframeBody()
    .find('input[name="dep_description"]')
    .type(body.description);
  cy.getIframeBody().find('label[for="eUnknown"]').click({ force: true });
  cy.getIframeBody().find('label[for="nUnknown"]').click({ force: true });
  cy.getIframeBody().find('input[class="select2-search__field"]').eq(0).click();
  cy.getIframeBody().find(`div[title="${body.metaServicesNames[0]}"]`).click();
  cy.getIframeBody().find('input[class="select2-search__field"]').eq(0).click();
  cy.getIframeBody().find(`div[title="${body.metaServicesNames[1]}"]`).click();
  cy.getIframeBody().find('input[class="select2-search__field"]').eq(1).click();
  cy.getIframeBody().find(`div[title="${body.dependentMSNames[0]}"]`).click();
  cy.getIframeBody()
    .find('textarea[name="dep_comment"]')
    .type(body.comment);
  cy.getIframeBody().find('input.btc.bt_success[name^="submit"]').eq(0).click();
});

Cypress.Commands.add('updateMSDependency', (body: MetaServiceDependency) => {
  cy.waitForElementInIframe('#main-content', 'input[name="dep_name"]');
    cy.getIframeBody()
      .find('input[name="dep_name"]')
      .clear()
      .type(body.name);
    cy.getIframeBody()
      .find('input[name="dep_description"]')
      .clear()
      .type(body.description);
    cy.getIframeBody().find('label[for="eUnknown"]').click({ force: true });
    cy.getIframeBody().find('label[for="eOk"]').click({ force: true });

    cy.getIframeBody().find('label[for="nUnknown"]').click({ force: true });
    cy.getIframeBody().find('label[for="nCritical"]').click({ force: true });
    cy.getIframeBody().find('span[title="Clear field"]').eq(0).click();
    cy.getIframeBody()
      .find('input[class="select2-search__field"]')
      .eq(0)
      .click();
    cy.getIframeBody().find(`div[title="${body.metaServicesNames[0]}"]`).click();
    cy.getIframeBody().find('span[title="Clear field"]').eq(1).click();
    cy.getIframeBody()
      .find('input[class="select2-search__field"]')
      .eq(1)
      .click();
    cy.getIframeBody().find(`div[title="${body.dependentMSNames[0]}"]`).click();
    cy.getIframeBody()
      .find('textarea[name="dep_comment"]')
      .clear()
      .type(body.comment);
    cy.getIframeBody()
      .find('input.btc.bt_success[name^="submit"]')
      .eq(0)
      .click();
})

Cypress.Commands.add('addCommonDependencyFileds', (body: Dependency) => {
  cy.waitForElementInIframe('#main-content', 'input[name="dep_name"]');
  cy.getIframeBody()
    .find('input[name="dep_name"]')
    .type(body.name);
  cy.getIframeBody()
    .find('input[name="dep_description"]')
    .type(body.description);
  cy.getIframeBody().find('label[for="eOk"]').click({ force: true });
  cy.getIframeBody().find('label[for="eWarning"]').click({ force: true });
  cy.getIframeBody().find('label[for="eCritical"]').click({ force: true });
  cy.getIframeBody().find('label[for="nOk"]').click({ force: true });
  cy.getIframeBody().find('label[for="nWarning"]').click({ force: true });
  cy.getIframeBody().find('label[for="nCritical"]').click({ force: true });
  cy.getIframeBody()
    .find('textarea[name="dep_comment"]')
    .type(body.comment);
});

Cypress.Commands.add('updateCommonDependencyFileds', (body: Dependency) => {
  cy.waitForElementInIframe('#main-content', 'input[name="dep_name"]');
  cy.getIframeBody()
    .find('input[name="dep_name"]')
    .clear()
    .type(body.name);
  cy.getIframeBody()
    .find('input[name="dep_description"]')
    .clear()
    .type(body.description);
  cy.getIframeBody().find('label[for="eOk"]').click({ force: true });
  cy.getIframeBody().find('label[for="nOk"]').click({ force: true });
  cy.getIframeBody()
  .find('textarea[name="dep_comment"]')
  .clear()
  .type(body.comment);
});


Cypress.Commands.add('addServiceDependency', (body: ServiceDependency) => {
  cy.addCommonDependencyFileds(body.dependency);
  cy.getIframeBody().find('input[class="select2-search__field"]').eq(0).click();
  cy.getIframeBody().find(`div[title="${body.services[0]}"]`).click();
  cy.getIframeBody().find('input[class="select2-search__field"]').eq(1).type(`host2 - ${body.dependentServices[0]}`);
  cy.getIframeBody().find(`div[title="host2 - ${body.dependentServices[0]}"]`).click();
  cy.getIframeBody().find('input[class="select2-search__field"]').eq(2).click();
  cy.getIframeBody().find(`div[title="${body.dependentHosts[0]}"]`).click();
  cy.getIframeBody().find('input.btc.bt_success[name^="submit"]').eq(0).click();
  cy.wait('@getTimeZone');
  cy.exportConfig();
});

Cypress.Commands.add('updateServiceDependency', (body: ServiceDependency) => {
  cy.updateCommonDependencyFileds(body.dependency);
  cy.getIframeBody().find('span[title="Clear field"]').eq(0).click();
  cy.getIframeBody()
    .find('input[class="select2-search__field"]')
    .eq(0)
    .click();
  cy.getIframeBody().find(`div[title="host2 - ${body.services[0]}"]`).click();
  cy.getIframeBody().find('span[title="Clear field"]').eq(1).click();
  cy.getIframeBody()
    .find('input[class="select2-search__field"]')
    .eq(1)
    .type(body.dependentServices[0]);
  cy.getIframeBody().find(`div[title="host3 - ${body.dependentServices[0]}"]`).click();
  cy.getIframeBody().find('span[title="Clear field"]').eq(2).click();
  cy.getIframeBody()
    .find('input[class="select2-search__field"]')
    .eq(2)
    .type(body.dependentHosts[0]);
  cy.getIframeBody().find(`div[title="${body.dependentHosts[0]}"]`).click();
  cy.getIframeBody()
    .find('input.btc.bt_success[name^="submit"]')
    .eq(0)
    .click();
  cy.wait('@getTimeZone');
  cy.exportConfig();
})

Cypress.Commands.add('addServiceGroupDependency', (body: ServiceGroupDependency) => {
  cy.addCommonDependencyFileds(body.dependency);
  cy.getIframeBody().find('input[class="select2-search__field"]').eq(0).click();
  cy.getIframeBody().find(`div[title="${body.service_groups[0]}"]`).click();
  cy.getIframeBody().find('input[class="select2-search__field"]').eq(1).type(body.dependent_service_groups[0]);
  cy.getIframeBody().find(`div[title="${body.dependent_service_groups[0]}"]`).click();
  cy.getIframeBody().find('input.btc.bt_success[name^="submit"]').eq(0).click();
  cy.wait('@getTimeZone');
  cy.exportConfig();
});

Cypress.Commands.add('updateServiceGroupDependency', (body: ServiceGroupDependency) => {
  cy.updateCommonDependencyFileds(body.dependency); 
  cy.getIframeBody().find('span[title="Clear field"]').eq(0).click();
  cy.getIframeBody()
    .find('input[class="select2-search__field"]')
    .eq(0)
    .click();
  cy.getIframeBody().find(`div[title="${body.service_groups[0]}"]`).click();
  cy.getIframeBody().find('span[title="Clear field"]').eq(1).click();
  cy.getIframeBody()
    .find('input[class="select2-search__field"]')
    .eq(1)
    .type(body.dependent_service_groups[0]);
  cy.getIframeBody().find(`div[title="${body.dependent_service_groups[0]}"]`).click();
  cy.getIframeBody()
    .find('input.btc.bt_success[name^="submit"]')
    .eq(0)
    .click();
  cy.wait('@getTimeZone');
  cy.exportConfig();
});

Cypress.Commands.add('createOrUpdateHostGroupService', (body: HostGroupService, isUpdate: boolean, htmldata: HtmlElt[]) => {
  cy.waitForElementInIframe('#main-content', 'input[name="service_description"]');
  cy.fillFieldInIframe(htmldata[0]);
  [htmldata[1], htmldata[2], htmldata[3], htmldata[4]].forEach((elt) => {
    cy.clickOnFieldInIframe(elt);
  })
  cy.getIframeBody().find('#select2-service_template_model_stm_id-container').click();
  [htmldata[5], htmldata[6]].forEach((elt) => {
    cy.clickOnFieldInIframe(elt);
  })
  cy.getIframeBody().find('#select2-command_command_id-container').click();
  cy.getIframeBody().find('input[class="select2-search__field"]').eq(6).type(body.checkcommand);
  cy.clickOnFieldInIframe(htmldata[7]);
  cy.getIframeBody().find('#macro_add').click();
  cy.waitForElementInIframe('#main-content', '#macroInput_0');
  cy.getIframeBody().find('#macroInput_0').clear().type(body.macroname);
  cy.getIframeBody().find('#macroValue_0').clear().type(`${body.macrovalue}`);
  cy.clickOnFieldInIframe(htmldata[8]);
  cy.getIframeBody().find('#select2-timeperiod_tp_id-container').click();
  cy.clickOnFieldInIframe(htmldata[9]);
  [htmldata[10], htmldata[11], htmldata[12]].forEach((elt) => {
    cy.fillFieldInIframe(elt);
  })
  if(isUpdate){
    cy.getIframeBody().contains('label','No').eq(0).click();
  }
  //Notifications
  cy.getIframeBody().contains('a', 'Notifications').click();
  cy.get('body').click(0, 0);
  cy.clickOnFieldInIframe(htmldata[13]);
  cy.getIframeBody().find('input[class="select2-search__field"]').eq(1).click({force: true});
  [htmldata[14], htmldata[15]].forEach((elt) => {
    cy.clickOnFieldInIframe(elt);
  })
  cy.getIframeBody().find('input[class="select2-search__field"]').eq(2).click({force: true});
  cy.clickOnFieldInIframe(htmldata[16]);
  cy.fillFieldInIframe(htmldata[17]);
  cy.clickOnFieldInIframe(htmldata[18]);
  cy.getIframeBody().find('#select2-timeperiod_tp_id2-container').click();
  cy.clickOnFieldInIframe(htmldata[19]);
  cy.getIframeBody().find('#notifC').click({ force: true });
  if(isUpdate){
    cy.getIframeBody().find('#notifC').click({ force: true });
    cy.getIframeBody().find('#notifU').click({ force: true });
  }
  [htmldata[20], htmldata[21]].forEach((elt) => {
    cy.fillFieldInIframe(elt);
  })
  //Relations
  cy.getIframeBody().contains('a', 'Relations').click();
  cy.get('body').click(0, 0);
  cy.clickOnFieldInIframe(htmldata[22]);
  cy.getIframeBody().find('input[class="select2-search__field"]').eq(3).click({force: true});
  [htmldata[23], htmldata[24]].forEach((elt) => {
    cy.clickOnFieldInIframe(elt);
  })
  cy.getIframeBody().find('input[class="select2-search__field"]').eq(4).click({force: true});
  cy.clickOnFieldInIframe(htmldata[25]);
  //Data Processing
  cy.getIframeBody().contains('a', 'Data Processing').click();
  cy.get('body').click(0, 0);
  cy.fillFieldInIframe(htmldata[26]);
 //Extended Info
  cy.getIframeBody().contains('a', 'Extended Info').click();
  cy.get('body').click(0, 0);
  cy.clickOnFieldInIframe(htmldata[27]);
  cy.getIframeBody().find('input[class="select2-search__field"]').eq(5).click({force: true});
  cy.clickOnFieldInIframe(htmldata[28]);
  [htmldata[29], htmldata[30], htmldata[31]].forEach((elt) => {
    cy.fillFieldInIframe(elt);
  })
  cy.getIframeBody().find('#esi_icon_image').select('1');
  [htmldata[32], htmldata[33], htmldata[34]].forEach((elt) => {
    cy.fillFieldInIframe(elt);
  })
  cy.getIframeBody().find('input[value="Save"]').eq(1).click();
  cy.wait('@getTimeZone');
  cy.exportConfig();
});

Cypress.Commands.add('checkValuesOfHostGroupService', (name:string, body: HostGroupService) => {
  cy.waitForElementInIframe(
    '#main-content',
    `a:contains("${name}")`
  );
  cy.getIframeBody().contains(name).click();
  cy.waitForElementInIframe('#main-content', 'input[name="service_description"]');
  cy.getIframeBody()
    .find('input[name="service_description"]')
    .should('have.value', name);
  cy.getIframeBody()
      .find('#service_hgPars')
      .find('option:selected')
      .should('have.length', 1)
      .and('have.text', body.hostgroups);
  cy.getIframeBody()
      .find('#service_template_model_stm_id')
      .find('option:selected')
      .should('have.length', 1)
      .and('have.text', body.template);
  cy.getIframeBody()
      .find('#command_command_id')
      .find('option:selected')
      .should('have.length', 1)
      .and('have.text', body.checkcommand);
  cy.getIframeBody().find('#macroInput_0').should('have.value',body.macroname);
  cy.getIframeBody().find('#macroValue_0').should('have.value',body.macrovalue);
  cy.getIframeBody()
      .find('#timeperiod_tp_id')
      .find('option:selected')
      .should('have.length', 1)
      .and('have.text', body.checkperiod);
  cy.getIframeBody().find('input[name="service_max_check_attempts"]').should('have.value',body.maxcheckattempts);
  cy.getIframeBody().find('input[name="service_normal_check_interval"]').should('have.value',body.normalcheckinterval);
  cy.getIframeBody().find('input[name="service_retry_check_interval"]').should('have.value',body.retrycheckinterval);
  cy.checkLegacyRadioButton('No');
  //Notifications
  cy.getIframeBody().contains('a', 'Notifications').click();
  cy.get('body').click(0, 0);
   cy.getIframeBody()
      .find('#service_cs')
      .find('option:selected')
      .should('have.length', 1)
      .and('have.text', body.contacts);
  cy.getIframeBody()
      .find('#service_cgs')
      .find('option:selected')
      .should('have.length', 1)
      .and('have.text', body.contactgroups);

  cy.getIframeBody().find('input[name="service_notification_interval"]').should('have.value',body.notinterval);
   cy.getIframeBody()
      .find('#timeperiod_tp_id2')
      .find('option:selected')
      .should('have.length', 1)
      .and('have.text', body.notificationperiod);
  cy.getIframeBody().find('#notifC').should('be.checked');
  cy.getIframeBody().find('#notifU').should('be.checked');
  cy.getIframeBody().find('input[name="service_first_notification_delay"]').should('have.value',body.firstnotdelay);
  cy.getIframeBody().find('input[name="service_recovery_notification_delay"]').should('have.value',body.recoverynotdelay);
  //Relations
  cy.getIframeBody().contains('a', 'Relations').click();
  cy.get('body').click(0, 0);
  cy.getIframeBody()
      .find('#service_sgs')
      .find('option:selected')
      .should('have.length', 1)
      .and('have.text', body.servicegroups);
  cy.getIframeBody()
      .find('#service_traps')
      .find('option:selected')
      .should('have.length', 1)
      .and('have.text', body.servicetrap);
  //Data Processing
  cy.getIframeBody().contains('a', 'Data Processing').click();
  cy.get('body').click(0, 0);
  cy.getIframeBody().find('input[name="service_freshness_threshold"]').should('have.value',body.freshnessthreshold);
 //Extended Info
  cy.getIframeBody().contains('a', 'Extended Info').click();
  cy.get('body').click(0, 0);
  cy.getIframeBody()
      .find('#service_categories')
      .find('option:selected')
      .should('have.length', 1)
      .and('have.text', body.servicecategories);

  cy.getIframeBody().find('input[name="esi_notes_url"]').should('have.value',body.noteurl);
  cy.getIframeBody().find('input[name="esi_notes"]').should('have.value',body.note);
  cy.getIframeBody().find('input[name="esi_action_url"]').should('have.value',body.actionurl);
  cy.getIframeBody().find('#esi_icon_image')
    .should('have.value', '1'); 
  cy.getIframeBody().find('input[name="esi_icon_image_alt"]').should('have.value',body.atlicon);
  cy.getIframeBody().find('input[name="geo_coords"]').should('have.value',body.geocoords);
  cy.getIframeBody().find('textarea[name="service_comment"]').should('have.value',body.comment);
});


interface Dependency {
  name: string,
  description: string,
  parent_relationship: number,
  execution_fails_on_ok: number,
  execution_fails_on_warning: number,
  execution_fails_on_unknown: number,
  execution_fails_on_critical: number,
  execution_fails_on_pending: number,
  execution_fails_on_none: number,
  notification_fails_on_none: number,
  notification_fails_on_ok: number,
  notification_fails_on_warning: number,
  notification_fails_on_unknown: number,
  notification_fails_on_critical: number,
  notification_fails_on_pending: number,
  comment: string
}

interface ServiceDependency {
  dependency: Dependency,
  services: string[],
  dependentServices: string[],
  dependentHosts: string[],
}

interface ServiceGroupDependency {
  dependency: Dependency,
  service_groups: string[],
  dependent_service_groups: string[],
}

interface VirtualMetric {
  name: string,
  linkedHostServices: string,
  knownMetric: string,
  unit: string,
  warning_threshold: string,
  critical_threshold: string,
  comments: string,
}

interface MetaService {
  name : string,
  max_check_attempts: string
}

interface MetaServiceDependency {
  name: string,
  description: string,
  parent_relationship: number,
  execution_fails_on_ok: number,
  execution_fails_on_warning: number,
  execution_fails_on_unknown: number,
  execution_fails_on_critical: number,
  execution_fails_on_pending: number,
  execution_fails_on_none: number,
  notification_fails_on_none: number,
  notification_fails_on_ok: number,
  notification_fails_on_warning: number,
  notification_fails_on_unknown: number,
  notification_fails_on_critical: number,
  notification_fails_on_pending: number,
  metaServicesNames: string[],
  dependentMSNames: string[],
  comment: string
}

interface HostGroupService {
  name: string,
  hostgroups: string,
  template: string,
  checkcommand: string,
  macroname: string,
  macrovalue: number,
  checkperiod: string;
  maxcheckattempts: number;
  normalcheckinterval: number,
  retrycheckinterval: number,
  contacts: string;
  contactgroups: string;
  notinterval: number;
  notificationperiod: string;
  firstnotdelay: number;
  recoverynotdelay: number,
  servicegroups: string,
  servicetrap: string,
  freshnessthreshold: number,
  servicecategories: string,
  noteurl: string,
  note: string,
  actionurl: string,
  atlicon: string,
  geocoords: string,
  comment: string,
}

interface HtmlElt {
  tag: string,
  attribut: string,
  attributValue: string,
  valueOrIndex: string
}

declare global {
  namespace Cypress {
    interface Chainable {
      addOrUpdateVirtualMetric: (body: VirtualMetric) => Cypress.Chainable;
      checkFieldsOfVM: (body: VirtualMetric) => Cypress.Chainable;
      addMetaService: (body: MetaService) => Cypress.Chainable;
      addMSDependency: (body:MetaServiceDependency) => Cypress.Chainable;
      updateMSDependency: (body: MetaServiceDependency) => Cypress.Chainable;
      addServiceDependency: (body: ServiceDependency) => Cypress.Chainable;
      updateServiceDependency: (body: ServiceDependency) => Cypress.Chainable;
      addCommonDependencyFileds: (body: Dependency) => Cypress.Chainable;
      updateCommonDependencyFileds: (body: Dependency) => Cypress.Chainable;
      addServiceGroupDependency: (body:ServiceGroupDependency) => Cypress.Chainable;
      updateServiceGroupDependency: (body:ServiceGroupDependency) => Cypress.Chainable;
      createOrUpdateHostGroupService: (body: HostGroupService, isUpdate: boolean, htmldata: HtmlElt[]) => Cypress.Chainable;
      checkValuesOfHostGroupService: (name: string, body: HostGroupService) => Cypress.Chainable;
    }
  }
}

export {};