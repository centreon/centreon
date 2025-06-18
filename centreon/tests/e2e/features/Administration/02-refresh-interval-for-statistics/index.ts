/* eslint-disable cypress/unsafe-to-chain-command */
import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';

const refreshValue = 40;

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
    url: '/centreon/api/internal.php?object=centreon_topcounter&action=servicesStatus'
  }).as('getTopCounter');
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

When('the user goes to Administration > Parameters > Centreon UI page', () => {
  cy.navigateTo({
    page: 'Centreon UI',
    rootItemNumber: 4,
    subMenu: 'Parameters'
  });
  cy.wait('@getTimeZone');
});

When('the user updates the Refresh Interval for statistics field value', () => {
  cy.getIframeBody()
    .find('input[name="AjaxTimeReloadStatistic"]')
    .clear()
    .type(`${refreshValue}`);
  cy.getIframeBody().find('#submitGeneralOptionsForm').click();
  cy.wait('@getTimeZone');
});

When('the user logout from the centreon plateform', () => {
  cy.logout();
});

When('the user reconnect to the centreon plateform', () => {
  cy.loginByTypeOfUser({
    jsonName: 'admin',
    loginViaApi: false
  });
});

Then(
  'the top counter refresh request must be called each "defined value" seconds',
  () => {
    cy.wait('@getTopCounter').then((interception) => {
      const firstRequestTime = Date.now();
      cy.wait(refreshValue * 1000);
      cy.wait('@getTopCounter').then((interception) => {
        const secondRequestTime = Date.now();
        const timeDifference = (secondRequestTime - firstRequestTime) / 1000;
        expect(timeDifference).to.be.at.least(
          40,
          `The request of refresh the top counter is called each ${refreshValue} seconds`
        );
      });
    });
  }
);

Then(
  'the parameters request must contains the "defined value" for the Refresh Interval for statistics attribut',
  () => {
    cy.request({
      method: 'GET',
      url: 'centreon/api/latest/administration/parameters'
    }).then((response) => {
      expect(response.status).to.eq(200);
      expect(response.body.statistics_default_refresh_interval).to.eq(
        refreshValue
      );
    });
  }
);
