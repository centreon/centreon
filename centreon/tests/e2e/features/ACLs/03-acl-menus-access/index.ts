import { Given, When, Then } from '@badeball/cypress-cucumber-preprocessor';
import data from '../../../fixtures/acls/acl-access-groups.json';

const ACLGroups = {
  ACLGroup1: {
    name: 'ACL_group_1',
    alias: 'ACL group 1'
  },
  ACLGroup2: {
    name: 'ACL_group_2',
    alias: 'ACL group 2'
  }
};

const ACLMenu ={
    name
}

beforeEach(() => {
  cy.startWebContainer();
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
  cy.stopWebContainer();
});

Given('I am logged in a Centreon server', () => {
  cy.loginByTypeOfUser({ jsonName: 'admin' });
});

When('I add a new menu access linked with two groups', () => {
  cy.addACLGroup({ name: ACLGroups.ACLGroup1.name });
  cy.addACLGroup({ name: ACLGroups.ACLGroup2.name });

  cy.navigateTo({
    page: 'Menus Access',
    rootItemNumber: 4,
    subMenu: 'ACL'
  });
  cy.wait('@getTimeZone');
  cy.getIframeBody().contains('a', 'Add').click();

  cy.wait('@getTimeZone');
});

Then('the menu access is saved with its properties', () => {
  // Verify menu access is saved
});

Then(
  'only chosen linked access groups display the new menu access in Authorized information tab',
  () => {
    // Verify linked access groups display the new menu access
  }
);

When('I remove one access group', () => {
  // Remove one access group
});

Then('link between access group and menu access must be broken', () => {
  // Verify link between access group and menu access is broken
});

When('I duplicate the Menu access', () => {
  // Duplicate the Menu access
});

Then(
  'a new Menu access is created with identical properties except the name',
  () => {
    // Verify a new Menu access is created with identical properties except the name
  }
);

When('I disable it', () => {
  // Disable the Menu access
});

Then('its status is modified', () => {
  // Verify the status of the Menu access is modified
});

When('I delete the Menu access', () => {
  // Delete the Menu access
});

Then(
  'the menu access record is not visible anymore in Menus Access page',
  () => {
    // Verify the menu access record is not visible anymore in Menus Access page
  }
);

Then('the link with access groups is broken', () => {
  // Verify the link with access groups is broken
});
