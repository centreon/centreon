/* eslint-disable prettier/prettier */
/* eslint-disable cypress/unsafe-to-chain-command */
import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';

import vms from '../../../fixtures/services/virtual-metric.json';

const checkFirstVMFromListing = () => {
  cy.navigateTo({
    page: 'Virtual Metrics',
    rootItemNumber: 1,
    subMenu: 'Performances'
  });
  cy.wait('@getTimeZone');
  cy.getIframeBody().find('div.md-checkbox.md-checkbox-inline').eq(1).click();
  cy.getIframeBody()
    .find('select[name="o1"]')
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

When('the user adds a virtual metric', () => {
  cy.navigateTo({
    page: 'Virtual Metrics',
    rootItemNumber: 1,
    subMenu: 'Performances'
  });
  cy.wait('@getTimeZone');
  cy.getIframeBody().contains('a', 'Add').click();
  cy.addOrUpdateVirtualMetric(vms.default);
});

Then('all properties are saved', () => {
  cy.getIframeBody().contains(vms.default.name).should('exist');
  cy.getIframeBody().contains(vms.default.name).click();
  cy.checkFieldsOfVM(vms.default);
  cy.getIframeBody()
    .find('.md-checkbox input[name="vhidden"]')
    .should('be.checked');
});

Given('an existing virtual metric', () => {
    cy.navigateTo({
        page: 'Virtual Metrics',
        rootItemNumber: 1,
        subMenu: 'Performances'
      });
      cy.wait('@getTimeZone');
      cy.getIframeBody().contains('a', 'Add').click();
      cy.addOrUpdateVirtualMetric(vms.default);
});

When('the user changes the properties of the configured virtual metric', () => {
    cy.getIframeBody().contains(vms.default.name).should('exist');
    cy.getIframeBody().contains(vms.default.name).click();
    cy.addOrUpdateVirtualMetric(vms.vmForUpdate);
});

Then('these properties are updated', () => {
    cy.getIframeBody().contains(vms.vmForUpdate.name).should('exist');
    cy.getIframeBody().contains(vms.vmForUpdate.name).click();
    cy.checkFieldsOfVM(vms.vmForUpdate);
    cy.getIframeBody()
      .find('.md-checkbox input[name="vhidden"]')
      .should('not.be.checked');
});

When('the user duplicates the configured virtual metric', () => {
  checkFirstVMFromListing();
  cy.getIframeBody().find('select[name="o1"]').select('Duplicate');
  cy.wait('@getTimeZone');
  cy.exportConfig();
});

Then('a new virtual metric is created with identical fields', () => {
  cy.getIframeBody().contains(vms.vmForDuplication.name).should('exist');
  cy.getIframeBody().contains(vms.vmForDuplication.name).click();
  cy.checkFieldsOfVM(vms.vmForDuplication);
  cy.getIframeBody()
    .find('.md-checkbox input[name="vhidden"]')
    .should('be.checked');
});

When('the user deletes the configured virtual metric', () => {
  checkFirstVMFromListing();
  cy.getIframeBody().find('select[name="o1"]').select('Delete');
  cy.wait('@getTimeZone');
  cy.exportConfig();
});

Then('the virtual metric disappears from the Virtual metrics list', () => {
  cy.getIframeBody().contains(vms.default.name).should('not.exist');
});
