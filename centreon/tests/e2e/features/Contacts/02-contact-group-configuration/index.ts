/* eslint-disable cypress/unsafe-to-chain-command */
import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';

import groups from '../../../fixtures/users/contact.json';

const checkFirstContactGroupFromListing = () => {
  cy.navigateTo({
    page: 'Contact Groups',
    rootItemNumber: 3,
    subMenu: 'Users'
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
    url: '/centreon/include/common/webServices/rest/internal.php?object=centreon_configuration_contact*'
  }).as('getContacts');
  cy.intercept({
    method: 'GET',
    url: '/centreon/include/common/webServices/rest/internal.php?object=centreon_administration_aclgroup*'
  }).as('getACLGroups');
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

When('a contact group is configured', () => {
  cy.navigateTo({
    page: 'Contact Groups',
    rootItemNumber: 3,
    subMenu: 'Users'
  });
  cy.wait('@getTimeZone');
  cy.getIframeBody().contains('a', 'Add').click();
  cy.addOrUpdateContactGroup(groups.defaultGroup);
});

When('the user updates the properties of the configured contact group', () => {
  cy.getIframeBody().contains(groups.defaultGroup.name).click();
  cy.addOrUpdateContactGroup(groups.GroupForUpdate);
});

Then('the properties are updated', () => {
  cy.getIframeBody().contains(groups.GroupForUpdate.name).should('exist');
  cy.getIframeBody().contains(groups.GroupForUpdate.name).click();
  cy.wait('@getTimeZone');
  cy.waitForElementInIframe('#main-content', 'input[name="cg_name"]');
  cy.getIframeBody()
    .find('input[name="cg_name"]')
    .should('have.value', groups.GroupForUpdate.name);
  cy.getIframeBody()
    .find('input[name="cg_alias"]')
    .should('have.value', groups.GroupForUpdate.alias);
  cy.getIframeBody().find('#cg_contacts').find('option:selected').then($selectedOptions => {
    const selectedTexts = Array.from($selectedOptions).map(option => option.text);
    expect(selectedTexts).to.include.members([groups.defaultGroup.linkedContact, groups.GroupForUpdate.linkedContact]);
  });
  cy.getIframeBody().find('#cg_acl_groups').find('option:selected').then($selectedOptions => {
    const selectedTexts = Array.from($selectedOptions).map(option => option.text);
    expect(selectedTexts).to.include.members(['ALL']);
  });
  cy.checkLegacyRadioButton(groups.GroupForUpdate.status);
  cy.getIframeBody()
    .find('textarea[name="cg_comment"]')
    .should('have.value', groups.GroupForUpdate.comment);
});

When('the user duplicates the configured contact group', () => {
  checkFirstContactGroupFromListing();
  cy.getIframeBody().find('select[name="o1"]').select('Duplicate');
  cy.wait('@getTimeZone');
  cy.exportConfig();
});

Then('a new contact group is created with identical properties', () => {
  cy.getIframeBody().contains(`${groups.defaultGroup.name}_1`).should('exist');
  cy.getIframeBody().contains(`${groups.defaultGroup.name}_1`).click();
  cy.wait('@getTimeZone');
  cy.waitForElementInIframe('#main-content', 'input[name="cg_name"]');
  cy.getIframeBody()
    .find('input[name="cg_name"]')
    .should('have.value', `${groups.defaultGroup.name}_1`);
  cy.getIframeBody()
    .find('input[name="cg_alias"]')
    .should('have.value', groups.defaultGroup.alias);
  cy.getIframeBody().find('#cg_contacts').find('option:selected').then($selectedOptions => {
    const selectedTexts = Array.from($selectedOptions).map(option => option.text);
    expect(selectedTexts).to.include.members([groups.defaultGroup.linkedContact]);
  });
  cy.getIframeBody().find('#cg_acl_groups').find('option:selected').then($selectedOptions => {
    const selectedTexts = Array.from($selectedOptions).map(option => option.text);
    expect(selectedTexts).to.include.members(['ALL']);
  });
  cy.checkLegacyRadioButton(groups.defaultGroup.status);
  cy.getIframeBody()
    .find('textarea[name="cg_comment"]')
    .should('have.value', groups.defaultGroup.comment);
});

When('the user deletes the configured contact group', () => {
  checkFirstContactGroupFromListing();
  cy.getIframeBody().find('select[name="o1"').select('Delete');
  cy.wait('@getTimeZone');
  cy.exportConfig();
});

Then('the deleted contact group is not visible anymore on the contact group page', () => {
  cy.getIframeBody().contains(groups.defaultGroup.name).should('not.exist');
});