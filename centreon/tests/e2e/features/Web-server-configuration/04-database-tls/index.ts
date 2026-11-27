import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { INTERCEPTORS } from 'fixtures/shared/constants/interceptors';

import {
  buildRestartWebServicesCommand,
  buildSecureTransportCommand,
  buildSetDatabaseTlsOptionsCommand,
  buildTlsAcceptedConnectionsQuery,
  buildTlsAvailabilityQuery,
  databaseTlsOptions,
  webEnvFile
} from '../common';

interface Credentials {
  login: string;
  password: string;
}

const apiCallsCount = 3;

let tlsAcceptsBeforeLogin = 0;

beforeEach(() => {
  cy.startContainers();

  tlsAcceptsBeforeLogin = 0;

  cy.intercept({
    method: 'GET',
    url: INTERCEPTORS.api.navigation_list
  }).as('getNavigationList');
});

afterEach(() => {
  cy.stopContainers();
});

const tlsAcceptedConnections = (): Cypress.Chainable<number> =>
  cy
    .requestOnDatabase({
      database: 'centreon',
      query: buildTlsAcceptedConnectionsQuery()
    })
    .then(([rows]) =>
      cy.wrap(Number(rows[0].Value), {
        log: false
      })
    );

const callApi = (): void => {
  Array.from({ length: apiCallsCount }).forEach(() => {
    cy.request({
      method: 'GET',
      url: `${INTERCEPTORS.api.monitor_resources}?limit=1`
    })
      .its('status')
      .should('eq', 200);
  });
};

Given('a platform whose database accepts TLS connections', () => {
  cy.requestOnDatabase({
    database: 'centreon',
    query: buildTlsAvailabilityQuery()
  }).then(([rows]) => {
    expect(rows).to.have.length(1);
    expect(rows[0].Value).to.eq('YES');
  });
});

Given(
  "the database TLS options are set in the web application's .env file",
  () => {
    cy.execInContainer({
      command: buildSetDatabaseTlsOptionsCommand(),
      name: 'web'
    });

    cy.execInContainer({ command: `cat ${webEnvFile}`, name: 'web' }).then(
      ({ output }) => {
        Object.entries(databaseTlsOptions).forEach(([key, value]) => {
          expect(String(output)).to.contain(`${key}=${value}`);
        });
      }
    );
  }
);

Given('the web services are restarted', () => {
  cy.execInContainer({
    command: buildRestartWebServicesCommand(
      Cypress.env('WEB_IMAGE_OS').includes('alma')
    ),
    name: 'web'
  });
});

When('the administrator logs in to the web interface and calls the API', () => {
  tlsAcceptedConnections().then((count) => {
    tlsAcceptsBeforeLogin = count;
  });

  cy.loginByTypeOfUser({ jsonName: 'admin' });
  callApi();
});

Then('the web application sessions on the database use TLS', () => {
  tlsAcceptedConnections().then((count) => {
    expect(count, 'TLS connections accepted by the database').to.be.greaterThan(
      tlsAcceptsBeforeLogin
    );
  });
});

Then(
  'the login still succeeds when the database requires secure transport',
  () => {
    // A clear-text connection of the web application is refused from now on
    cy.execInContainer({
      command: buildSecureTransportCommand(true),
      name: 'web'
    });

    cy.fixture('users/admin.json').then(({ login, password }: Credentials) => {
      cy.clearCookies();
      cy.request({
        body: { login, password },
        failOnStatusCode: false,
        log: false,
        method: 'POST',
        url: INTERCEPTORS.api.local_authentication
      })
        .its('status')
        .should('eq', 200);
    });
    callApi();

    cy.execInContainer({
      command: buildSecureTransportCommand(false),
      name: 'web'
    });
  }
);
