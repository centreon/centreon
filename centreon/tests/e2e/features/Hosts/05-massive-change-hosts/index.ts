import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { INTERCEPTORS } from 'fixtures/shared/constants/interceptors';
import { PAGES } from 'fixtures/shared/constants/pages';

import { buildCountHostServicesFromTemplateQuery } from '../common';

const hostNames = ['host2', 'host3', 'host4'];

const templates = {
  next: { hostTemplate: 'mc-next-host-template', service: 'mc-next-service' },
  previous: {
    hostTemplate: 'mc-previous-host-template',
    service: 'mc-previous-service'
  }
};

const checkHostsProperties = (hostName) => {
  cy.getIframeBody().contains(hostName).click();
  cy.waitForElementInIframe('#main-content', 'input[name="host_name"]');
  cy.getIframeBody()
    .find('span[id="select2-host_location-container"]')
    .should('have.attr', 'title', 'Africa/Algiers');

  cy.getIframeBody()
    .find('span[id="select2-command_command_id-container"]')
    .should('have.attr', 'title', 'check_http');
  cy.getIframeBody()
    .find('input[name="host_retry_check_interval"]')
    .should('have.value', '3');
  cy.getIframeBody().find('input.btc.bt_success[name^="submit"]').eq(1).click();
  cy.wait('@getTimeZone');
};

// Unknown Examples values throw instead of silently falling back to another case.
const templateUpdateModes: Record<string, number> = {
  incremental: 0,
  replacement: 1
};
const previousServicesCounts: Record<string, number> = { kept: 1, removed: 0 };

const lookup = (values: Record<string, number>, key: string): number => {
  if (!(key in values)) {
    throw new Error(`Unexpected value "${key}"`);
  }

  return values[key];
};

const openMassChangeOnHosts = (): void => {
  cy.visit(PAGES.configuration.hostsLegacy);
  cy.wait('@getTimeZone');
  // Tick the fixture rows by name, never the header check-all: that box selects
  // every row on the page, so the mass change silently rewrote the platform's
  // own hosts (Centreon-Server included) along with the three under test.
  hostNames.forEach((name) => {
    cy.getIframeBody()
      .contains('tr', name)
      .find('div.md-checkbox.md-checkbox-inline')
      .click();
  });
  cy.getIframeBody().find('select[name="o1"]').select('Mass Change');
  cy.wait('@getTimeZone');
};

const submitMassChange = (): void => {
  cy.getIframeBody()
    .find('input.btc.bt_success[name="submitMC"]')
    .eq(1)
    .click();
  cy.wait('@getTimeZone');
};

const countHostServicesFromTemplate = (
  hostName: string,
  serviceTemplate: string
): Cypress.Chainable =>
  cy
    .requestOnDatabase({
      database: 'centreon',
      query: buildCountHostServicesFromTemplateQuery(hostName, serviceTemplate)
    })
    .then(([rows]) => cy.wrap(Number(rows[0].total), { log: false }));

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

Given('several hosts have been created with mandatory properties', () => {
  hostNames.forEach((name) => {
    cy.addHost({
      hostGroup: 'Linux-Servers',
      name,
      template: 'generic-host'
    }).applyPollerConfiguration();
  });
});

When('the user has applied "Mass Change" operation on several hosts', () => {
  openMassChangeOnHosts();
  cy.getIframeBody().find('span[id="select2-host_location-container"]').click();
  cy.getIframeBody().find('div[title="Africa/Algiers"]').click();
  cy.getIframeBody()
    .find('span[id="select2-command_command_id-container"]')
    .click();
  cy.getIframeBody().find('div[title="check_http"]').click();
  cy.getIframeBody().find('input[name="host_retry_check_interval"]').type('3');
  submitMassChange();
  cy.exportConfig();
});

Then('all the selected hosts are updated with the same values', () => {
  // Wait on the next host's listing link rather than a hard-coded host_id:
  // the ids depend on what the dataset already holds.
  checkHostsProperties('host2');
  cy.waitForElementInIframe('#main-content', 'a:contains("host3")');
  checkHostsProperties('host3');
  cy.waitForElementInIframe('#main-content', 'a:contains("host4")');
  checkHostsProperties('host4');
});

Given(
  'the selected hosts use a host template whose services are deployed',
  () => {
    Object.values(templates).forEach(({ hostTemplate, service }) => {
      cy.executeActionViaClapi({
        bodyContent: {
          action: 'ADD',
          object: 'HTPL',
          // HTPL shares the HOST signature: name;alias;address;template;instance;
          // hostgroup — all six fields are expected, empty ones included.
          values: `${hostTemplate};${hostTemplate};;;;`
        }
      });
      cy.executeActionViaClapi({
        bodyContent: {
          action: 'ADD',
          object: 'STPL',
          values: `${service};${service};`
        }
      });
      cy.executeActionViaClapi({
        bodyContent: {
          action: 'ADDHOSTTEMPLATE',
          object: 'STPL',
          values: `${service};${hostTemplate}`
        }
      });
    });
    hostNames.forEach((name) => {
      cy.executeActionViaClapi({
        bodyContent: {
          action: 'SETTEMPLATE',
          object: 'HOST',
          values: `${name};${templates.previous.hostTemplate}`
        }
      });
      cy.executeActionViaClapi({
        bodyContent: { action: 'APPLYTPL', object: 'HOST', values: name }
      });
      countHostServicesFromTemplate(name, templates.previous.service).should(
        'equal',
        1
      );
    });
  }
);

When(
  'the user applies a "Mass Change" with another host template in {string} mode',
  (mode: string) => {
    openMassChangeOnHosts();
    cy.getIframeBody().find('#template_add').click();
    cy.getIframeBody().find('span[id^="select2-tpSelect_"]').last().click();
    cy.getIframeBody()
      .find(`div[title="${templates.next.hostTemplate}"]`)
      .click();
    cy.getIframeBody()
      .find(
        `input[name="mc_mod_tplp[mc_mod_tplp]"][value="${lookup(templateUpdateModes, mode.toLowerCase())}"]`
      )
      .check({ force: true });
    // "Create Services linked to the Template too": deploys the new template's services.
    cy.getIframeBody()
      .find('input[name="dupSvTplAssoc[dupSvTplAssoc]"][value="1"]')
      .check({ force: true });
    submitMassChange();
  }
);

Then(
  'the services of the previous host template are {word} on the selected hosts',
  (outcome: string) => {
    hostNames.forEach((name) => {
      countHostServicesFromTemplate(name, templates.previous.service).should(
        'equal',
        lookup(previousServicesCounts, outcome)
      );
    });
  }
);

Then(
  'the services of the new host template are deployed on the selected hosts',
  () => {
    hostNames.forEach((name) => {
      countHostServicesFromTemplate(name, templates.next.service).should(
        'equal',
        1
      );
    });
  }
);
