/* eslint-disable cypress/unsafe-to-chain-command */
import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { checkHostsAreMonitored, checkServicesAreMonitored } from 'e2e/commons';

import hostGroups from '../../../fixtures/host-groups/host-group.json';

const services = {
  serviceCritical: {
    host: 'host3',
    name: 'service3',
    template: 'SNMP-Linux-Load-Average'
  },
  serviceOk: { host: 'host2', name: 'service_test_ok', template: 'Ping-LAN' },
  serviceWarning: {
    host: 'host2',
    name: 'service2',
    template: 'SNMP-Linux-Memory'
  }
};
const resultsToSubmit = [
  {
    host: services.serviceWarning.host,
    output: 'submit_status_2',
    service: services.serviceCritical.name,
    status: 'critical'
  },
  {
    host: services.serviceWarning.host,
    output: 'submit_status_2',
    service: services.serviceWarning.name,
    status: 'warning'
  }
];

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
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/latest/configuration/hosts/groups?page=1&limit=*'
  }).as('getGroups');
  cy.intercept({
    method: 'GET',
    url: '/centreon/include/common/webServices/rest/internal.php?object=centreon_configuration_host&action=defaultValues&target=hostgroups&field=hg_hosts&id*'
  }).as('getHosts');
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

When('a host group is configured', () => {
  cy.addHostGroup({
    name: hostGroups.default.name
  });

  cy.addHost({
    hostGroup: hostGroups.default.name,
    name: services.serviceOk.host,
    template: 'generic-host'
  })
    .addService({
      activeCheckEnabled: false,
      host: services.serviceOk.host,
      maxCheckAttempts: 1,
      name: services.serviceOk.name,
      template: services.serviceOk.template
    })
    .addService({
      activeCheckEnabled: false,
      host: services.serviceOk.host,
      maxCheckAttempts: 1,
      name: services.serviceWarning.name,
      template: services.serviceWarning.template
    })
    .addService({
      activeCheckEnabled: false,
      host: services.serviceOk.host,
      maxCheckAttempts: 1,
      name: services.serviceCritical.name,
      template: services.serviceCritical.template
    })
    .applyPollerConfiguration();

  checkHostsAreMonitored([{ name: services.serviceOk.host }]);
  checkServicesAreMonitored([
    { name: services.serviceCritical.name },
    { name: services.serviceOk.name }
  ]);
  cy.submitResults(resultsToSubmit);
});

When('the user changes some properties of the configured host group', () => {
  cy.navigateTo({
    page: 'Host Groups',
    rootItemNumber: 3,
    subMenu: 'Hosts'
  });
  cy.wait('@getGroups');
  cy.contains('p', hostGroups.default.name)
    .eq(0)
    .click();
  cy.wait('@getTimeZone')
  cy.waitUntil(
    () => {
      return cy
        .getByLabel({ label: 'Up status hosts', tag: 'a' })
        .invoke('text')
        .then((text) => {
          if (text !== '2') {
            cy.exportConfig();
          }

          return text === '2';
        });
    },
    { interval: 20000, timeout: 100000 }
  );

  cy.getIframeBody()
    .find('input[name="hg_name"]')
    .clear()
    .type(hostGroups.forTest.name);
  cy.getIframeBody()
    .find('input[name="hg_alias"]')
    .clear()
    .type(hostGroups.forTest.alias);
  cy.getIframeBody()
    .find('input[name="hg_notes"]')
    .clear()
    .type(hostGroups.forTest.notes);
  cy.getIframeBody()
    .find('input[name="hg_notes_url"]')
    .clear()
    .type(hostGroups.forTest.notes_url);
  cy.getIframeBody()
    .find('input[name="hg_action_url"]')
    .clear()
    .type(hostGroups.forTest.action_url);
  cy.getIframeBody().find('select[name="hg_icon_image"]').select('1');
  cy.getIframeBody().find('select[name="hg_map_icon_image"]').select('1');
  cy.getIframeBody()
    .find('input[name="geo_coords"]')
    .clear()
    .type(hostGroups.forTest.geo_coords);
  cy.getIframeBody()
    .find('input[name="hg_rrd_retention"]')
    .clear()
    .type(hostGroups.forTest.rrd);
  cy.getIframeBody()
    .find('textarea[name="hg_comment"]')
    .clear()
    .type(hostGroups.forTest.comment);
  cy.getIframeBody().contains('label', 'Disabled').click();

  cy.getIframeBody().find('input.btc.bt_success[name^="submit"]').eq(0).click();
  cy.wait('@getGroups')
  cy.exportConfig();
});

Then('these properties are updated', () => {
  cy.contains('p', hostGroups.forTest.name).eq(0).should('exist');
  cy.contains('p', hostGroups.forTest.name).eq(0).click();
  cy.wait('@getTimeZone');
  cy.waitForElementInIframe('#main-content', 'input[name="hg_name"]');
  cy.getIframeBody()
    .find('input[name="hg_name"]')
    .should('have.value', hostGroups.forTest.name);
  cy.getIframeBody()
    .find('input[name="hg_alias"]')
    .should('have.value', hostGroups.forTest.alias);
  cy.wait('@getHosts');
  cy.waitForElementInIframe('#main-content', `span[title="${services.serviceOk.host}"]`);
  cy.getIframeBody()
    .find('select[name="hg_hosts[]"]')
    .find('option')
    .then((options) => {
      const host2Option = options.filter((index, option) => {
        return Cypress.$(option).text() === services.serviceOk.host;
      });
      expect(host2Option.length).to.eq(1);
  });
  cy.getIframeBody()
    .find('input[name="hg_notes"]')
    .should('have.value', hostGroups.forTest.notes);
  cy.getIframeBody()
    .find('input[name="hg_notes_url"]')
    .should('have.value', hostGroups.forTest.notes_url);
  cy.getIframeBody()
    .find('input[name="hg_action_url"]')
    .should('have.value', hostGroups.forTest.action_url);
  cy.getIframeBody()
    .find('select[name="hg_icon_image"]')
    .should('have.value', '1');
  cy.getIframeBody()
    .find('select[name="hg_map_icon_image"]')
    .should('have.value', '1');
  cy.getIframeBody()
    .find('input[name="geo_coords"]')
    .should('have.value', hostGroups.forTest.geo_coords);
  cy.getIframeBody()
    .find('input[name="hg_rrd_retention"]')
    .should('have.value', hostGroups.forTest.rrd);
  cy.getIframeBody()
    .find('textarea[name="hg_comment"]')
    .should('have.value', hostGroups.forTest.comment);
  cy.checkLegacyRadioButton('Disabled');
});

When('the user duplicates the configured host group', () => {
  cy.updateHostGroupViaApi(hostGroups.forTest, hostGroups.default.name);
  cy.navigateTo({
    page: 'Host Groups',
    rootItemNumber: 3,
    subMenu: 'Hosts'
  });
  cy.wait('@getGroups');
  cy.getByTestId({testId: 'ContentCopyOutlinedIcon'}).eq(1).click();
  cy.get('[type="submit"][aria-label="Duplicate"]')
    .click();
  cy.wait('@getGroups');
});

Then('a new host group is created with identical properties', () => {
  cy.contains('p', `${hostGroups.forTest.name}_1`).eq(0).should('exist');
  cy.contains('p', `${hostGroups.forTest.name}_1`).eq(0).click();
  cy.waitForElementInIframe('#main-content', 'input[name="hg_name"]');
  cy.getIframeBody()
    .find('input[name="hg_name"]')
    .should('have.value', `${hostGroups.forTest.name}_1`);
  cy.getIframeBody()
    .find('input[name="hg_alias"]')
    .should('have.value', hostGroups.forTest.alias);
  cy.getIframeBody()
    .find('select[name="hg_hosts[]"]')
    .find('option')
    .then((options) => {
      const host2Option = options.filter((index, option) => {
        return Cypress.$(option).text() === services.serviceOk.host;
      });
      expect(host2Option.length).to.eq(1);
    });
  cy.getIframeBody()
    .find('input[name="hg_notes"]')
    .should('have.value', hostGroups.forTest.notes);
  cy.getIframeBody()
    .find('input[name="hg_notes_url"]')
    .should('have.value', hostGroups.forTest.notes_url);
  cy.getIframeBody()
    .find('input[name="hg_action_url"]')
    .should('have.value', hostGroups.forTest.action_url);
  cy.getIframeBody()
    .find('select[name="hg_icon_image"]')
    .should('have.value', '1');
  cy.getIframeBody()
    .find('select[name="hg_map_icon_image"]')
    .should('have.value', '1');
  cy.getIframeBody()
    .find('input[name="geo_coords"]')
    .should('have.value', hostGroups.forTest.geo_coords);
  cy.getIframeBody()
    .find('input[name="hg_rrd_retention"]')
    .should('have.value', hostGroups.forTest.rrd);
  cy.getIframeBody()
    .find('textarea[name="hg_comment"]')
    .should('have.value', hostGroups.forTest.comment);
  cy.checkLegacyRadioButton('Disabled');
});

When('the user deletes the configured host group', () => {
  cy.navigateTo({
    page: 'Host Groups',
    rootItemNumber: 3,
    subMenu: 'Hosts'
  });
  cy.wait('@getGroups');
  cy.getByTestId({testId: 'DeleteOutlineIcon'}).eq(1).click();
  cy.get('[type="submit"][aria-label="Delete"]')
    .click();
  cy.wait('@getGroups');
  cy.exportConfig();
});

Then(
  'the configured host group is not visible anymore on the host group page',
  () => {
    cy.contains('p', hostGroups.default.name).should('not.exist');
  }
);
