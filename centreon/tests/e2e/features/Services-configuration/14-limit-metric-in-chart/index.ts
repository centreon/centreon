/* eslint-disable cypress/unsafe-to-chain-command */
import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import vms from '../../../fixtures/services/virtual-metric.json';

const checkFirstVMFromListing = () => {
  cy.getIframeBody().find('div.md-checkbox.md-checkbox-inline').eq(1).click();
  cy.getIframeBody()
    .find('select[name="o1"]')
    .invoke(
      'attr',
      'onchange',
      "javascript: { setO(this.form.elements['o1'].value); submit(); }"
    );
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
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/internal.php?object=centreon_metric&action=ListOfMetricsByService&*'
  }).as('getListOfMetricsByService');
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/internal.php?object=centreon_metric&action=statusByService&ids=*'
  }).as('getGraphMetrics');
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

Given('many virtual metrics are linked to a configured service', () => {
  cy.navigateTo({
    page: 'Virtual Metrics',
    rootItemNumber: 1,
    subMenu: 'Performances'
  });
  cy.wait('@getTimeZone');
  // Wait until the 'Virtual metrics' is visible in the DOM
  cy.waitForElementInIframe('#main-content', 'input[name="searchVM"]');
  cy.getIframeBody().contains('a', 'Add').click();
  cy.addOrUpdateVirtualMetric(vms.vmForMemory, true);
  //Type a value in 'Options' field for duplicate
  cy.getIframeBody().find('input[name="dupNbr[1]"]').clear().type('50');
  checkFirstVMFromListing();
  cy.getIframeBody().find('select[name="o1"]').select('Duplicate');
  cy.wait('@getTimeZone');
  cy.exportConfig();
});

When('the user displays the chart in performance page', () => {
  cy.navigateTo({
    page: 'Graphs',
    rootItemNumber: 1,
    subMenu: 'Performances'
  });
  cy.wait('@getTimeZone');
  // Wait until the 'Chart' field is visible in the DOM
  cy.waitForElementInIframe('#main-content', '#select-chart');
  // Search for a chart to display
  cy.getIframeBody().find('input[class="select2-search__field"]').eq(0).type('disk');
  // Chose memory service to display its graph
  cy.getIframeBody().contains('div', 'Centreon-Server - Disk-/').click();
  cy.wait('@getGraphMetrics');
})

Then('a message says that the chart will not be displayed is visible', () => {
  // Wait until the button 'Split Chart' is visible
  cy.waitForElementInIframe('#main-content', 'a:contains("Split chart ")');
  // Check that the message is displayed inside the graph
  cy.getIframeBody().contains('text', "Too many metrics, the chart can't be displayed").should('be.visible');
});

Then('a button is available to display the chart', () => {
  // Check that the button is displayed inside the graph
  cy.getIframeBody().contains('button', "Display Chart").should('be.visible');
});
