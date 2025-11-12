import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { checkHostsAreMonitored, checkServicesAreMonitored } from 'commons';

let hostName = '';
let hostWithGeoCoords = 'New-Host-Name-for-geo';
const hostAddress = '127.0.0.1';
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

const fillGeographicCoordinates = (value: string) => {
  //Navigate to the Host Extended Infos tab
  cy.getIframeBody().contains('a', 'Host Extended Infos').click();
  // Click outside the form
  cy.get('body').click(0, 0);
  cy.getIframeBody().find('input[name="geo_coords"]').type(value);
};

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

When('a host is configured', () => {
  cy.addHost({
    hostGroup: 'Linux-Servers',
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

When('the admin changes the name of a host to {string}', (name: string) => {
  hostName = name;
  cy.visitHostsListingPage(3);
  cy.getIframeBody().contains(services.serviceOk.host).click({
    force: true
  });
  cy.waitForElementInIframe('#main-content', 'input[name="host_name"]');
  cy.getIframeBody().find('input[name="host_name"]').clear().type(hostName);
  cy.getIframeBody().find('input[name="host_alias"]').clear().type(hostName);
  cy.getIframeBody().find('input.btc.bt_success[name^="submit"]').eq(0).click();
  cy.wait('@getTimeZone');
});

Then(
  'the updated name should be updated on the host page to {string}',
  (name: string) => {
    hostName = name;
    cy.exportConfig();
    cy.getIframeBody().contains(hostName).should('exist');
  }
);

When('the admin duplicates a host', () => {
  cy.visitHostsListingPage(3);
  cy.getIframeBody().find('div.md-checkbox.md-checkbox-inline').eq(2).click();
  cy.getIframeBody()
    .find('select')
    .eq(4)
    .invoke(
      'attr',
      'onchange',
      "javascript: { setO(this.form.elements['o1'].value); this.form.submit(); }"
    );
  cy.getIframeBody().find('select').eq(4).select('Duplicate');
  cy.wait('@getTimeZone');
  cy.exportConfig();
});

Then('a new host is created with identical fields', () => {
  cy.getIframeBody().contains(`${services.serviceOk.host}_1`).should('exist');
});

When('the admin deletes the host', () => {
  cy.visitHostsListingPage(3);
  cy.getIframeBody().find('div.md-checkbox.md-checkbox-inline').eq(2).click();
  cy.getIframeBody()
    .find('select')
    .eq(4)
    .invoke(
      'attr',
      'onchange',
      "javascript: { setO(this.form.elements['o1'].value); this.form.submit(); }"
    );
  cy.getIframeBody().find('select').eq(4).select('Delete');
  cy.wait('@getTimeZone');
  cy.exportConfig();
});

Then('the host is not visible in the host list', () => {
  cy.getIframeBody().contains(services.serviceOk.host).should('not.exist');
});

Given('the admin is on the hosts listing page', () => {
  cy.visitHostsListingPage(3);
});

Given('the admin fills in the required fields to create a host', () => {
  cy.waitForElementInIframe('#main-content', 'input[name="searchH"]');
  cy.getIframeBody().contains('a', 'Add').click();
  cy.waitForElementInIframe('#main-content', 'input[name="host_name"]');
  cy.getIframeBody()
    .find('input[name="host_name"]')
    .clear()
    .type(hostWithGeoCoords);
  cy.getIframeBody()
    .find('input[name="host_alias"]')
    .clear()
    .type(hostWithGeoCoords);
  cy.getIframeBody()
    .find('input[name="host_address"]')
    .clear()
    .type(hostAddress);
});

When('the admin saves the host', () => {
  cy.getIframeBody().find('input.btc.bt_success[name^="submit"]').eq(0).click();
  cy.wait('@getTimeZone');
});

Then('the host is successfully created', () => {
  cy.wait('@getTimeZone');
  cy.getIframeBody().contains(hostWithGeoCoords).should('be.visible');
});

Then('the geo-coordinates value is truncated {string}', (value: string) => {
  cy.getIframeBody().contains(hostWithGeoCoords).eq(0).click();
  cy.waitForElementInIframe('#main-content', 'input[name="host_name"]');
  //Navigate to the Host Extended Infos tab
  cy.getIframeBody().contains('a', 'Host Extended Infos').click();
  // Click outside the form
  cy.get('body').click(0, 0);
  // Check that the setted geo coords value has been truncated
  cy.getIframeBody()
    .find('input[name="geo_coords"]')
    .should('have.value', value);
});

Given(
  'the admin enters this non valid value {string} in the geo-coordinates field',
  (value: string) => {
    fillGeographicCoordinates(value);
  }
);

Given('a host is already configured', () => {
  hostWithGeoCoords = services.serviceOk.host;
  cy.getIframeBody().contains('a', hostWithGeoCoords).should('be.visible');
});

When('the admin opens the edit form on this host', () => {
  cy.getIframeBody().contains('a', hostWithGeoCoords).click();
  cy.waitForElementInIframe('#main-content', 'input[name="host_name"]');
});
