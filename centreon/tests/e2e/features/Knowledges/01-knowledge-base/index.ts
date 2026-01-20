import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { PAGES } from 'fixtures/shared/constants/pages';
import { configureKb, getMediaWikiContainerPort } from '../common';

const kbUrl = 'http://mediawiki';
const kbAccount = 'WikiSysop';
const kbPassword = 'centreon';
const serviceNoteUrl =
  './include/configuration/configKnowledge/proxy/proxy.php?host_name=$HOSTNAME$&service_description=$SERVICEDESC$';
const hostNoteUrl =
  './include/configuration/configKnowledge/proxy/proxy.php?host_name=$HOSTNAME$';
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

Given(
  'an admin user is logged in a Centreon server with MediaWiki installed',
  () => {
    cy.loginByTypeOfUser({
      jsonName: 'admin',
      loginViaApi: false
    });
    // Configure a Knowledge Base
    configureKb(kbUrl, kbAccount, kbPassword);
  }
);

Given('a host is configured', () => {
  cy.addHost({
    activeCheckEnabled: false,
    passiveCheckEnabled: true,
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
    .applyPollerConfiguration();
});

When('the user adds a procedure concerning this host in MediaWiki', () => {
  cy.visit(PAGES.configuration.hostsKnowledgeBaseLegacy);
  cy.wait('@getTimeZone');
  // Wait until the 'Host' search field is visible in the DOM page
  cy.waitForElementInIframe('#main-content', 'input[name="searchHost"]');
  cy.getIframeBody()
    .find('a[target="_blank"]')
    .eq(5)
    .then(($elt) => {
      // Get the href of the mediawiki link of the host
      const linkUrl = $elt.prop('href');
      // Check that the href is as expected
      expect(linkUrl).to.contains(`Host_:_${services.serviceOk.host}`);
      // Get MediaWiki container port and visit the link
      getMediaWikiContainerPort().then((port) => {
        // Now that you have the port, visit the URL
        cy.visit(
          `http://localhost:${port}/index.php?title=Host_:_${services.serviceOk.host}&action=edit`
        );
        // Type a wiki
        cy.get('#wpTextbox1').type('add wiki host page');
        cy.get('#wpSave').click();
        cy.execInContainer({
          command: 'php /usr/share/centreon/cron/centKnowledgeSynchronizer.php',
          name: 'web'
        });
        cy.visit('/');
      });
    });
});

Then(
  'a link towards this host procedure is available in the configuration',
  () => {
    cy.visit(PAGES.configuration.hostsLegacy);
    cy.wait('@getTimeZone');
    // Wait until the 'Name' search field is visible in the DOM page
    cy.waitForElementInIframe('#main-content', 'input[name="searchH"]');
    // Click on the host to open its details
    cy.getIframeBody().contains('a', services.serviceOk.host).click();
    // Wait until the tab 'Host Extended Infos' is visible
    cy.waitForElementInIframe(
      '#main-content',
      'a:contains("Host Extended Infos")'
    );
    cy.getIframeBody().contains('a', 'Host Extended Infos').click();
    // Click outside the form
    cy.get('body').click(0, 0);
    // Check that the 'Note URL' contains the url of the mediawiki
    cy.getIframeBody()
      .find('input[name="ehi_notes_url"]')
      .should('have.value', hostNoteUrl);
  }
);

Given('a service is configured', () => {
  cy.visit(PAGES.configuration.servicesByHostLegacy);
  cy.wait('@getTimeZone');
  // Wait until the 'Hosts' search field is visible in the DOM page
  cy.waitForElementInIframe('#main-content', 'input[name="searchH"]');
  // Check that a configured service is existed
  cy.getIframeBody().contains('a', services.serviceOk.name).should('exist');
});

When('the user adds a procedure concerning this service in MediaWiki', () => {
  cy.visit(PAGES.configuration.servicesKnowledgeBaseLegacy);
  cy.wait('@getTimeZone');
  // Wait until the 'Host' search field is visible in the DOM page
  cy.waitForElementInIframe('#main-content', 'input[name="searchHost"]');
  cy.getIframeBody()
    .find('a[name="Create wiki page"]')
    .eq(10)
    .then(($elt) => {
      // Get the href of the mediawiki link of the service
      const linkUrl = $elt.prop('href');
      // Check that the href is as expected
      expect(linkUrl).to.contains(
        `Service_:_${services.serviceOk.host}_/_${services.serviceOk.name}`
      );
      // Get MediaWiki container port and visit the link
      getMediaWikiContainerPort().then((port) => {
        // Now that you have the port, visit the URL
        cy.visit(
          `http://localhost:${port}/index.php?title=Service_:_${services.serviceOk.host}_/_${services.serviceOk.name}&action=edit`
        );
        // Type a wiki
        cy.get('#wpTextbox1').type('add wiki service page');
        cy.get('#wpSave').click();
        cy.execInContainer({
          command: 'php /usr/share/centreon/cron/centKnowledgeSynchronizer.php',
          name: 'web'
        });
        cy.visit('/');
      });
    });
});

Then(
  'a link towards this service procedure is available in configuration',
  () => {
    cy.visit(PAGES.configuration.servicesByHostLegacy);
    cy.wait('@getTimeZone');
    // Wait until the 'Hosts' search field is visible in the DOM page
    cy.waitForElementInIframe('#main-content', 'input[name="searchH"]');
    // Click on the service to open its details
    cy.getIframeBody().contains('a', services.serviceOk.name).click();
    // Wait until the tab 'Extended Infos' is visible
    cy.waitForElementInIframe('#main-content', 'a:contains("Extended Info")');
    cy.getIframeBody().contains('a', 'Extended Info').click();
    // Click outside the form
    cy.get('body').click(0, 0);
    // Check that the 'Note URL' contains the url of the mediawiki
    cy.getIframeBody()
      .find('input[name="esi_notes_url"]')
      .should('have.value', serviceNoteUrl);
  }
);

Given('the knowledge configuration page with procedure', () => {
  cy.visit(PAGES.configuration.hostsKnowledgeBaseLegacy);
  cy.wait('@getTimeZone');
  // Wait until the 'Host' search field is visible in the DOM page
  cy.waitForElementInIframe('#main-content', 'input[name="searchHost"]');
});

When('the user deletes a wiki procedure', () => {
  cy.getIframeBody().contains('a', 'Delete wiki page').click();
  cy.wait('@getTimeZone');
});

Then('the page is deleted and the option disappear', () => {
  cy.reload();
  cy.wait('@getTimeZone');
  cy.exportConfig();
  cy.getIframeBody().contains('a', 'Delete wiki page').should('not.exist');
  cy.getIframeBody().contains('font', ' No wiki page defined ').should('exist');
});
