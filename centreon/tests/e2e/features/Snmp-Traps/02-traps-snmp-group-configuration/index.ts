import { Given, When, Then } from '@badeball/cypress-cucumber-preprocessor';

import data from '../../../fixtures/snmp-traps/snmp-trap.json';
import { CreateOrUpdateTrapGroup } from '../common';

const checkFirstTrapGroupFromListing = () => {
  cy.waitForElementInIframe('#main-content', 'a[href*="id=1"]');
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
    url: '/centreon/include/common/userTimezone.php'
  }).as('getTimeZone');
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/internal.php?object=centreon_topology&action=navigationList'
  }).as('getNavigationList');
  cy.intercept({
    method: 'GET',
    url: '/centreon/include/common/webServices/rest/internal.php?object=centreon_configuration_trap&action=list*'
  }).as('listTraps');
});

Given('an admin user is logged in a Centreon server', () => {
  cy.loginByTypeOfUser({ jsonName: 'admin' });
});

Given('a trap group is configured', () => {
  cy.navigateTo({
    page: 'Group',
    rootItemNumber: 3,
    subMenu: 'SNMP Traps'
  });
  cy.wait('@getTimeZone');
  cy.getIframeBody().contains('a', 'Add').click();
  CreateOrUpdateTrapGroup(data.snmpGroup1);
  cy.wait('@getTimeZone');
  cy.exportConfig();
});

When('the user changes the properties of a trap group', () => {
  cy.waitForElementInIframe('#main-content', 'a[href*="id=1"]');
  cy.getIframeBody().contains(data.snmpGroup1.name).click();
  CreateOrUpdateTrapGroup(data.snmpGroup2);
  cy.wait('@getTimeZone');
  cy.exportConfig();
});

Then('the properties are updated', () => {
  cy.waitForElementInIframe('#main-content', 'a[href*="id=1"]');
  cy.getIframeBody().contains(data.snmpGroup2.name).click();
  cy.waitForElementInIframe('#main-content', 'input[name="name"]');
  cy.getIframeBody()
    .find('input[name="name"]')
    .should('have.value', data.snmpGroup2.name);
  cy.getIframeBody()
    .find('select[id="traps"]')
    .find('option:selected')
    .then(($selectedOptions) => {
      const selectedTexts = Array.from($selectedOptions).map(
        (option) => option.text
      );
      expect(selectedTexts).to.include.members([
        data.snmpGroup2.traps[0],
        data.snmpGroup2.traps[1]
      ]);
    });
});

When('the user duplicates a trap group', () => {
  checkFirstTrapGroupFromListing();
  cy.getIframeBody().find('select[name="o1"]').select('Duplicate');
  cy.wait('@getTimeZone');
  cy.exportConfig();
});

Then('the a new trap group is created with identical properties', () => {
  cy.waitForElementInIframe('#main-content', 'a[href*="id=2"]');
  cy.getIframeBody().contains(`${data.snmpGroup1.name}_1`).click();
  cy.waitForElementInIframe('#main-content', 'input[name="name"]');
  cy.getIframeBody()
    .find('input[name="name"]')
    .should('have.value', `${data.snmpGroup1.name}_1`);
  cy.getIframeBody()
    .find('select[id="traps"]')
    .find('option:selected')
    .then(($selectedOptions) => {
      const selectedTexts = Array.from($selectedOptions).map(
        (option) => option.text
      );
      expect(selectedTexts).to.include.members([
        data.snmpGroup1.traps[0],
        data.snmpGroup1.traps[1]
      ]);
    });
});

When('the user deletes a trap group', () => {
  checkFirstTrapGroupFromListing();
  cy.getIframeBody().find('select[name="o1"]').select('Delete');
  cy.wait('@getTimeZone');
  cy.exportConfig();
});

Then(
  'the deleted trap group is not visible anymore on the trap group page',
  () => {
    cy.getIframeBody().contains(data.snmpGroup1.name).should('not.exist');
  }
);

afterEach(() => {
  cy.stopContainers();
});
