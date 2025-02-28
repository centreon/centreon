/* eslint-disable cypress/unsafe-to-chain-command */
import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { configureKB, getMediaWikiContainerPort } from '../common';

const kb_url = 'http://mediawiki';
const kb_account = 'WikiSysop';
const kb_password = 'centreon';
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

before(() => {
  cy.startContainers({ profiles: ['mediawiki'] });
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

Given('an admin user is logged in a Centreon server with MediaWiki installed', () => {
  cy.loginByTypeOfUser({
    jsonName: 'admin',
    loginViaApi: false
  });
  // Configure a Knowledge Base
  configureKB(kb_url, kb_account, kb_password);
});

Given('a host is configured', () => {
  cy.addHost({
    activeCheckEnabled: false,
    passiveCheckEnabled: true,
    hostGroup: "Linux-Servers",
    name: services.serviceOk.host,
    template: "generic-host",
  })
    .applyPollerConfiguration();
}
);

When('the user adds a procedure concerning this host in MediaWiki', () => {
  cy.navigateTo({
    page: 'Hosts',
    rootItemNumber: 3,
    subMenu: 'Knowledge Base'
  });
  cy.wait('@getTimeZone');
  // Wait until the 'Host' search field is visible in the DOM page
  cy.waitForElementInIframe('#main-content', 'input[name="searchHost"]');
  cy.getIframeBody().find('a[target="_blank"]').eq(5).then($elt => {
  // Get the href of the mediawiki link of the host
  const linkUrl = $elt.prop('href');
  // Check that the href is as expected 
  expect(linkUrl).to.contains(`Host_:_${services.serviceOk.host}`);
  // Get MediaWiki container port and visit the link
  getMediaWikiContainerPort().then((port) => {
    // Now that you have the port, visit the URL
    cy.visit(`http://localhost:${port}/index.php?title=Host_:_${services.serviceOk.host}&action=edit`);
    // Type a wiki 
    cy.get('#wpTextbox1') 
      .type('add wiki host page'); 
    cy.get('#wpSave').click();
    //cy.wait(10000);
    cy.execInContainer({
      command: `php /usr/share/centreon/cron/centKnowledgeSynchronizer.php`,
      name: 'web'
    });
    cy.visit('/');
    cy.exportConfig();
  });
  });
});

Then('a link towards this host procedure is available in the configuration', () => {
  cy.navigateTo({
    page: 'Hosts',
    rootItemNumber: 3,
    subMenu: 'Hosts'
  });
  cy.wait('@getTimeZone');
  // Wait until the 'Name' search field is visible in the DOM page
  cy.waitForElementInIframe('#main-content', 'input[name="searchH"]');
  // Click on the host to open its details
  cy.getIframeBody().contains('a', services.serviceOk.host).click();
  // Wait until the tab 'Host Extended Infos' is visible
  cy.waitForElementInIframe('#main-content', 'a:contains("Host Extended Infos")');
  cy.getIframeBody().contains('a', 'Host Extended Infos').click();
  // Click outside the form
  cy.get('body').click(0, 0);
  // Check that the 'Note URL' contains the url of the mediawiki
  cy.getIframeBody()
    .find('input[name="ehi_notes_url"]')
    .should('have.value', './include/configuration/configKnowledge/proxy/proxy.php?host_name=$HOSTNAME$');
});

Given('a service is configured', () => {
});

When('the user adds a procedure concerning this service in MediaWiki', () => {

});

Then('a link towards this service procedure is available in configuration', () => {
});

Given('the knowledge configuration page with procedure', () => {
  
});

When('the user deletes a wiki procedure', () => {

});

Then('the page is deleted and the option disappear', () => {

});
