/* eslint-disable no-script-url */
/* eslint-disable cypress/unsafe-to-chain-command */
import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';

import grps from '../../../fixtures/notifications/data-for-notification.json';
import data from '../../../fixtures/host-groups/dependency.json';

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

Given('a user is logged in a Centreon server', () => {
  cy.loginByTypeOfUser({
    jsonName: 'admin',
    loginViaApi: false
  });
});

Given('some hosts groups are configured', () => {
  cy.addHostGroup({
    name: grps.hostGroups.hostGroup1.name
  });
  cy.addHostGroup({
    name: grps.hostGroups.hostGroup2.name
  });
});

Given('a host group dependency is configured', () => {
  cy.navigateTo({
    page: 'Host Groups',
    rootItemNumber: 3,
    subMenu: 'Notifications'
  });
  cy.getIframeBody().contains('a', 'Add').click({ force: true });
  cy.addHostGroupDependency(data.default);
});

When('the user changes the properties of a host group dependency', () => {
  cy.waitForElementInIframe(
    '#main-content',
    `a:contains("${data.default.name}")`
  );
  cy.getIframeBody().contains(data.default.name).click();
  cy.updateHostGroupDependency(data.HostGrpDependency1);
});

Then('the properties are updated', () => {
  cy.waitForElementInIframe(
    '#main-content',
    `a:contains("${data.HostGrpDependency1.name}")`
  );
  cy.getIframeBody().contains(data.HostGrpDependency1.name).click();
  cy.waitForElementInIframe('#main-content', 'input[name="dep_name"]');
  cy.getIframeBody()
    .find('input[name="dep_name"]')
    .should('have.value', data.HostGrpDependency1.name);

  cy.getIframeBody()
    .find('input[name="dep_description"]')
    .should('have.value', data.HostGrpDependency1.description);
  cy.getIframeBody().find('#eUp').should('be.checked');
  cy.getIframeBody().find('#nDown').should('be.checked');
  cy.getIframeBody()
    .find('#dep_hgParents')
    .find('option:selected')
    .should('have.length', 2)
    .then((options) => {
      const selectedTexts = Array.from(options).map((option) =>
        option.text.trim()
      );
      expect(selectedTexts).to.include.members([
        data.HostGrpDependency1.hostGrpsNames[0],
        data.HostGrpDependency1.hostGrpsNames[1]
      ]);
    });
  cy.getIframeBody()
    .find('#dep_hgChilds')
    .find('option:selected')
    .should('have.length', 1)
    .and('have.text', data.HostGrpDependency1.dependentHostGrpsNames[0]);
  cy.getIframeBody()
    .find('textarea[name="dep_comment"]')
    .should('have.value', data.HostGrpDependency1.comment);
});

When('the user duplicates a host group dependency', () => {
  cy.checkFirstRowFromListing('searchHGD');
  cy.getIframeBody().find('select[name="o1"]').select('Duplicate');
  cy.wait('@getTimeZone');
  cy.exportConfig();
});

Then('the new object has the same properties', () => {
  cy.waitForElementInIframe(
    '#main-content',
    `a:contains("${data.default.name}_1")`
  );
  cy.getIframeBody().contains(`${data.default.name}_1`).click();
  cy.waitForElementInIframe('#main-content', 'input[name="dep_name"]');
  cy.getIframeBody()
    .find('input[name="dep_name"]')
    .should('have.value', `${data.default.name}_1`);
  cy.getIframeBody()
    .find('input[name="dep_description"]')
    .should('have.value', data.default.description);
  cy.getIframeBody().find('#eDown').should('be.checked');
  cy.getIframeBody().find('#nPending').should('be.checked');
  cy.getIframeBody()
    .find('#dep_hgParents')
    .find('option:selected')
    .should('have.length', 1)
    .and('have.text', data.default.hostGrpsNames[0]);
  cy.getIframeBody()
    .find('#dep_hgChilds')
    .find('option:selected')
    .should('have.length', 1)
    .and('have.text', data.default.dependentHostGrpsNames[0]);
  cy.getIframeBody()
    .find('textarea[name="dep_comment"]')
    .should('have.value', data.default.comment);
});

When('the user deletes a host group dependency', () => {
  cy.checkFirstRowFromListing('searchHGD');
  cy.getIframeBody().find('select[name="o1"]').select('Delete');
  cy.wait('@getTimeZone');
  cy.exportConfig();
});

Then('the deleted object is not displayed in the list', () => {
  cy.getIframeBody().contains(data.default.name).should('not.exist');
});
