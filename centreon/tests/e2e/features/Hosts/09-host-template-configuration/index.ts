import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { INTERCEPTORS } from 'fixtures/shared/constants/interceptors';

import { formSelectors } from '../common';

const hostName = 'New-Host-Name';

beforeEach(() => {
  cy.startContainers();
  cy.intercept({
    method: 'GET',
    url: INTERCEPTORS.api.navigation_list
  }).as('getNavigationList');
  cy.intercept({
    method: 'GET',
    url: INTERCEPTORS.pages.time_zone
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

When('a host inheriting from a host template', () => {
  cy.setUserTokenApiV1();
  cy.addHost({
    hostGroup: 'Linux-Servers',
    name: hostName,
    template: 'Printers'
  }).applyPollerConfiguration();
});

Then('the user configures the host', () => {
  cy.openHostsListing();
  cy.openListingRowForm(hostName);
});

Then('the user can configure directly its parent template', () => {
  // The affordance is an inline-SVG button in the template row's action group,
  // and it opens the template in a popup window Cypress will not follow — so
  // read the template id from the row's own select, like the button does, and
  // navigate in place instead.
  cy.getSidePanelBody()
    .find('.cf-macro-action-btn[title="Modify"]')
    .first()
    .closest('.clone-cell')
    .find('select')
    .invoke('val')
    .then((templateId) => {
      if (
        templateId === '' ||
        templateId === undefined ||
        templateId === null
      ) {
        throw new Error('No parent template found to edit');
      }

      cy.visit(
        `/centreon/main.php?p=60103&o=c&min=1&host_id=${templateId.toString()}`
      );
    });

  cy.waitForElementInIframe('#main-content', 'input[name="host_name"]');
  cy.getIframeBody().find('input[name="host_name"]').click();
  cy.getIframeBody().find(formSelectors.saveButton).first().click();
});

When('a host template inheriting from a host template', () => {
  cy.openHostTemplatesListing();
});

When('the user configures the host template', () => {
  // Parent host template already configured: generic-host.
  cy.openListingRowForm('Printers');
});
