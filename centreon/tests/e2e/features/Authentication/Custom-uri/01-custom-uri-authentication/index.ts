import { Then, When } from '@badeball/cypress-cucumber-preprocessor';

import {
  checkHostsAreMonitored,
  checkServicesAreMonitored
} from '../../../../commons';
import { reloadWebServer, updateWebServerConfig } from '../common';

const service = 'Ping';
const host = 'Centreon-Server';

before(() => {
  cy.startContainers();

  cy.loginAsAdminViaApiV2()
    .scheduleServiceCheck({ host: 'Centreon-Server', service: 'Ping' })
    .logoutViaAPI();
});

beforeEach(() => {
  cy.intercept({
    method: 'GET',
    url: '/monitor/api/internal.php?object=centreon_topology&action=navigationList'
  }).as('getNavigationList');
  cy.intercept({
    method: 'GET',
    url: '/monitor/api/latest/users/filters/events-view?page=1&limit=100'
  }).as('getLastestUserFilters');
  cy.intercept({
    method: 'GET',
    url: '/monitor/include/common/userTimezone.php'
  }).as('getTimeZone');
  cy.intercept({
    method: 'GET',
    url: '/monitor/api/latest/monitoring/resources/hosts/*/services/*'
  }).as('getResourceDetails');
});

When(
  'I update the base uri within the corresponding web server configuration file',
  () => {
    updateWebServerConfig();
  }
);

When('I reload the web server', () => {
  reloadWebServer();

  cy.waitUntil(
    () => {
      return cy
        .request({
          failOnStatusCode: false,
          method: 'GET',
          url: '/monitor'
        })
        .then((response) => {
          return /^[2-3]\d{2}/.test(response.status.toString());
        });
    },
    {
      timeout: 10000
    }
  );
});

Then('I can authenticate to the centreon platform', () => {
  cy.loginByTypeOfUser({ jsonName: 'admin' });

  cy.url().should('contain', '/monitor');
});

Then(
  'the resource icons are displayed in configuration and monitoring pages',
  () => {
    cy.wait('@getLastestUserFilters');

    cy.getByLabel({ label: 'State filter' }).click();

    cy.get('[data-value="all"]').click();

    cy.contains(service).parent().get('.MuiChip-root').should('contain', 's');

    cy.contains(host).parent().get('.MuiChip-root').should('contain', 'h');

    cy.navigateTo({
      page: 'Hosts',
      rootItemNumber: 3,
      subMenu: 'Hosts'
    });

    cy.wait('@getTimeZone').then(() => {
      cy.getIframeBody()
        .contains(host)
        .find('svg')
        .invoke('width')
        .should('be.gte', 21);

      cy.getIframeBody()
        .contains(host)
        .find('svg')
        .invoke('height')
        .should('be.gte', 21);
    });

    cy.navigateTo({
      page: 'Services by host',
      rootItemNumber: 3,
      subMenu: 'Services'
    });

    cy.wait('@getTimeZone').then(() => {
      cy.getIframeBody()
        .contains(service)
        .find('svg')
        .invoke('width')
        .should('be.gte', 18);

      cy.getIframeBody()
        .contains(service)
        .find('svg')
        .invoke('height')
        .should('be.gte', 18);
    });
  }
);

Then(
  'the detailed information of the monitoring resources are displayed',
  () => {
    checkHostsAreMonitored([
      {
        name: host,
        status: 'up'
      }
    ]);

    checkServicesAreMonitored([
      {
        name: service,
        status: 'ok'
      }
    ]);

    cy.navigateTo({
      page: 'Resources Status',
      rootItemNumber: 1
    });

    cy.get('header').parent().children().eq(1).contains('OK').should('exist');

    cy.get('header').parent().children().eq(1).contains('Up').should('exist');

    cy.contains(service).click();

    cy.wait('@getResourceDetails');

    cy.get('#panel-content').should('be.visible');

    cy.get('#panel-content').contains('OK - 127.0.0.1');
  }
);

after(() => {
  cy.stopContainers();
});
