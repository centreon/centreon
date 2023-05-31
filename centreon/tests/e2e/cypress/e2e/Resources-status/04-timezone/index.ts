import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';

import { checkServicesExistInDatabase } from '../../../commons';
import {
  insertDtResources,
  secondServiceInDtName,
  serviceInDtName
} from '../common';

before(() => {
  cy.startWebContainer();
});

beforeEach(() => {
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/internal.php?object=centreon_topology&action=navigationList'
  }).as('getNavigationList');
  cy.intercept({
    method: 'POST',
    url: '/centreon/api/latest/monitoring/resources/downtime'
  }).as('postSaveDowntime');
  cy.intercept({
    method: 'GET',
    url: '/centreon/include/common/webServices/rest/internal.php?object=centreon_configuration_timezone&action=list*'
  }).as('getTimezonesList');
  cy.intercept({
    method: 'GET',
    url: '/centreon/include/common/userTimezone.php'
  }).as('getTimeZone');
});

Given('a user authenticated in a Centreon server', () => {
  cy.loginByTypeOfUser({
    jsonName: 'admin',
    loginViaApi: true
  });
});

Given('the platform is configured with at least one resource', () => {
  insertDtResources();

  checkServicesExistInDatabase([serviceInDtName, secondServiceInDtName]);
});

When('user cliks on Timezone field in his profile menu', () => {
  cy.navigateTo({
    page: 'My Account',
    rootItemNumber: 4,
    subMenu: 'Parameters'
  }).wait('@getTimeZone');

  cy.getIframeBody()
    .find('span[aria-labelledby="select2-contact_location-container"]')
    .eq(0)
    .as('timezoneInput')
    .should('be.visible');

  cy.get('@timezoneInput').click().wait('@getTimezonesList');
});

When('user selects a Timezone \\/ Location', () => {
  cy.getIframeBody()
    .find('input[class="select2-search__field"]')
    .clear()
    .type('Africa/Casablanca')
    .wait('@getTimezonesList');

  cy.getIframeBody()
    .find('ul[id="select2-contact_location-results"] li')
    .contains('Africa/Casablanca')
    .eq(0)
    .click();
});

When('user saves the form', () => {
  cy.getIframeBody()
    .find('input[name="submitC"]')
    .eq(0)
    .contains('Save')
    .click();
});

Then('timezone information are updated on the banner', () => {
  cy.get('header div[data-cy="clock"]').then(($time) => {
    const timeofTZ = new Date().toLocaleString('en-US', {
      timeZone: 'Africa/Casablanca'
    });
    console.log($time.children()[1]);
  });
});
