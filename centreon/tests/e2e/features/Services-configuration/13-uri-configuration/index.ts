/* eslint-disable cypress/unsafe-to-chain-command */
import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';

const link = 'https://www.google.com/';
const services = {
  serviceCritical: {
    host: "host3",
    name: "service3",
    template: "SNMP-Linux-Load-Average",
  },
  serviceOk: { host: "host2", name: "service_test_ok", template: "Ping-LAN" },
  serviceWarning: {
    host: "host2",
    name: "service2",
    template: "SNMP-Linux-Memory",
  },
};

const visitStatusDetailPage = () => {
  cy.navigateTo({
    page: 'Services',
    rootItemNumber: 1,
    subMenu: 'Status Details'
  });
  cy.wait('@getTimeZone');
};

before(() => {
  cy.startContainers();
});

beforeEach(() => {
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/internal.php?object=centreon_topology&action=navigationList'
  }).as('getNavigationList');
  cy.intercept({
    method: 'GET',
    url: '/centreon/include/common/userTimezone.php'
  }).as('getTimeZone');
});

after(() => {
  cy.stopContainers();
});

Given('a user is logged in a Centreon server', () => {
  cy.loginByTypeOfUser({
    jsonName: 'admin',
    loginViaApi: false
  });
});

Given('a configured passive host', () => {
  cy.addHost({
    passiveCheckEnabled: true,
    activeCheckEnabled: false,
    hostGroup: 'Linux-Servers',
    name: services.serviceOk.host,
    template: 'generic-host'
  })
    .applyPollerConfiguration();
}
);

Given('a configured passive service linked to the host', () => {
  cy.addService({
    passiveCheckEnabled: true,
    activeCheckEnabled: false,
    host: services.serviceOk.host,
    maxCheckAttempts: 1,
    name: services.serviceOk.name,
    template: services.serviceOk.template
  })
    .applyPollerConfiguration();
})

When(
  'the user goes to "Administration > Parameters > My Account"',
  () => {
    cy.navigateTo({
      page: 'My Account',
      rootItemNumber: 4,
      subMenu: 'Parameters'
    });
    cy.wait('@getTimeZone');
  }
);

When('the user check the option "Use deprecated pages"', () => {
  // Wait for the 'Name' field to be visible in the DOM
  cy.waitForElementInIframe('#main-content', 'input[name="contact_name"]');
  // Check the option 'Use deprecated pages'
  cy.getIframeBody().find('div.md-checkbox.md-checkbox-inline').eq(0).click();
});

When('the user clicks on "Save"', () => {
  // Click on the first 'Save' button
  cy.getIframeBody().find('input[name="submitC"]').eq(0).click();
  cy.waitForElementInIframe('#main-content', 'input[name="change"]');
});

Then('the user can access to the page "Monitoring > Status Details > Services"', () => {
  visitStatusDetailPage();
  cy.waitForElementInIframe('#main-content', 'input[name="host_search"]');
  // Chose the 'All' option as Service Status filter
  cy.getIframeBody().find('select#statusService')
    .select('All');
});

When('the user submits result for the configured service', () => {
  // Wait for the 'service_test_ok' to be visible in the DOM
  cy.waitForElementInIframe('#main-content', 'a:contains("service_test_ok")');
  // Click on the 'service_test_ok' passive service
  cy.getIframeBody().contains('a', 'service_test_ok').click();
  cy.wait('@getTimeZone');
  // Wait for the option 'Submit result for this service' to be visible in the DOM
  cy.waitForElementInIframe('#main-content', 'a:contains("Submit result for this service")');
  // Click on the 'Submit result for this service' option
  cy.getIframeBody().contains('a', 'Submit result for this service').click();
});

When('the user puts a link as "Check output"', () => {
  // Wait for the 'Check output' to be visible in the DOM
  cy.waitForElementInIframe('#main-content', 'input[name="output"]');
  // Type a value in the 'Check output' input
  cy.getIframeBody().find('input[name="output"]').type(link);
});

When('the user save the modifications', () => {
  cy.getIframeBody().find('input[name="submit"]').click();
  cy.wait('@getTimeZone');
});

Then('the status of the service is changed', () => {
  // Wait until 2 services have the status 'OK'
  cy.waitUntil(
    () => {
      return cy
        .getByLabel({ label: 'OK status services', tag: 'a' })
        .invoke('text')
        .then((text) => {
          if (text != '2') {
            cy.exportConfig();
          }
          return text === '2';
        });
    },
    { interval: 5000, timeout: 50000 }
  );
  cy.visit('/');
  visitStatusDetailPage();
  // Wait for the 'service_test_ok' to be visible in the DOM
  cy.waitForElementInIframe('#main-content', 'a:contains("service_test_ok")');
  // Click on the 'service_test_ok' passive service
  cy.getIframeBody().contains('a', 'service_test_ok').click();
  cy.wait('@getTimeZone');

  // Wait for the status to be 'OK' in the DOM
  cy.waitForElementInIframe('#main-content', 'span[class="badge service_ok"]');
  // Check that the 'Service Status' of the passive service is OK
  cy.getIframeBody().find('span[class="badge service_ok"]').should('exist');
});

When('the user clicks on the link in the "status information"', () => {
  // Wait for the 'Status information' to be visible in the DOM
  cy.waitForElementInIframe('#main-content', 'a[class="linkified"]');
});

Then('a new tab is open to the link', () => {
  cy.getIframeBody().find('a.linkified').then($link => {
    // Get the href of the link in the 'Status information' field
    const linkUrl = $link.prop('href');
    // Check that the href equals the already setted link 
    expect(linkUrl).to.equal(link);
    // Visit the link
    cy.visit(linkUrl);
    // Check that tab is opened
    cy.url().should('eq', linkUrl);
  });
});

When('the user visits "Monitoring > Status Details > Services"', () => {
  visitStatusDetailPage();
});

When('the user adds a comment to a configured passive service', () => {
  cy.waitForElementInIframe('#main-content', 'input[name="host_search"]');
  // Chose the 'All' option as Service Status filter
  cy.getIframeBody().find('select#statusService')
    .select('All');
  // Wait for the 'service_test_ok' to be visible in the DOM
  cy.waitForElementInIframe('#main-content', 'a:contains("service_test_ok")');
  // Click on the 'service_test_ok' passive service
  cy.getIframeBody().contains('a', 'service_test_ok').click();
  cy.wait('@getTimeZone');
  // Wait for the option 'Add a comment for this service' to be visible in the DOM
  cy.waitForElementInIframe('#main-content', 'a:contains("Add a comment for this service")');
  // Click on the 'Add a comment for this service' option
  cy.getIframeBody().contains('a', 'Add a comment for this service').click();
  // Wait for the 'Comments' field to be visible in the DOM
  cy.waitForElementInIframe('#main-content', 'textarea[name="comment"]');
  // Type a value in the 'Comments' textarea
  cy.getIframeBody().find('textarea[name="comment"]').type(link);
  // Click on the 'Save' button
  cy.getIframeBody().find('input[name="submitA"]').click();
  cy.wait('@getTimeZone');
});

Then('the comment is displayed on "Monitoring > Downtimes > Comments" listing page', () => {
  // Check that the user is redirected to the "Monitoring > Downtimes > Comments " listing page
  cy.url().should('eq', 'http://127.0.0.1:4000/centreon/main.php?p=21002');
  cy.waitUntil(
    () => {
      cy.waitForElementInIframe('#main-content', 'table.ListTable');
      return cy.getIframeBody()
        .find('table.ListTable')
        .eq(0)
        .find('tbody tr')
        .then(($elts) => {
          const count = $elts.length;
          if (count == 1) {
            // Refresh the page until the added comment is displayed on the listing page
            cy.reload();
            cy.wait('@getTimeZone')
          }
          return count > 1;
        });
    },
    { interval: 5000, timeout: 50000 }
  );
  // Check that the comment is added to the listing page
  cy.waitForElementInIframe('#main-content', 'a:contains("service_test_ok")');
});

When('the user clicks on the link', () => {
  // Check that the link is added as comments
  cy.waitForElementInIframe('#main-content', `a:contains("${link}")`);
});

