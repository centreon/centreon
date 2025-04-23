/* eslint-disable cypress/unsafe-to-chain-command */
import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';

const wrongProxyAdress = 'squad';
const wrongProxyPort = '9999';

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
  cy.intercept({
    method: 'POST',
    url: '/centreon/include/common/webServices/rest/internal.php?object=centreon_proxy&action=checkConfiguration'
  }).as('testProxy');
});

after(() => {
  cy.stopContainers();
});

Given('a user is logged in a Centreon server with a configured proxy', () => {
  cy.loginByTypeOfUser({
    jsonName: 'admin',
    loginViaApi: false
  });
});

When('the user tests the proxy configuration in the interface', () => {
  cy.visit('/centreon/main.php?p=50110&o=general');
  cy.wait('@getTimeZone');
  cy.waitForElementInIframe('#main-content', 'input[name="test_proxy"]');
  cy.getIframeBody().find('input[name="test_proxy"]').click();
  cy.wait('@testProxy');
});

Then('a popin displays a successful connexion', () => {
  cy.getIframeBody()
    .find('span.msg-field.success2')
    .should('be.visible')
    .and('contain.text', 'Connection Successful');
});

Given(
  'a user is logged in a Centreon server with a wrongly configured proxy',
  () => {
    cy.loginByTypeOfUser({
      jsonName: 'admin',
      loginViaApi: false
    });
    cy.navigateTo({
      page: 'Centreon UI',
      rootItemNumber: 4,
      subMenu: 'Parameters'
    });
    cy.wait('@getTimeZone');
    cy.waitForElementInIframe('#main-content', 'input[name="test_proxy"]');
    cy.getIframeBody()
      .find('input[name="proxy_url"]')
      .clear()
      .type(wrongProxyAdress);
    cy.getIframeBody()
      .find('input[name="proxy_port"]')
      .clear()
      .type(wrongProxyPort);
    cy.getIframeBody().find('#submitGeneralOptionsForm').click();
    cy.wait('@getTimeZone');
  }
);

Then('a popin displays an error message', () => {
  cy.getIframeBody()
    .find('span.msg-field.error')
    .should('be.visible')
    .and(
      'contain.text',
      'Could not establish connection to Centreon IMP servers (Page not found)'
    );
});
