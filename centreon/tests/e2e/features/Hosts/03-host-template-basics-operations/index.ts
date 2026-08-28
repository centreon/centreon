import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { INTERCEPTORS } from 'fixtures/shared/constants/interceptors';
import { PAGES } from 'fixtures/shared/constants/pages';

import hostTemplates from '../../../fixtures/host-templates/host-template.json';

const checkFirstHostTemplateFromListing = () => {
  cy.visit(PAGES.configuration.hostsTemplatesLegacy);
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
    url: INTERCEPTORS.api.navigation_list
  }).as('getNavigationList');
  cy.intercept({
    method: 'GET',
    url: INTERCEPTORS.pages.time_zone
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
  cy.visit(PAGES.configuration.hostsTemplatesLegacy);
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

const dupSource = 'dup-src-template';
const dupSibling = 'dup-sibling-template';
const sharedServices = ['dup-svc-a', 'dup-svc-b'];

const templateRelationCount = (name: string): Cypress.Chainable =>
  cy
    .requestOnDatabase({
      database: 'centreon',
      query:
        'SELECT COUNT(*) AS total FROM host_service_relation hsr ' +
        'JOIN host h ON h.host_id = hsr.host_host_id ' +
        `WHERE h.host_name = '${name}'`
    })
    .then(([rows]) => cy.wrap(Number(rows[0].total), { log: false }));

const duplicateTemplateRow = (name: string): void => {
  cy.visit(PAGES.configuration.hostsTemplatesLegacy);
  cy.wait('@getTimeZone');
  // Exact-text match: a copy's name contains its source's name, so a substring
  // lookup would tick whichever of the two the listing renders first.
  cy.getIframeBody()
    .find('a')
    .filter((_, el) => el.textContent?.trim() === name)
    .closest('tr')
    .find('div.md-checkbox.md-checkbox-inline')
    .click();
  cy.getIframeBody()
    .find('select')
    .eq(0)
    .invoke(
      'attr',
      'onchange',
      "javascript: { setO(this.form.elements['o1'].value); submit(); }"
    );
  cy.getIframeBody().find('select').eq(0).select('Duplicate');
  cy.wait('@getTimeZone');
};

Given('a host template with shared service templates is configured', () => {
  // Two host templates sharing the same service templates: the sharing is what
  // made the old duplication relate each service twice on the copy.
  [dupSource, dupSibling].forEach((name) => {
    cy.executeActionViaClapi({
      bodyContent: { action: 'ADD', object: 'HTPL', values: `${name};${name};` }
    });
  });
  sharedServices.forEach((svc) => {
    cy.executeActionViaClapi({
      bodyContent: { action: 'ADD', object: 'STPL', values: `${svc};${svc};` }
    });
    [dupSource, dupSibling].forEach((tpl) => {
      cy.executeActionViaClapi({
        bodyContent: {
          action: 'ADDHOSTTEMPLATE',
          object: 'STPL',
          values: `${svc};${tpl}`
        }
      });
    });
  });
});

When('the user duplicates that host template and its copy', () => {
  duplicateTemplateRow(dupSource);
  duplicateTemplateRow(`${dupSource}_1`);
});

Then('each copy carries exactly the service templates of its source', () => {
  templateRelationCount(dupSource).then((source) => {
    expect(source, 'source relation count').to.equal(sharedServices.length);
    templateRelationCount(`${dupSource}_1`).then((firstCopy) => {
      expect(firstCopy, 'first copy relation count').to.equal(source);
    });
    templateRelationCount(`${dupSource}_1_1`).then((secondCopy) => {
      expect(secondCopy, 'copy-of-copy relation count').to.equal(source);
    });
  });
});

Given('a host template already carries the name the copy would take', () => {
  cy.executeActionViaClapi({
    bodyContent: {
      action: 'ADD',
      object: 'HTPL',
      values: `${dupSource}_1;${dupSource}_1;`
    }
  });
});

When('the user duplicates that host template', () => {
  duplicateTemplateRow(dupSource);
});

Then('no duplicate host template row was created', () => {
  cy.requestOnDatabase({
    database: 'centreon',
    query: `SELECT COUNT(*) AS total FROM host WHERE host_name = '${dupSource}_1'`
  }).then(([rows]) => {
    expect(Number(rows[0].total), 'rows carrying the taken name').to.equal(1);
  });
  // The skipped copy must not have attached relations to the name holder either.
  templateRelationCount(`${dupSource}_1`).then((count) => {
    expect(count, 'relations on the pre-existing template').to.equal(0);
  });
});
