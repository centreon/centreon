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
    url: '/centreon/api/latest/configuration/hosts?page=1*'
  }).as('getHosts');
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/latest/configuration/hosts/groups/*'
  }).as('getGroupDetails');
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/latest/configuration/icons?page=*'
  }).as('getIcons');
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
  cy.waitUntil(
    () => {
      return cy
        .getByLabel({ label: 'Up status hosts', tag: 'a' })
        .invoke('text')
        .then((text) => {
          if (text != '2') {
            cy.exportConfig();
          }

          return text === '2';
        });
    },
    { interval: 10000, timeout: 600000 }
  );
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
  cy.wait('@getGroupDetails')
  cy.contains('p', 'Modify a host group').should('be.visible');
  // Update Name field 
  cy.getByTestId({ testId: 'Name' }).eq(1).clear().type(hostGroups.forTest.name);
  // Update Alias field
  cy.getByTestId({ testId: 'Alias' }).eq(1).clear().type(hostGroups.forTest.alias);
  // Update Group members hosts field
  cy.get('#Selecthosts').click();
  cy.wait('@getHosts');
  cy.contains('Centreon-Server').click();
  // Update geo coordinates for MAP
  cy.getByTestId({ testId: 'Geographic coordinates for MAP'}).eq(1).clear().type(hostGroups.forTest.geo_coords);
  // Update icon
  cy.getByTestId({ testId: 'ArrowDropDownIcon'}).eq(2).click();
  cy.wait('@getIcons');
  cy.contains('p', 'centreon').click();
  // Update Comment field
  cy.getByTestId({testId: 'Comment'}).eq(1).clear().type(hostGroups.forTest.comment);
  // Save the form
  cy.getByTestId({ testId: 'submit' }).click();
  cy.wait('@getGroups')
  cy.exportConfig();
});

Then('these properties are updated', () => {
  cy.contains('p', hostGroups.forTest.name).eq(0).should('exist');
  cy.contains('p', hostGroups.forTest.name).eq(0).click();
  cy.wait('@getGroupDetails');
  cy.getByTestId({ testId: 'Name' })
    .eq(1)
    .should('have.value', hostGroups.forTest.name);
  cy.getByTestId({ testId: 'Alias' })
    .eq(1)
    .should('have.value', hostGroups.forTest.alias);
  // check values of hosts members
  cy.contains('span', 'host2').should('be.visible');
  cy.contains('span', 'Centreon-Server').should('be.visible');
  cy.getByTestId({ testId: 'Geographic coordinates for MAP'})
    .eq(1)
    .should('have.value', hostGroups.forTest.geo_coords);
  // Check value of the icon
  cy.get('img[alt="logo-centreon-colors.png"]').should('be.visible');
  cy.getByTestId({testId: 'Comment'})
    .eq(1)
    .should('have.value', hostGroups.forTest.comment);
});

When('the user duplicates the configured host group', () => {
  cy.updateHostGroupViaApi(hostGroups.forDuplicate, hostGroups.default.name);
  cy.navigateTo({
    page: 'Host Groups',
    rootItemNumber: 3,
    subMenu: 'Hosts'
  });
  cy.wait('@getGroups');
  cy.getByTestId({ testId: 'ContentCopyOutlinedIcon' }).eq(1).click();
  cy.get('[type="submit"][aria-label="Duplicate"]')
    .click();
  cy.wait('@getGroups');
});

Then('a new host group is created with identical properties', () => {
  cy.contains('p', `${hostGroups.forDuplicate.name}_1`).should('exist');
  cy.contains('p', `${hostGroups.forDuplicate.name}_1`).click();
  cy.getByTestId({ testId: 'Name' })
    .eq(1)
    .should('have.value', `${hostGroups.forDuplicate.name}_1`);
  cy.getByTestId({ testId: 'Alias' })
    .eq(1)
    .should('have.value', hostGroups.forDuplicate.alias);
  // check values of hosts members
  cy.contains('span', 'host2').should('be.visible');
  cy.getByTestId({ testId: 'Geographic coordinates for MAP'})
    .eq(1)
    .should('have.value', hostGroups.forDuplicate.geo_coords);
  // Check value of the icon
  cy.get('img[alt="logo-centreon-colors.png"]').should('be.visible');
  cy.getByTestId({testId: 'Comment'})
    .eq(1)
    .should('have.value', hostGroups.forTest.comment);
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
