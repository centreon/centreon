import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { INTERCEPTORS } from 'fixtures/shared/constants/interceptors';
import { PAGES } from 'fixtures/shared/constants/pages';

import {
  addCustomView,
  addSharedView,
  deleteCustomView,
  shareCustomView,
  visitCustomViewPage
} from '../common';

const viewName = 'locked-View';
const logByAclUser = () => {
  cy.logout();
  cy.loginByTypeOfUser({
    jsonName: 'custom-view-acl-user',
    loginViaApi: false
  });
  visitCustomViewPage();
};

const logByAdmin = () => {
  cy.logout();
  cy.loginByTypeOfUser({
    jsonName: 'admin',
    loginViaApi: false
  });
  visitCustomViewPage();
  cy.wait('@getViews');
};

before(() => {
  cy.startContainers();
  cy.executeCommandsViaClapi(
    'resources/clapi/config-ACL/custom-view-acl-user.json'
  );
});

beforeEach(() => {
  cy.intercept({
    method: 'GET',
    url: INTERCEPTORS.api.navigation_list
  }).as('getNavigationList');
  cy.intercept({
    method: 'GET',
    url: INTERCEPTORS.pages.time_zone
  }).as('getTimeZone');
  cy.intercept({
    method: 'GET',
    url: `${INTERCEPTORS.pages.customViews_views}?currentView=*`
  }).as('getViews');
  cy.intercept({
    method: 'POST',
    url: INTERCEPTORS.pages.customViews_action
  }).as('action');
  cy.intercept({
    method: 'Get',
    url: `${INTERCEPTORS.pages.centreon_configuration_contact}&action=list*`
  }).as('getContacts');
  cy.intercept({
    method: 'Get',
    url: `${INTERCEPTORS.pages.centreon_configuration_contactgroup}&action=list*`
  }).as('getContactGroups');
});

after(() => {
  cy.stopContainers();
});

Given('an admin user is logged in a Centreon server', () => {
  cy.loginByTypeOfUser({
    jsonName: 'admin',
    loginViaApi: false
  });
});

Given('the admin is on the "Home > Custom Views" page', () => {
  visitCustomViewPage();
});

When(
  'the admin adds a new locked custom view shared with a configured non admin user',
  () => {
    addCustomView(viewName, false);
    shareCustomView('Locked users', 'custom-view-acl-user');
  }
);

Then('the view is added', () => {
  cy.getIframeBody().contains('a', viewName).should('exist');
});

Given('a custom view shared in read only with the non admin user', () => {
  visitCustomViewPage();
  cy.wait('@getViews');
  cy.getIframeBody().contains('a', viewName).should('exist');
});

When('the non admin user wishes to add a new custom view', () => {
  logByAclUser();
  cy.getIframeBody().find('a[title="Show/Hide edit mode"]').click();
  // Wait until the button 'Add view' is visible
  cy.waitForElementInIframe('#main-content', 'button:contains("Add view")');
});

Then('he can add the shared view', () => {
  addSharedView(viewName);
});

When('the non admin user is using the shared view', () => {
  logByAclUser();
  cy.wait('@getViews');
  cy.getIframeBody().contains('a', viewName).should('exist');
});

Then('he cannot modify the content of the shared view', () => {
  // Check that the buttons 'Edit View' and 'Add widget' are disabled
  ['editView', 'addWidget'].forEach((style) => {
    cy.getIframeBody().find(`button.${style}`).should('be.disabled');
  });
});

When('he removes the shared view', () => {
  cy.getIframeBody().find('a[title="Show/Hide edit mode"]').click();
  deleteCustomView();
});

Then('the view is not visible anymore', () => {
  cy.getIframeBody().contains('a', viewName).should('not.exist');
});

Then('the user can use the shared view again', () => {
  addSharedView(viewName);
});

When('the owner modifies the custom view', () => {
  logByAdmin();
  cy.getIframeBody().find('a[title="Show/Hide edit mode"]').click();
  // Wait until the button 'Add view' is visible
  cy.waitForElementInIframe('#main-content', 'button:contains("Add view")');
  // Click on the 'Edit View' button
  cy.getIframeBody().find('button.editView').click({ force: true });
  // Type a new value in the field 'Name' of the custom view
  cy.getIframeBody()
    .find('#editView')
    .find('input[name="name"]')
    .clear()
    .type(`${viewName}-changed`);
  // Click on the 'Submit' button
  cy.getIframeBody()
    .find('#editView')
    .find('input[name="submit"]')
    .eq(0)
    .click();
  cy.wait('@getViews');
});

Then(
  'the changes are reflected on all users displaying the custom view',
  () => {
    logByAclUser();
    cy.wait('@getViews');
    cy.getIframeBody().contains('a', `${viewName}-changed`).should('exist');
  }
);

When('the owner removes the view', () => {
  logByAdmin();
  cy.getIframeBody().find('a[title="Show/Hide edit mode"]').click();
  deleteCustomView();
});

Then('the view is removed for all users displaying the custom view', () => {
  logByAclUser();
  cy.getIframeBody().contains('a', `${viewName}-changed`).should('not.exist');
});

Given('a custom view shared in read only with a group', () => {
  /*** this part is for setting the Guest contact group to the configured acl user ***/
  cy.visit(PAGES.configuration.contactsUsersLegacy);
  cy.wait('@getTimeZone');
  cy.getIframeBody().contains('a', 'custom-view-acl-user').click();
  // The contact form now opens in the side panel, a nested iframe, so the fields
  // are no longer part of the #main-content document.
  cy.waitForElementInIframe('#main-content', 'iframe#cfSidePanelFrame');
  cy.getSidePanelBody().find('input[name="contact_alias"]').should('exist');
  // Anchored on the field: the modernized form labels it "Contact Groups" and
  // the select2 no longer carries the QuickForm label as a placeholder. The
  // selection container is the click target, its inline search input being
  // covered by the "Select all" header.
  cy.getSidePanelBody()
    .contains('.cf-field', 'Contact Groups')
    .find('.select2-selection--multiple')
    .click();
  cy.wait('@getContactGroups');
  cy.getSidePanelBody().contains('Guest').click();
  // The dropdown stays open on a multi-select and would cover the submit.
  cy.getSidePanelBody().find('.cf-tab-nav').click();
  cy.getSidePanelBody()
    .find('input.btc.bt_success[name^="submit"]')
    .eq(0)
    .click();
  cy.wait('@getTimeZone');
  cy.exportConfig();

  /*** this part is for adding a locked custom view with the group Guest ***/
  visitCustomViewPage();
  addCustomView(viewName, false);
  shareCustomView('Locked user groups', 'Guest');
});

Given('a configured custom view shared in read only with a group', () => {
  visitCustomViewPage();
  cy.wait('@getViews');
  cy.getIframeBody().contains('a', viewName).should('exist');
});

When('an user of this group is using the configured shared view', () => {
  logByAclUser();
  cy.wait('@getViews');
  cy.getIframeBody().contains('a', viewName).should('exist');
});
