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

Given('a user is logged in a Centreon server via APIv2', () => {
  cy.loginAsAdminViaApiV2();
  cy.visit('/').url().should('include', '/monitoring/resources');
});

When('a call to the endpoint "Add" a time period is done via APIv2', () => {
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
    cy.wait('@getTimeZone');
    cy.waitForElementInIframe(
      '#main-content',
      'span[class*="badge service_ok"]'
    );
    cy.getIframeBody()
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
    cy.checkLogDetails(1, 0, 'Field Name', 'Before', 'After');
    cy.checkLogDetails(1, 1, 'name', '', periods.default.name);
    cy.checkLogDetails(1, 2, 'alias', '', periods.default.alias);
    cy.checkLogDetails(1, 3, 'monday', '', periods.default.days[0].time_range);
    cy.checkLogDetails(1, 4, 'tuesday', '', periods.default.days[1].time_range);
    cy.checkLogDetails(
      1,
      5,
      'wednesday',
      '',
      periods.default.days[2].time_range
    );
    cy.checkLogDetails(
      1,
      6,
      'thursday',
      '',
      periods.default.days[3].time_range
    );
    cy.checkLogDetails(1, 7, 'friday', '', periods.default.days[4].time_range);
    cy.checkLogDetails(
      1,
      8,
      'saturday',
      '',
      periods.default.days[5].time_range
    );
    cy.checkLogDetails(1, 9, 'sunday', '', periods.default.days[6].time_range);
  }
);

Given('a time period is configured via APIv2', () => {
  cy.addTimePeriodViaApi(periods.default);
});

When(
  'a call to the endpoint "Update" a time period is done on the configured time period via APIv2',
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
    cy.wait('@getTimeZone');
    cy.waitForElementInIframe(
      '#main-content',
      'span[class*="badge service_warning"]'
    );
    cy.getIframeBody()
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
    cy.checkLogDetails(1, 0, 'Field Name', 'Before', 'After');
    cy.checkLogDetails(
      1,
      1,
      'name',
      periods.default.name,
      periods.time_period1.name
    );
    cy.checkLogDetails(
      1,
      2,
      'alias',
      periods.default.alias,
      periods.time_period1.alias
    );
  }
);

When(
  'a call to the endpoint "Delete" a time period is done on the configured time period via APIv2',
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
    cy.wait('@getTimeZone');
    cy.waitForElementInIframe(
      '#main-content',
      'span[class*="badge service_critical"]'
    );
    cy.getIframeBody()
      .contains('span.badge.service_critical', 'Deleted')
      .should('exist');

    cy.getIframeBody()
      .find('tr.list_one')
      .find('td')
      .eq(2)
      .should('contain.text', 'timeperiod');
  }
);
