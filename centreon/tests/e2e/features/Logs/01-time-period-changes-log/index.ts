/* eslint-disable cypress/unsafe-to-chain-command */
import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';

import periods from '../../../fixtures/time-periods/time-period.json';

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
    url: '/centreon/main.php?p=508&object_type=timeperiod&object_id=5&searchU=&searchO=&otype='
  }).as('getTimePeriod');
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

When('a call to the endpoint "Add" a time period is done', () => {
  cy.addTimePeriodViaApi(periods.default);
});

Then('a new time period is displayed on the time periods page', () => {
  cy.navigateTo({
    page: 'Time Periods',
    rootItemNumber: 3,
    subMenu: 'Users'
  });
  cy.wait('@getTimeZone');
  cy.getIframeBody().contains('a', periods.default.name).should('be.visible');
});

Then(
  'a new "Added" ligne of log is getting added to the page Administration > Logs',
  () => {
    cy.navigateTo({
      page: 'Logs',
      rootItemNumber: 4
    });
    cy.reload();
    cy.getIframeBody()
      .find('tr.list_one')
      .find('td')
      .eq(1)
      .contains('span.badge.service_ok', 'Added')
      .should('exist');

    cy.getIframeBody()
      .find('tr.list_one')
      .find('td')
      .eq(2)
      .should('contain.text', 'timeperiod');
  }
);

Then(
  'the informations of the log are the same as those passed to the endpoint',
  () => {
    cy.getIframeBody().contains(periods.default.name).click();
    cy.waitForElementInIframe(
      '#main-content',
      'a[href="./main.php?p=508"].btc.bt_success'
    );
    cy.getIframeBody()
      .find('td.ListColHeaderCenter')
      .eq(0)
      .should('contain.text', periods.default.name);
    cy.getIframeBody().contains('td', 'Create by admin').should('exist');
    cy.getIframeBody().contains('td', periods.default.name).should('exist');
    cy.getIframeBody().contains('td', periods.default.alias).should('exist');
    cy.getIframeBody().contains('td', 'monday').should('exist');
    cy.getIframeBody().contains('td', 'tuesday').should('exist');
    cy.getIframeBody().contains('td', 'wednesday').should('exist');
    cy.getIframeBody().contains('td', 'thursday').should('exist');
    cy.getIframeBody().contains('td', 'friday').should('exist');
    cy.getIframeBody().contains('td', 'saturday').should('exist');
    cy.getIframeBody().contains('td', 'sunday').should('exist');
  }
);

Given('a time period is configured', () => {
  cy.addTimePeriodViaApi(periods.default);
});

When(
  'a call to the endpoint "Update" a time period is done on the configured time period',
  () => {
    cy.updateTimePeriodViaApi(periods.default.name, periods.time_period1);
  }
);

Then(
  'a new "Changed" ligne of log is getting added to the page Administration > Logs',
  () => {
    cy.navigateTo({
      page: 'Logs',
      rootItemNumber: 4
    });
    cy.reload();
    cy.getIframeBody()
      .find('tr.list_one')
      .find('td')
      .eq(1)
      .contains('span.badge.service_warning', 'Changed')
      .should('exist');

    cy.getIframeBody()
      .find('tr.list_one')
      .find('td')
      .eq(2)
      .should('contain.text', 'timeperiod');
  }
);

Then(
  'the informations of the log are the same as those of the updated time period',
  () => {
    cy.getIframeBody().contains(periods.time_period1.name).click();
    cy.waitForElementInIframe(
      '#main-content',
      'a[href="./main.php?p=508"].btc.bt_success'
    );
    cy.getIframeBody()
      .find('td.ListColHeaderCenter')
      .eq(0)
      .should('contain.text', periods.time_period1.name);
    cy.getIframeBody().contains('td', 'Change by admin').should('exist');
    cy.getIframeBody()
      .contains('td', periods.time_period1.name)
      .should('exist');
    cy.getIframeBody()
      .contains('td', periods.time_period1.alias)
      .should('exist');
  }
);

When(
  'a call to the endpoint "Delete" a time period is done on the configured time period',
  () => {
    cy.deleteTimePeriodViaApi(periods.default.name);
  }
);

Then(
  'a new "Deleted" ligne of log is getting added to the page Administration > Logs',
  () => {
    cy.navigateTo({
      page: 'Logs',
      rootItemNumber: 4
    });
    cy.reload();
    cy.getIframeBody()
      .find('tr.list_one')
      .find('td')
      .eq(1)
      .contains('span.badge.service_critical', 'Deleted')
      .should('exist');

    cy.getIframeBody()
      .find('tr.list_one')
      .find('td')
      .eq(2)
      .should('contain.text', 'timeperiod');
  }
);
