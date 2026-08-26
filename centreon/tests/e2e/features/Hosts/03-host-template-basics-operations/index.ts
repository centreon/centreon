import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { INTERCEPTORS } from 'fixtures/shared/constants/interceptors';

import hostTemplates from '../../../fixtures/host-templates/host-template.json';
import {
  formSections,
  formSelectors,
  getListingRow,
  listingSelectors,
  searchInListing,
  segmentedButton,
  segmentedRadio,
  waitForListingRefresh
} from '../common';

const secondTemplateName = 'host-template-second';
// Active Checks is the switch the fixture flips from 0 (default template) to
// 1 (edited template).
const activeChecksRadio = 'host_active_checks_enabled';
// Plain text fields on purpose: they are present in every form mode, so the same
// selector reads back the saved value from the regular edit form.
const massChangedMaxCheckAttempts = '7';
const massChangedRetryInterval = '3';

// A service template of this spec's own: the assertion counts the links to this
// exact description, and a shipped one could already be linked elsewhere.
const serviceTemplateName = 'host-template-service';

const createServiceTemplate = (): void => {
  cy.addServiceTemplate({
    description: serviceTemplateName,
    name: serviceTemplateName,
    template: 'generic-service'
  });
};

const linkServiceTemplate = (hostTemplateName: string): void => {
  cy.requestOnDatabase({
    database: 'centreon',
    query: `INSERT INTO host_service_relation
              (hostgroup_hg_id, host_host_id, servicegroup_sg_id, service_service_id)
            SELECT NULL, host.host_id, NULL, service.service_id
            FROM host, service
            WHERE host.host_name = '${hostTemplateName}'
              AND service.service_description = '${serviceTemplateName}'`
  }).then(([result]) => {
    // An INSERT ... SELECT that matches nothing is an OK packet with no row, so
    // a mistyped name would otherwise fail a later step for an unrelated reason.
    if (!result || result.affectedRows === 0) {
      throw new Error(
        `Host template not found for template name ${hostTemplateName}`
      );
    }
  });
};

const expectServiceTemplateLinks = (
  hostTemplateName: string,
  expected: number
): void => {
  cy.requestOnDatabase({
    database: 'centreon',
    query: `SELECT COUNT(*) AS link_count
            FROM host_service_relation
            INNER JOIN host ON host.host_id = host_service_relation.host_host_id
            INNER JOIN service
              ON service.service_id = host_service_relation.service_service_id
            WHERE host.host_name = '${hostTemplateName}'
              AND service.service_description = '${serviceTemplateName}'`
  }).then(([rows]) => {
    expect(
      Number(rows[0].link_count),
      `service template links of ${hostTemplateName}`
    ).to.eq(expected);
  });
};

const createHostTemplate = (body: Record<string, unknown>): void => {
  cy.request({
    body,
    headers: {
      'Content-Type': 'application/json'
    },
    method: 'POST',
    url: '/centreon/api/beta/configuration/hosts/templates'
  }).then((response) => {
    expect(response.status).to.eq(201);
  });
};

beforeEach(() => {
  cy.startContainers();
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
  createHostTemplate(hostTemplates.defaultHostTemplate);
});

Given('the configured host template is locked', () => {
  // Nothing in the platform install ships a locked template, so make one.
  cy.lockHostTemplateWithSql(hostTemplates.defaultHostTemplate.name);
});

Given('a second host template is configured', () => {
  createHostTemplate({
    ...hostTemplates.defaultHostTemplate,
    alias: secondTemplateName,
    name: secondTemplateName
  });
});

When('the user changes the properties of the configured host template', () => {
  cy.openHostTemplatesListing();
  cy.openListingRowForm(hostTemplates.defaultHostTemplate.name);

  cy.getSidePanelBody()
    .find('input[name="host_name"]')
    .clear()
    .type(hostTemplates.hostTemplate.name);
  cy.getSidePanelBody()
    .find('input[name="host_alias"]')
    .clear()
    .type(hostTemplates.hostTemplate.alias);
  cy.getSidePanelBody()
    .find('input[name="host_snmp_community"]')
    .clear()
    .type(hostTemplates.hostTemplate.snmp_community);
  cy.getSidePanelBody()
    .find('select[name="host_snmp_version"]')
    .select(hostTemplates.hostTemplate.snmp_version);

  cy.getSidePanelBody()
    .find('span[id="select2-host_location-container"]')
    .click();
  cy.getSidePanelBody().find('div[title="Africa/Algiers"]').click();

  cy.getSidePanelBody()
    .find('span[id="select2-command_command_id-container"]')
    .click();
  cy.getSidePanelBody().find('div[title="check_http"]').click();

  // Check Period, the three interval fields and the Active Checks switch all
  // live in Scheduling, which the form ships collapsed.
  cy.expandFormSection(formSections.scheduling);

  cy.getSidePanelBody()
    .find('span[id="select2-timeperiod_tp_id-container"]')
    .click();
  cy.getSidePanelBody().find('div[title="none"]').click();

  cy.getSidePanelBody()
    .find('input[name="host_max_check_attempts"]')
    .clear()
    .type(hostTemplates.hostTemplate.max_check_attempts.toString());

  cy.getSidePanelBody()
    .find('input[name="host_check_interval"]')
    .clear()
    .type(hostTemplates.hostTemplate.normal_check_interval.toString());

  cy.getSidePanelBody()
    .find('input[name="host_retry_check_interval"]')
    .clear()
    .type(hostTemplates.hostTemplate.retry_check_interval.toString());

  // Yes/No/Default is a button group now, driving the hidden radios the form
  // still submits — hence clicking the button and asserting the radio.
  cy.getSidePanelBody().find(segmentedButton(activeChecksRadio, '1')).click();

  cy.getSidePanelBody().find(formSelectors.saveButton).first().click();
  cy.wait('@getTimeZone');
  cy.exportConfig();
});

Then('the properties are updated', () => {
  cy.getIframeBody()
    .find(listingSelectors.tableBody)
    .contains(hostTemplates.hostTemplate.name)
    .should('exist');
  cy.openListingRowForm(hostTemplates.hostTemplate.name);

  cy.getSidePanelBody()
    .find('input[name="host_name"]')
    .should('have.value', hostTemplates.hostTemplate.name);

  cy.getSidePanelBody()
    .find('input[name="host_alias"]')
    .should('have.value', hostTemplates.hostTemplate.alias);

  cy.getSidePanelBody()
    .find('select[name="host_snmp_version"]')
    .should('have.value', '3');

  cy.getSidePanelBody()
    .find('span[id="select2-host_location-container"]')
    .should('have.attr', 'title', 'Africa/Algiers');

  cy.getSidePanelBody()
    .find('span[id="select2-command_command_id-container"]')
    .should('have.attr', 'title', 'check_http');

  cy.getSidePanelBody()
    .find('span[id="select2-timeperiod_tp_id-container"]')
    .should('have.attr', 'title', 'none');

  cy.getSidePanelBody()
    .find('input[name="host_max_check_attempts"]')
    .should('have.value', hostTemplates.hostTemplate.max_check_attempts);

  cy.getSidePanelBody()
    .find('input[name="host_check_interval"]')
    .should('have.value', hostTemplates.hostTemplate.normal_check_interval);

  cy.getSidePanelBody()
    .find('input[name="host_retry_check_interval"]')
    .should('have.value', hostTemplates.hostTemplate.retry_check_interval);

  // The hidden radio behind the switch is what the form submits.
  cy.getSidePanelBody()
    .find(segmentedRadio(activeChecksRadio, '1'))
    .should('be.checked');
});

// Sharing the service template is what arms the defect: the copy then used to
// earn a relation from the shared-service branch on top of the template one.
Given('both host templates carry the same service template', () => {
  createServiceTemplate();
  linkServiceTemplate(hostTemplates.defaultHostTemplate.name);
  linkServiceTemplate(secondTemplateName);
});

Then('the source and its copy link the service template exactly once', () => {
  const sourceName = hostTemplates.defaultHostTemplate.name;

  expectServiceTemplateLinks(sourceName, 1);
  expectServiceTemplateLinks(`${sourceName}_1`, 1);
});

When('the user duplicates the configured host template', () => {
  cy.openHostTemplatesListing();
  cy.runListingBulkAction(
    hostTemplates.defaultHostTemplate.name,
    'Duplicate',
    'Duplicate'
  );
  cy.wait('@getTimeZone');
  cy.exportConfig();
});

Then('a new host template is created with identical properties', () => {
  const duplicateName = `${hostTemplates.defaultHostTemplate.name}_1`;

  cy.getIframeBody()
    .find(listingSelectors.tableBody)
    .contains(duplicateName)
    .should('exist');
  cy.openListingRowForm(duplicateName);

  cy.getSidePanelBody()
    .find('input[name="host_name"]')
    .should('have.value', duplicateName);

  cy.getSidePanelBody()
    .find('input[name="host_alias"]')
    .should('have.value', hostTemplates.defaultHostTemplate.alias);

  cy.getSidePanelBody()
    .find('select[name="host_snmp_version"]')
    .should('have.value', hostTemplates.defaultHostTemplate.snmp_version);

  cy.getSidePanelBody()
    .find('span[id="select2-host_location-container"]')
    .should('have.attr', 'title', 'Africa/Abidjan');

  cy.getSidePanelBody()
    .find('span[id="select2-command_command_id-container"]')
    .should('have.attr', 'title', 'check_host_alive');

  cy.getSidePanelBody()
    .find('span[id="select2-timeperiod_tp_id-container"]')
    .should('have.attr', 'title', '24x7');

  cy.getSidePanelBody()
    .find('input[name="host_max_check_attempts"]')
    .should('have.value', hostTemplates.defaultHostTemplate.max_check_attempts);

  cy.getSidePanelBody()
    .find('input[name="host_check_interval"]')
    .should(
      'have.value',
      hostTemplates.defaultHostTemplate.normal_check_interval
    );

  cy.getSidePanelBody()
    .find('input[name="host_retry_check_interval"]')
    .should(
      'have.value',
      hostTemplates.defaultHostTemplate.retry_check_interval
    );

  cy.getSidePanelBody()
    .find(segmentedRadio(activeChecksRadio, '0'))
    .should('be.checked');
});

When('the user deletes the configured host template', () => {
  cy.openHostTemplatesListing();
  cy.runListingBulkAction(
    hostTemplates.defaultHostTemplate.name,
    'Delete',
    'Delete'
  );
  cy.wait('@getTimeZone');
  cy.exportConfig();
});

Then(
  'the deleted host template is not visible anymore on the host template page',
  () => {
    // Reopen the listing: the delete submitted the page, and openHostTemplatesListing
    // is what proves the rows are the fetched ones before asserting an absence.
    cy.openHostTemplatesListing();
    cy.getIframeBody()
      .find(listingSelectors.tableBody)
      .should('not.contain', hostTemplates.defaultHostTemplate.name);
  }
);

When('the user applies a mass change on both host templates', () => {
  cy.openHostTemplatesListing();
  cy.tickListingRow(hostTemplates.defaultHostTemplate.name);
  cy.tickListingRow(secondTemplateName);

  cy.openListingMassChange();

  // Both fields live in Scheduling, which the form ships collapsed.
  cy.expandFormSection(formSections.scheduling);
  cy.getSidePanelBody()
    .find('input[name="host_max_check_attempts"]')
    .clear()
    .type(massChangedMaxCheckAttempts);
  cy.getSidePanelBody()
    .find('input[name="host_retry_check_interval"]')
    .clear()
    .type(massChangedRetryInterval);

  cy.getSidePanelBody().find(formSelectors.massChangeSubmit).first().click();
  cy.wait('@getTimeZone');
  cy.exportConfig();
});

Then('both host templates carry the mass changed values', () => {
  [hostTemplates.defaultHostTemplate.name, secondTemplateName].forEach(
    (name) => {
      cy.openHostTemplatesListing();
      cy.openListingRowForm(name);

      cy.getSidePanelBody()
        .find('input[name="host_max_check_attempts"]')
        .should('have.value', massChangedMaxCheckAttempts);
      cy.getSidePanelBody()
        .find('input[name="host_retry_check_interval"]')
        .should('have.value', massChangedRetryInterval);
    }
  );
});

// ---------------------------------------------------------------------------
// Modernized listing
// ---------------------------------------------------------------------------

When('the user opens the host templates listing', () => {
  cy.openHostTemplatesListing();
});

Then(
  'the AJAX listing table is displayed with the configured host template',
  () => {
    cy.getIframeBody().find(listingSelectors.table).should('exist');
    cy.getIframeBody()
      .find(listingSelectors.tableBody)
      .contains(hostTemplates.defaultHostTemplate.name)
      .should('exist');
  }
);

When('the user searches the host templates for the first one', () => {
  searchInListing(
    hostTemplates.defaultHostTemplate.name,
    '@getHostTemplateListing'
  );
});

Then('only the matching host template is displayed', () => {
  cy.getIframeBody()
    .find(listingSelectors.tableBody)
    .contains(hostTemplates.defaultHostTemplate.name)
    .should('exist');
  cy.getIframeBody()
    .find(listingSelectors.tableBody)
    .should('not.contain', secondTemplateName);
});

When('the user asks for the locked host templates', () => {
  cy.getIframeBody().find(listingSelectors.advancedToggle).click();
  cy.getIframeBody().find('#displayLocked').check({ force: true });
  cy.getIframeBody().find(listingSelectors.advancedSearch).click();
  waitForListingRefresh('@getHostTemplateListing');
});

Then('the locked host template cannot be selected nor duplicated', () => {
  // A locked row keeps its checkbox disabled and renders no duplication input.
  getListingRow(hostTemplates.defaultHostTemplate.name)
    .find(listingSelectors.rowCheckbox)
    .should('be.disabled');
  getListingRow(hostTemplates.defaultHostTemplate.name)
    .find(listingSelectors.duplicateInput)
    .should('not.exist');
});

Then(
  'the pagination information shows the total count of host templates',
  () => {
    cy.getIframeBody()
      .find(listingSelectors.pageInfo)
      .invoke('text')
      .should('match', /\d+-\d+ of \d+/);
  }
);

When('the user sets the rows per page to 10', () => {
  cy.getIframeBody().find(listingSelectors.limitSelect).select('10');
  waitForListingRefresh('@getHostTemplateListing');
});

Then('at most 10 host template rows are displayed', () => {
  cy.getIframeBody()
    .find(`${listingSelectors.tableBody} tr`)
    .should('have.length.at.most', 10);
});

When(
  'the user opens the host template form and comes back to the listing',
  () => {
    cy.openListingRowForm(hostTemplates.defaultHostTemplate.name);
    cy.openHostTemplatesListing();
  }
);

Then('the host templates search field still contains the search term', () => {
  cy.getIframeBody()
    .find(listingSelectors.searchInput)
    .should('have.value', hostTemplates.defaultHostTemplate.name);
});
