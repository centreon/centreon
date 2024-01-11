import { Given, When, Then } from '@badeball/cypress-cucumber-preprocessor';

import { searchInput, setUserFilter } from '../common';
import { checkServicesAreMonitored } from '../../../commons';

const serviceOk = 'service_test_ok';
const serviceInDtName = 'service_downtime_1';
const secondServiceInDtName = 'service_downtime_2';
const serviceInAcknowledgementName = 'service_ack_1';

beforeEach(() => {
  cy.intercept({
    method: 'POST',
    url: '/centreon/api/latest/authentication/providers/configurations/local'
  }).as('postLocalAuthentication');

  cy.intercept({
    method: 'GET',
    url: '/centreon/api/internal.php?object=centreon_topology&action=navigationList'
  }).as('getNavigationList');

  cy.intercept({
    method: 'GET',
    url: '/centreon/api/latest/users/filters/events-view?page=1&limit=100'
  }).as('getFilters');

  cy.intercept('/centreon/api/latest/monitoring/resources*').as(
    'monitoringEndpoint'
  );

  cy.startContainers();

  cy.loginByTypeOfUser({
    jsonName: 'admin',
    loginViaApi: true
  }).wait('@getFilters');

  cy.disableListingAutoRefresh();

  cy.addHost({
    activeCheckEnabled: false,
    checkCommand: 'check_centreon_cpu',
    name: 'host1',
    template: 'generic-host'
  })
    .addService({
      activeCheckEnabled: false,
      host: 'host1',
      maxCheckAttempts: 1,
      name: serviceInDtName,
      template: 'SNMP-DISK-/'
    })
    .addService({
      activeCheckEnabled: false,
      host: 'host1',
      maxCheckAttempts: 1,
      name: secondServiceInDtName,
      template: 'Ping-LAN'
    })
    .addService({
      activeCheckEnabled: false,
      host: 'host1',
      maxCheckAttempts: 1,
      name: serviceInAcknowledgementName,
      template: 'SNMP-DISK-/'
    })
    .addService({
      activeCheckEnabled: false,
      host: 'host1',
      maxCheckAttempts: 1,
      name: serviceOk,
      template: 'Ping-LAN'
    })
    .applyPollerConfiguration();

  checkServicesAreMonitored([
    {
      name: serviceOk
    }
  ]);

  cy.submitResults([
    {
      host: 'host1',
      output: 'submit_status_2',
      service: serviceInDtName,
      status: 'critical'
    },
    {
      host: 'host1',
      output: 'submit_status_2',
      service: secondServiceInDtName,
      status: 'critical'
    },
    {
      host: 'host1',
      output: 'submit_status_2',
      service: serviceInAcknowledgementName,
      status: 'critical'
    },
    {
      host: 'host1',
      output: 'submit_status_0',
      service: serviceOk,
      status: 'ok'
    }
  ]);

  checkServicesAreMonitored([
    {
      name: serviceInDtName,
      status: 'critical'
    },
    {
      name: secondServiceInDtName,
      status: 'critical'
    },
    {
      name: serviceInAcknowledgementName,
      status: 'critical'
    },
    {
      name: serviceOk,
      status: 'ok'
    }
  ]);
});

Then('the unhandled problems filter is selected', (): void => {
  cy.visit('/').wait('@getFilters');
  cy.contains('Unhandled alerts');
});

Then('only non-ok resources are displayed', () => {
  cy.contains(serviceInAcknowledgementName);
  cy.contains(serviceOk).should('not.exist');
  cy.contains('Critical');
  cy.get('header').parent().children().eq(1).contains('Ok').should('not.exist');
  cy.get('header').parent().children().eq(1).contains('Up').should('not.exist');
});

When('I put in some criterias', () => {
  cy.loginByTypeOfUser({
    jsonName: 'admin',
    loginViaApi: true
  });

  const searchValue = `type:service name:${serviceInDtName}`;

  cy.get(searchInput).type(`{selectall}{backspace}${searchValue}{enter}`);
});

Then(
  'only the Resources matching the selected criterias are displayed in the result',
  () => {
    cy.contains(serviceInDtName);
  }
);

Given('a saved custom filter', () => {
  cy.fixture('resources/filters.json').then((filters) =>
    setUserFilter(filters)
  );

  cy.visit('centreon/monitoring/resources').wait([
    '@getFilters',
    '@monitoringEndpoint'
  ]);

  cy.contains('Unhandled alerts').should('be.visible');

  cy.get(`div[data-testid="selectedFilter"]`).click();

  cy.contains('OK services');
});

When('I select the custom filter', () => {
  cy.contains('OK services').click();
});

Then(
  'only Resources matching the selected filter are displayed in the result',
  () => {
    cy.contains(serviceOk).should('be.visible');
  }
);

afterEach(() => {
  cy.stopContainers();
});
