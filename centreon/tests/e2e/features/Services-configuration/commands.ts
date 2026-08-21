import { PAGES } from 'fixtures/shared/constants/pages';

interface Dependency {
  name: string;
  description: string;
  parentRelationship: number;
  executionFailsOnOk: number;
  executionFailsOnWarning: number;
  executionFailsOnUnknown: number;
  executionFailsOnCritical: number;
  executionFailsOnPending: number;
  executionFailsOnNone: number;
  notificationFailsOnNone: number;
  notificationFailsOnOk: number;
  notificationFailsOnWarning: number;
  notificationFailsOnUnknown: number;
  notificationFailsOnCritical: number;
  notificationFailsOnPending: number;
  comment: string;
}

interface ServiceDependency {
  dependency: Dependency;
  services: Array<string>;
  dependentServices: Array<string>;
  dependentHosts: Array<string>;
}

interface ServiceGroupDependency {
  dependency: Dependency;
  serviceGroups: Array<string>;
  dependentServiceGroups: Array<string>;
}

interface VirtualMetric {
  name: string;
  linkedHostServices: string;
  knownMetric: string;
  unit: string;
  warningThreshold: string;
  criticalThreshold: string;
  comments: string;
}

interface MetaService {
  name: string;
  maxCheckAttempts: string;
}

interface MetaServiceDependency {
  name: string;
  description: string;
  parentRelationship: number;
  executionFailsOnOk: number;
  executionFailsOnWarning: number;
  executionFailsOnUnknown: number;
  executionFailsOnCritical: number;
  executionFailsOnPending: number;
  executionFailsOnNone: number;
  notificationFailsOnNone: number;
  notificationFailsOnOk: number;
  notificationFailsOnWarning: number;
  notificationFailsOnUnknown: number;
  notificationFailsOnCritical: number;
  notificationFailsOnPending: number;
  metaServicesNames: Array<string>;
  dependentMetaServicesNames: Array<string>;
  comment: string;
}

interface HostGroupService {
  name: string;
  hostGroups: string;
  template: string;
  checkCommand: string;
  macroName: string;
  macroValue: number;
  checkPeriod: string;
  maxCheckAttempts: number;
  normalCheckInterval: number;
  retryCheckInterval: number;
  contacts: string;
  contactGroups: string;
  notificationInterval: number;
  notificationPeriod: string;
  firstNotificationDelay: number;
  recoveryNotificationDelay: number;
  serviceGroups: string;
  serviceTrap: string;
  freshnessThreshold: number;
  serviceCategories: string;
  noteUrl: string;
  note: string;
  actionUrl: string;
  atlIcon: string;
  geoCoords: string;
  geoCoordsTruncated: string;
  comment: string;
}

interface HtmlElt {
  tag: string;
  attribut: string;
  attributValue: string;
  valueOrIndex: string;
}

Cypress.Commands.add(
  'addOrUpdateVirtualMetric',
  (body: VirtualMetric, showGraph: boolean) => {
    cy.wait('@getTimeZone');
    cy.waitForElementInIframe('#main-content', 'input[name="vmetric_name"]');
    cy.getIframeBody()
      .find('input[name="vmetric_name"]')
      .clear()
      .type(body.name);
    cy.getIframeBody().find('span[id="select2-host_id-container"]').click();
    cy.getIframeBody().find(`div[title='${body.linkedHostServices}']`).click();
    cy.getIframeBody().find('textarea[name="rpn_function"]').clear();
    cy.getIframeBody()
      .find('span[id="select2-sl_list_metrics-container"]')
      .click();
    cy.wait('@getListOfMetricsByService');
    cy.waitUntil(
      () => {
        return cy
          .getIframeBody()
          .find('.select2-results')
          .find('li')
          .then((lis) => {
            const count = lis.length;
            if (count <= 1) {
              cy.exportConfig();
              cy.getIframeBody()
                .find('span[title="Clear field"]')
                .eq(1)
                .click();
              cy.getIframeBody()
                .find('span[id="select2-sl_list_metrics-container"]')
                .click();
              cy.wait('@getListOfMetricsByService');
            }
            return count > 1;
          });
      },
      { interval: 10000, timeout: 600000 }
    );

    cy.getIframeBody().find('span[title="Clear field"]').eq(1).click();
    cy.getIframeBody()
      .find('span[id="select2-sl_list_metrics-container"]')
      .click();
    cy.wait('@getListOfMetricsByService');
    cy.getIframeBody().find(`div[title='${body.knownMetric}']`).click();
    cy.getIframeBody().find('#td_list_metrics img').eq(0).click();
    cy.getIframeBody().find('input[name="unit_name"]').clear().type(body.unit);
    cy.getIframeBody()
      .find('input[name="warn"]')
      .clear()
      .type(body.warningThreshold);
    cy.getIframeBody()
      .find('input[name="crit"]')
      .clear()
      .type(body.criticalThreshold);
    if (!showGraph) {
      cy.getIframeBody()
        .find('div.md-checkbox.md-checkbox-inline')
        .eq(0)
        .click();
    }
    cy.getIframeBody()
      .find('textarea[name="comment"]')
      .clear()
      .type(body.comments);
    cy.getIframeBody()
      .find('input.btc.bt_success[name^="submit"]')
      .eq(0)
      .click();
    cy.wait('@getTimeZone');
    cy.exportConfig();
  }
);

Cypress.Commands.add('checkFieldsOfVm', (body: VirtualMetric) => {
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
    .should('have.value', body.warningThreshold);
  cy.getIframeBody()
    .find('input[name="crit"]')
    .should('have.value', body.criticalThreshold);
  cy.getIframeBody()
    .find('textarea[name="comment"]')
    .should('have.value', body.comments);
});

Cypress.Commands.add('addMetaService', (body: MetaService) => {
  // Wait for the modernized listing so external callers (which visit the page
  // but don't wait for it) don't race the Add click. Target the toolbar button
  // rather than the one the framework clones into the empty state: on an empty
  // listing both are present and .cl-btn-add matches two elements.
  cy.waitForElementInIframe('#main-content', '.cl-actions-left .cl-btn-add');
  cy.getIframeBody().find('.cl-actions-left .cl-btn-add').click();
  cy.getMetaServiceSidePanelBody()
    .find('input[name="meta_name"]', { timeout: 20_000 })
    .should('be.visible')
    .type(body.name);
  cy.getMetaServiceSidePanelBody()
    .find('input[name="max_check_attempts"]')
    .type(body.maxCheckAttempts);
  cy.getMetaServiceSidePanelBody()
    .find('input.btc.bt_success[name^="submit"]')
    .first()
    .click();
  cy.wait('@getTimeZone');
});

Cypress.Commands.add(
  'addMetaserviceDependency',
  (body: MetaServiceDependency) => {
    cy.waitForElementInIframe('#main-content', 'input[name="dep_name"]');
    cy.getIframeBody().find('input[name="dep_name"]').type(body.name);
    cy.getIframeBody()
      .find('input[name="dep_description"]')
      .type(body.description);
    cy.getIframeBody().find('label[for="eUnknown"]').click({ force: true });
    cy.getIframeBody().find('label[for="nUnknown"]').click({ force: true });
    cy.getIframeBody()
      .find('input[class="select2-search__field"]')
      .eq(0)
      .click();
    cy.getIframeBody()
      .find(`div[title="${body.metaServicesNames[0]}"]`)
      .click();
    cy.getIframeBody()
      .find('input[class="select2-search__field"]')
      .eq(0)
      .click();
    cy.getIframeBody()
      .find(`div[title="${body.metaServicesNames[1]}"]`)
      .click();
    cy.getIframeBody()
      .find('input[class="select2-search__field"]')
      .eq(1)
      .click();
    cy.getIframeBody()
      .find(`div[title="${body.dependentMetaServicesNames[0]}"]`)
      .click();
    cy.getIframeBody().find('textarea[name="dep_comment"]').type(body.comment);
    cy.getIframeBody()
      .find('input.btc.bt_success[name^="submit"]')
      .eq(0)
      .click();
  }
);

Cypress.Commands.add(
  'updateMetaserviceDependency',
  (body: MetaServiceDependency) => {
    cy.waitForElementInIframe('#main-content', 'input[name="dep_name"]');
    cy.getIframeBody().find('input[name="dep_name"]').clear().type(body.name);
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
    cy.getIframeBody()
      .find(`div[title="${body.metaServicesNames[0]}"]`)
      .click();
    cy.getIframeBody().find('span[title="Clear field"]').eq(1).click();
    cy.getIframeBody()
      .find('input[class="select2-search__field"]')
      .eq(1)
      .click();
    cy.getIframeBody()
      .find(`div[title="${body.dependentMetaServicesNames[0]}"]`)
      .click();
    cy.getIframeBody()
      .find('textarea[name="dep_comment"]')
      .clear()
      .type(body.comment);
    cy.getIframeBody()
      .find('input.btc.bt_success[name^="submit"]')
      .eq(0)
      .click();
  }
);

Cypress.Commands.add('addCommonDependencyFields', (body: Dependency) => {
  cy.waitForElementInIframe('#main-content', 'input[name="dep_name"]');
  cy.getIframeBody().find('input[name="dep_name"]').type(body.name);
  cy.getIframeBody()
    .find('input[name="dep_description"]')
    .type(body.description);
  cy.getIframeBody().find('label[for="eOk"]').click({ force: true });
  cy.getIframeBody().find('label[for="eWarning"]').click({ force: true });
  cy.getIframeBody().find('label[for="eCritical"]').click({ force: true });
  cy.getIframeBody().find('label[for="nOk"]').click({ force: true });
  cy.getIframeBody().find('label[for="nWarning"]').click({ force: true });
  cy.getIframeBody().find('label[for="nCritical"]').click({ force: true });
  cy.getIframeBody().find('textarea[name="dep_comment"]').type(body.comment);
});

Cypress.Commands.add('updateCommonDependencyFields', (body: Dependency) => {
  cy.waitForElementInIframe('#main-content', 'input[name="dep_name"]');
  cy.getIframeBody().find('input[name="dep_name"]').clear().type(body.name);
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
  cy.addCommonDependencyFields(body.dependency);
  cy.getIframeBody().find('input[class="select2-search__field"]').eq(0).click();
  cy.getIframeBody().find(`div[title="${body.services[0]}"]`).click();
  cy.getIframeBody()
    .find('input[class="select2-search__field"]')
    .eq(1)
    .type(`host2 - ${body.dependentServices[0]}`);
  cy.getIframeBody()
    .find(`div[title="host2 - ${body.dependentServices[0]}"]`)
    .click();
  cy.getIframeBody().find('input[class="select2-search__field"]').eq(2).click();
  cy.getIframeBody().find(`div[title="${body.dependentHosts[0]}"]`).click();
  cy.getIframeBody().find('input.btc.bt_success[name^="submit"]').eq(0).click();
  cy.wait('@getTimeZone');
  cy.exportConfig();
});

Cypress.Commands.add('updateServiceDependency', (body: ServiceDependency) => {
  cy.updateCommonDependencyFields(body.dependency);
  cy.getIframeBody().find('span[title="Clear field"]').eq(0).click();
  cy.getIframeBody().find('input[class="select2-search__field"]').eq(0).click();
  cy.getIframeBody().find(`div[title="host2 - ${body.services[0]}"]`).click();
  cy.getIframeBody().find('span[title="Clear field"]').eq(1).click();
  cy.getIframeBody()
    .find('input[class="select2-search__field"]')
    .eq(1)
    .type(body.dependentServices[0]);
  cy.getIframeBody()
    .find(`div[title="host3 - ${body.dependentServices[0]}"]`)
    .click();
  cy.getIframeBody().find('span[title="Clear field"]').eq(2).click();
  cy.getIframeBody()
    .find('input[class="select2-search__field"]')
    .eq(2)
    .type(body.dependentHosts[0]);
  cy.getIframeBody().find(`div[title="${body.dependentHosts[0]}"]`).click();
  cy.getIframeBody().find('input.btc.bt_success[name^="submit"]').eq(0).click();
  cy.wait('@getTimeZone');
  cy.exportConfig();
});

Cypress.Commands.add(
  'addServiceGroupDependency',
  (body: ServiceGroupDependency) => {
    cy.addCommonDependencyFields(body.dependency);
    cy.getIframeBody()
      .find('input[class="select2-search__field"]')
      .eq(0)
      .click();
    cy.getIframeBody().find(`div[title="${body.serviceGroups[0]}"]`).click();
    cy.getIframeBody()
      .find('input[class="select2-search__field"]')
      .eq(1)
      .type(body.dependentServiceGroups[0]);
    cy.getIframeBody()
      .find(`div[title="${body.dependentServiceGroups[0]}"]`)
      .click();
    cy.getIframeBody()
      .find('input.btc.bt_success[name^="submit"]')
      .eq(0)
      .click();
    cy.wait('@getTimeZone');
    cy.exportConfig();
  }
);

Cypress.Commands.add(
  'updateServiceGroupDependency',
  (body: ServiceGroupDependency) => {
    cy.updateCommonDependencyFields(body.dependency);
    cy.getIframeBody().find('span[title="Clear field"]').eq(0).click();
    cy.getIframeBody()
      .find('input[class="select2-search__field"]')
      .eq(0)
      .click();
    cy.getIframeBody().find(`div[title="${body.serviceGroups[0]}"]`).click();
    cy.getIframeBody().find('span[title="Clear field"]').eq(1).click();
    cy.getIframeBody()
      .find('input[class="select2-search__field"]')
      .eq(1)
      .type(body.dependentServiceGroups[0]);
    cy.getIframeBody()
      .find(`div[title="${body.dependentServiceGroups[0]}"]`)
      .click();
    cy.getIframeBody()
      .find('input.btc.bt_success[name^="submit"]')
      .eq(0)
      .click();
    cy.wait('@getTimeZone');
    cy.exportConfig();
  }
);

Cypress.Commands.add(
  'createOrUpdateHostGroupService',
  (body: HostGroupService, isUpdate: boolean, htmldata: Array<HtmlElt>) => {
    cy.getFormBody()
      .find('input[name="service_description"]', { timeout: 20_000 })
      .should('be.visible');
    cy.fillFieldInIframe(htmldata[0]);
    [htmldata[1], htmldata[2], htmldata[3], htmldata[4]].forEach((elt) => {
      cy.clickOnFieldInIframe(elt);
    });
    cy.getFormBody()
      .find('#select2-service_template_model_stm_id-container')
      .click();
    [htmldata[5], htmldata[6]].forEach((elt) => {
      cy.clickOnFieldInIframe(elt);
    });
    cy.getFormBody().find('#select2-command_command_id-container').click();
    cy.getFormBody()
      .find('input[class="select2-search__field"]')
      .eq(6)
      .type(body.checkCommand);
    cy.clickOnFieldInIframe(htmldata[7]);
    cy.getFormBody().find('#macro_add').click();
    cy.getFormBody().find('#macroInput_0', { timeout: 20_000 }).should('exist');
    cy.getFormBody().find('#macroInput_0').clear().type(body.macroName);
    cy.getFormBody().find('#macroValue_0').clear().type(`${body.macroValue}`);
    cy.clickOnFieldInIframe(htmldata[8]);
    cy.getFormBody().find('#select2-timeperiod_tp_id-container').click();
    cy.clickOnFieldInIframe(htmldata[9]);
    [htmldata[10], htmldata[11], htmldata[12]].forEach((elt) => {
      cy.fillFieldInIframe(elt);
    });
    if (isUpdate) {
      // The radio labels are hidden behind the generated segmented buttons;
      // keep the original intent (the form's first "No") without having to name
      // the field it belongs to.
      cy.getFormBody()
        .find('.cf-segmented button')
        .filter(':contains("No")')
        .first()
        .click({ force: true });
    }
    //Notifications
    cy.getFormBody().find('.cf-tab-nav a[href="#cf-sec-notif"]').click();
    cy.clickOnFieldInIframe(htmldata[13]);
    cy.getFormBody()
      .find('input[class="select2-search__field"]')
      .eq(1)
      .click({ force: true });
    [htmldata[14], htmldata[15]].forEach((elt) => {
      cy.clickOnFieldInIframe(elt);
    });
    cy.getFormBody()
      .find('input[class="select2-search__field"]')
      .eq(2)
      .click({ force: true });
    cy.clickOnFieldInIframe(htmldata[16]);
    cy.fillFieldInIframe(htmldata[17]);
    cy.clickOnFieldInIframe(htmldata[18]);
    cy.getFormBody().find('#select2-timeperiod_tp_id2-container').click();
    cy.clickOnFieldInIframe(htmldata[19]);
    cy.getFormBody().find('#notifC').click({ force: true });
    if (isUpdate) {
      cy.getFormBody().find('#notifC').click({ force: true });
      cy.getFormBody().find('#notifU').click({ force: true });
    }
    [htmldata[20], htmldata[21]].forEach((elt) => {
      cy.fillFieldInIframe(elt);
    });
    //Relations
    cy.getFormBody().find('.cf-tab-nav a[href="#cf-sec-relations"]').click();
    cy.clickOnFieldInIframe(htmldata[22]);
    cy.getFormBody()
      .find('input[class="select2-search__field"]')
      .eq(3)
      .click({ force: true });
    [htmldata[23], htmldata[24]].forEach((elt) => {
      cy.clickOnFieldInIframe(elt);
    });
    cy.getFormBody()
      .find('input[class="select2-search__field"]')
      .eq(4)
      .click({ force: true });
    cy.clickOnFieldInIframe(htmldata[25]);
    //Data Processing
    cy.getFormBody().find('.cf-tab-nav a[href="#cf-sec-data"]').click();
    cy.fillFieldInIframe(htmldata[26]);
    //Extended Info
    cy.getFormBody().find('.cf-tab-nav a[href="#cf-sec-extended"]').click();
    cy.clickOnFieldInIframe(htmldata[27]);
    cy.getFormBody()
      .find('input[class="select2-search__field"]')
      .eq(5)
      .click({ force: true });
    cy.clickOnFieldInIframe(htmldata[28]);
    [htmldata[29], htmldata[30], htmldata[31]].forEach((elt) => {
      cy.fillFieldInIframe(elt);
    });
    cy.getFormBody().find('#esi_icon_image').select('1');
    [htmldata[32], htmldata[33], htmldata[34]].forEach((elt) => {
      cy.fillFieldInIframe(elt);
    });
    cy.getFormBody()
      .find('input.btc.bt_success[name^="submit"]')
      .first()
      .click();
    cy.wait('@getTimeZone');
    cy.exportConfig();
  }
);

Cypress.Commands.add(
  'checkValuesOfHostGroupService',
  (name: string, body: HostGroupService) => {
    cy.waitForElementInIframe('#main-content', 'table.cl-listing-table');
    cy.waitForListingRefresh();
    cy.openListingRowForm(name)
      .find('input[name="service_description"]', { timeout: 20_000 })
      .should('be.visible');
    cy.getFormBody()
      .find('input[name="service_description"]')
      .should('have.value', name);
    cy.getFormBody()
      .find('#service_hgPars')
      .find('option:selected')
      .should('have.length', 1)
      .and('have.text', body.hostGroups);
    cy.getFormBody()
      .find('#service_template_model_stm_id')
      .find('option:selected')
      .should('have.length', 1)
      .and('have.text', body.template);
    cy.getFormBody()
      .find('#command_command_id')
      .find('option:selected')
      .should('have.length', 1)
      .and('have.text', body.checkCommand);
    cy.getFormBody().find('#macroInput_0').should('have.value', body.macroName);
    cy.getFormBody()
      .find('#macroValue_0')
      .should('have.value', body.macroValue);
    cy.getFormBody()
      .find('#timeperiod_tp_id')
      .find('option:selected')
      .should('have.length', 1)
      .and('have.text', body.checkPeriod);
    cy.getFormBody()
      .find('input[name="service_max_check_attempts"]')
      .should('have.value', body.maxCheckAttempts);
    cy.getFormBody()
      .find('input[name="service_normal_check_interval"]')
      .should('have.value', body.normalCheckInterval);
    cy.getFormBody()
      .find('input[name="service_retry_check_interval"]')
      .should('have.value', body.retryCheckInterval);
    cy.checkLegacyRadioButton('No');
    //Notifications
    cy.getFormBody().find('.cf-tab-nav a[href="#cf-sec-notif"]').click();
    cy.getFormBody()
      .find('#service_cs')
      .find('option:selected')
      .should('have.length', 1)
      .and('have.text', body.contacts);
    cy.getFormBody()
      .find('#service_cgs')
      .find('option:selected')
      .should('have.length', 1)
      .and('have.text', body.contactGroups);

    cy.getFormBody()
      .find('input[name="service_notification_interval"]')
      .should('have.value', body.notificationInterval);
    cy.getFormBody()
      .find('#timeperiod_tp_id2')
      .find('option:selected')
      .should('have.length', 1)
      .and('have.text', body.notificationPeriod);
    cy.getFormBody().find('#notifC').should('be.checked');
    cy.getFormBody().find('#notifU').should('be.checked');
    cy.getFormBody()
      .find('input[name="service_first_notification_delay"]')
      .should('have.value', body.firstNotificationDelay);
    cy.getFormBody()
      .find('input[name="service_recovery_notification_delay"]')
      .should('have.value', body.recoveryNotificationDelay);
    //Relations
    cy.getFormBody().find('.cf-tab-nav a[href="#cf-sec-relations"]').click();
    cy.getFormBody()
      .find('#service_sgs')
      .find('option:selected')
      .should('have.length', 1)
      .and('have.text', body.serviceGroups);
    cy.getFormBody()
      .find('#service_traps')
      .find('option:selected')
      .should('have.length', 1)
      .and('have.text', body.serviceTrap);
    //Data Processing
    cy.getFormBody().find('.cf-tab-nav a[href="#cf-sec-data"]').click();
    cy.getFormBody()
      .find('input[name="service_freshness_threshold"]')
      .should('have.value', body.freshnessThreshold);
    //Extended Info
    cy.getFormBody().find('.cf-tab-nav a[href="#cf-sec-extended"]').click();
    cy.getFormBody()
      .find('#service_categories')
      .find('option:selected')
      .should('have.length', 1)
      .and('have.text', body.serviceCategories);

    cy.getFormBody()
      .find('input[name="esi_notes_url"]')
      .should('have.value', body.noteUrl);
    cy.getFormBody()
      .find('input[name="esi_notes"]')
      .should('have.value', body.note);
    cy.getFormBody()
      .find('input[name="esi_action_url"]')
      .should('have.value', body.actionUrl);
    cy.getFormBody().find('#esi_icon_image').should('have.value', '1');
    cy.getFormBody()
      .find('input[name="esi_icon_image_alt"]')
      .should('have.value', body.atlIcon);
    cy.getFormBody()
      .find('input[name="geo_coords"]')
      .should('have.value', body.geoCoordsTruncated);
    cy.getFormBody()
      .find('textarea[name="service_comment"]')
      .should('have.value', body.comment);
  }
);

// ---------------------------------------------------------------------------
// Meta services commands (modernized AJAX listing + side-panel form)
// ---------------------------------------------------------------------------

Cypress.Commands.add('openMetaServicesListing', () => {
  cy.visit(PAGES.configuration.metaServicesLegacy);
  cy.wait('@getTimeZone');
  cy.waitForElementInIframe('#main-content', 'table.cl-listing-table');
  cy.getIframeBody()
    .find('#clTableBody tr')
    .should('have.length.greaterThan', 0);
});

Cypress.Commands.add('getMetaServiceSidePanelBody', () => {
  return cy
    .getIframeBody()
    .find('#cfSidePanelFrame')
    .its('0.contentDocument.body', { timeout: 20_000 })
    .should('not.be.empty')
    .then((body) => cy.wrap<JQuery<HTMLElement>>(body));
});

Cypress.Commands.add('openMetaServiceForm', (name: string) => {
  cy.getIframeBody().find('#clTableBody').contains('a', name).click();
  cy.getMetaServiceSidePanelBody()
    .find('input[name="meta_name"]', { timeout: 20_000 })
    .should('be.visible');
});

Cypress.Commands.add(
  'selectMetaServiceRowAndRunBulkAction',
  (name: string, action: string) => {
    cy.getIframeBody()
      .find('#clTableBody')
      .contains(name)
      .parents('tr')
      .find('.cl-col-picker input[type="checkbox"]')
      .click({ force: true });
    cy.getIframeBody()
      .find('select[name="o1"]')
      .invoke(
        'attr',
        'onchange',
        "javascript: { setO(this.form.elements['o1'].value); this.form.submit(); }"
      );
    cy.getIframeBody()
      .find('select[name="o1"]')
      .select(action, { force: true });
  }
);

// ---------------------------------------------------------------------------
// Service categories commands (modernized AJAX listing + side-panel form)
// ---------------------------------------------------------------------------

Cypress.Commands.add('openServiceCategoriesListing', () => {
  cy.visit(PAGES.configuration.servicesCategoriesLegacy);
  cy.wait('@getTimeZone');
  cy.waitForElementInIframe('#main-content', 'table.cl-listing-table');
  cy.getIframeBody()
    .find('#clTableBody tr')
    .should('have.length.greaterThan', 0);
});

Cypress.Commands.add('getServiceCategorySidePanelBody', () => {
  return cy
    .getIframeBody()
    .find('#cfSidePanelFrame')
    .its('0.contentDocument.body', { timeout: 20_000 })
    .should('not.be.empty')
    .then((body) => cy.wrap<JQuery<HTMLElement>>(body));
});

Cypress.Commands.add('openServiceCategoryForm', (name: string) => {
  cy.getIframeBody().find('#clTableBody').contains('a', name).click();
  cy.getServiceCategorySidePanelBody()
    .find('input[name="sc_name"]', { timeout: 20000 })
    .should('be.visible');
});

Cypress.Commands.add(
  'selectServiceCategoryRowAndRunBulkAction',
  (name: string, action: string) => {
    cy.getIframeBody()
      .find('#clTableBody')
      .contains(name)
      .parents('tr')
      // The row checkbox is visibility:hidden behind its md-checkbox label.
      .find('.cl-col-picker input[type="checkbox"]')
      .click({ force: true });
    cy.getIframeBody()
      .find('select[name="o1"]')
      .invoke(
        'attr',
        'onchange',
        "javascript: { setO(this.form.elements['o1'].value); this.form.submit(); }"
      );
    // The native o1 select is hidden (replaced by the .cl-more-actions menu);
    // the overridden onchange turns a value change into setO + submit.
    cy.getIframeBody()
      .find('select[name="o1"]')
      .select(action, { force: true });
  }
);

declare global {
  // biome-ignore lint/style/noNamespace: false positive
  namespace Cypress {
    interface Chainable {
      openMetaServicesListing(): Chainable<void>;
      getMetaServiceSidePanelBody(): Chainable<JQuery<HTMLElement>>;
      openMetaServiceForm(name: string): Chainable<void>;
      selectMetaServiceRowAndRunBulkAction(
        name: string,
        action: string
      ): Chainable<void>;
      openServiceCategoriesListing(): Chainable<void>;
      getServiceCategorySidePanelBody(): Chainable<JQuery<HTMLElement>>;
      openServiceCategoryForm(name: string): Chainable<void>;
      selectServiceCategoryRowAndRunBulkAction(
        name: string,
        action: string
      ): Chainable<void>;
      addOrUpdateVirtualMetric: (
        body: VirtualMetric,
        showGraph: boolean
      ) => Cypress.Chainable;
      checkFieldsOfVm: (body: VirtualMetric) => Cypress.Chainable;
      addMetaService: (body: MetaService) => Cypress.Chainable;
      addMetaserviceDependency: (
        body: MetaServiceDependency
      ) => Cypress.Chainable;
      updateMetaserviceDependency: (
        body: MetaServiceDependency
      ) => Cypress.Chainable;
      addServiceDependency: (body: ServiceDependency) => Cypress.Chainable;
      updateServiceDependency: (body: ServiceDependency) => Cypress.Chainable;
      addCommonDependencyFields: (body: Dependency) => Cypress.Chainable;
      updateCommonDependencyFields: (body: Dependency) => Cypress.Chainable;
      addServiceGroupDependency: (
        body: ServiceGroupDependency
      ) => Cypress.Chainable;
      updateServiceGroupDependency: (
        body: ServiceGroupDependency
      ) => Cypress.Chainable;
      createOrUpdateHostGroupService: (
        body: HostGroupService,
        isUpdate: boolean,
        htmldata: Array<HtmlElt>
      ) => Cypress.Chainable;
      checkValuesOfHostGroupService: (
        name: string,
        body: HostGroupService
      ) => Cypress.Chainable;
    }
  }
}
