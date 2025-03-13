/* eslint-disable cypress/unsafe-to-chain-command */
import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { addCustomView, addSharedView } from '../common';

const logByAclUser = () => {
  cy.logout();
  cy.loginByTypeOfUser({
    jsonName: 'custom-view-acl-user',
    loginViaApi: false
  });
  cy.visit('/centreon/main.php?p=103');
  cy.wait('@getTimeZone');
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
    url: '/centreon/api/internal.php?object=centreon_topology&action=navigationList'
  }).as('getNavigationList');
  cy.intercept({
    method: 'GET',
    url: '/centreon/include/common/userTimezone.php'
  }).as('getTimeZone');
  cy.intercept({
    method: 'GET',
    url: '/centreon/include/home/customViews/views.php?currentView=*'
  }).as('getViews');
  cy.intercept({
    method: 'POST',
    url: '/centreon/include/home/customViews/action.php'
  }).as('action');
  cy.intercept({
    method: 'Get',
    url: '/centreon/api/internal.php?object=centreon_configuration_contact&action=list*'
  }).as('getContacts');
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
  cy.visit('/centreon/main.php?p=103');
  cy.wait('@getTimeZone');
});

When('the admin adds a new unlocked custom view shared with a configured non admin user', () => {
  addCustomView('Unlocked-View', false);
  cy.getIframeBody().contains('button', 'Share view').click();
  cy.getIframeBody().find('input[placeholder="Unlocked users"]').click();
  cy.wait('@getContacts');
  cy.getIframeBody().find('div[title="custom-view-acl-user"]').click();
  cy.getIframeBody().find('input[name="submit"][value="Share"]').click();
  cy.exportConfig();
});

Then('the view is added', () => {
  cy.getIframeBody().contains('a', 'Unlocked-View').should('exist');
});

Given('a shared custom view with the non admin user', () => {
  // Visit the page 'Home > Custom Views'
  cy.visit('/centreon/main.php?p=103')
  cy.wait('@getTimeZone');
  cy.wait('@getViews');
  cy.getIframeBody().contains('a', 'Unlocked-View').should('exist');
});

When('the non admin user is using the shared view', () => {
  logByAclUser();
  cy.wait('@getViews');
  cy.getIframeBody().find('a[title="Show/Hide edit mode"]').click();
  // Wait until the button 'Add view' is visible 
  cy.waitForElementInIframe(
    '#main-content',
    'button:contains("Add view")'
  );
  //addSharedView('Unlocked-View')
});

Then('he can modify the content of the shared view', () => {
  
});

When('he removes the shared view', () => {

});

Then('the view is not visible anymore', () => {
 
});

Then('the user can use the shared view again', () => {
});

When('the user modifies the custom view', () => {
});

Then('the changes are reflected on all users displaying the custom view', () => {
});

When('the owner removes the view', () => {
  
});

Then('the view remains visible for all users displaying the custom view', () => {

});

Then('the view is removed for the owner', () => {

});

Given('a shared custom view with a group', () => {

});

Then('he can modify the content of the shared view', () => {

});

When('he removes the shared view', () => {

});

Then('the view is not visible anymore', () => {

});

Then('the user can use the shared view again', () => {

});

When('the user modifies the custom view', () => {

});

Then('the changes are reflected on all users displaying the custom view', () => {

});

When('the owner removes the view', () => {

});

Then('the view remains visible for all users displaying the custom view', () => {

});

Then('the view is removed for the owner', () => {

});