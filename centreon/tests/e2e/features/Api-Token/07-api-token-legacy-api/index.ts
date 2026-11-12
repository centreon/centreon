import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { INTERCEPTORS } from 'fixtures/shared/constants/interceptors';

import { apiActionV1, getUserContactId } from '../../../commons';

const apiLatest = '/centreon/api/latest';
const adminLoginUrl = '/centreon/authentication/providers/configurations/local';
const contactPassword = 'Centreon!2021QaApiV1';
const checkWaitingTime = 15000;
const oneDayInSeconds = 86400;

let apiToken = '';
let apiTokenName = '';
let apiTokenUserId = 0;
let externalCommandMarker = '';

beforeEach(() => {
  cy.startContainers();

  cy.intercept({
    method: 'GET',
    url: INTERCEPTORS.api.navigation_list
  }).as('getNavigationList');

  apiToken = '';
  apiTokenName = '';
  apiTokenUserId = 0;
  externalCommandMarker = '';
});

afterEach(() => {
  cy.stopContainers();
});

// Session of the administrator, used to manage tokens with the current API.
const loginAsAdminViaApi = (): Cypress.Chainable =>
  cy.fixture('users/admin.json').then(({ login, password }) =>
    cy.request({
      body: { login, password },
      method: 'POST',
      url: adminLoginUrl
    })
  );

// The token must be the only credential of the call: drop any session cookie.
// "log: false" keeps the token out of the Cypress command log.
const requestWithToken = ({
  body,
  headerName,
  method = 'GET',
  url
}: {
  body?: object;
  headerName: 'centreon-auth-token' | 'X-AUTH-TOKEN';
  method?: 'GET' | 'POST';
  url: string;
}): Cypress.Chainable<Cypress.Response<unknown>> => {
  cy.clearCookies();

  return cy.request({
    body,
    failOnStatusCode: false,
    headers: {
      'Content-Type': 'application/json',
      [headerName]: apiToken
    },
    log: false,
    method,
    url
  });
};

const requestLegacyApi = ({
  body,
  method = 'GET',
  query
}: {
  body?: object;
  method?: 'GET' | 'POST';
  query: string;
}): Cypress.Chainable<Cypress.Response<unknown>> =>
  requestWithToken({
    body,
    headerName: 'centreon-auth-token',
    method,
    url: `${apiActionV1}?${query}`
  });

const clapi = (action: string, object: string, values: string): void => {
  cy.executeActionViaClapi({ bodyContent: { action, object, values } });
};

Given(
  'a non-admin user {string} allowed to use the API v1 with write access on host {string}',
  (userName: string, hostName: string) => {
    const aclGroup = `${userName}-ACLGROUP`;
    const aclAction = `${userName}-ACLACTION`;
    const aclResource = `${userName}-ACLRESOURCE`;

    clapi(
      'ADD',
      'CONTACT',
      `${userName};${userName};${userName}@localhost;${contactPassword};0;1;en_US;local`
    );
    clapi('SETPARAM', 'CONTACT', `${userName};reach_api;1`);
    clapi('SETPARAM', 'CONTACT', `${userName};reach_api_rt;1`);
    clapi('ADD', 'ACLGROUP', `${aclGroup};${aclGroup}`);
    clapi('SETCONTACT', 'ACLGROUP', `${aclGroup};${userName}`);
    clapi('ADD', 'ACLACTION', `${aclAction};${aclAction}`);
    clapi(
      'GRANT',
      'ACLACTION',
      `${aclAction};host_schedule_check|host_schedule_forced_check`
    );
    clapi('ADDACTION', 'ACLGROUP', `${aclGroup};${aclAction}`);
    clapi('ADD', 'ACLRESOURCE', `name=${aclResource};acl_group=${aclGroup}`);
    clapi('ADDRESOURCE', 'ACLGROUP', `${aclGroup};name=${aclResource}`);
    clapi('GRANT_HOST', 'ACLRESOURCE', `name=${aclResource};${hostName}`);
    clapi('RELOAD', 'ACL', '');
  }
);

Given(
  'an API token {string} generated for {string}',
  (tokenName: string, userName: string) => {
    apiTokenName = tokenName;

    getUserContactId(userName).then((userId) => {
      apiTokenUserId = Number(userId);

      loginAsAdminViaApi();

      const expirationDate = new Date(Date.now() + 7 * oneDayInSeconds * 1000);

      cy.request({
        body: {
          expiration_date: `${expirationDate.toISOString().split('.')[0]}Z`,
          name: tokenName,
          type: 'api',
          user_id: apiTokenUserId
        },
        log: false,
        method: 'POST',
        url: `${apiLatest}/administration/tokens`
      }).then((response) => {
        expect(response.status).to.eq(201);
        expect(response.body.token).to.be.a('string').and.not.be.empty;

        apiToken = response.body.token;
      });
    });
  }
);

When('the token is disabled from the Authentication Tokens page', () => {
  loginAsAdminViaApi();

  // Same call as the "Disable" toggle of the Authentication Tokens page
  cy.request({
    body: { is_revoked: true },
    method: 'PATCH',
    url: `${apiLatest}/administration/tokens/${apiTokenName}/users/${apiTokenUserId}`
  }).then((response) => {
    expect(response.status).to.eq(204);
  });
});

When('the token is moved past its expiration date', () => {
  cy.requestOnDatabase({
    database: 'centreon',
    params: [Math.floor(Date.now() / 1000) - oneDayInSeconds, apiTokenName],
    query: `UPDATE security_token st
      INNER JOIN security_authentication_tokens sat ON sat.provider_token_id = st.id
      SET st.expiration_date = ?
      WHERE sat.token_name = ?`
  });
});

Then(
  /^(?:a|the) realtime hosts listing on the API v1 with this token returns HTTP (\d+)$/,
  (status: string) => {
    requestLegacyApi({
      query: 'object=centreon_realtime_hosts&action=list'
    }).then((response) => {
      expect(response.status).to.eq(Number(status));

      if (Number(status) === 403) {
        expect(JSON.stringify(response.body)).to.contain('Unauthorized');
      }
    });
  }
);

Then(
  'a {string} external command sent to the API v1 with this token returns HTTP {int}',
  (command: string, status: number) => {
    const timestamp = Math.floor(Date.now() / 1000);

    // Unique value used afterwards to look for the command on the platform
    externalCommandMarker = `${command};Centreon-Server;${timestamp}`;

    requestLegacyApi({
      body: {
        commands: [
          {
            command: externalCommandMarker,
            poller_id: 1,
            timestamp
          }
        ]
      },
      method: 'POST',
      query: 'object=centreon_monitoring_externalcmd&action=send'
    }).then((response) => {
      expect(response.status).to.eq(status);

      if (status === 403) {
        expect(JSON.stringify(response.body)).to.contain('Unauthorized');
      }
    });
  }
);

Then('the external command is not forwarded to the poller', () => {
  expect(externalCommandMarker).to.not.eq('');

  // Let gorgone consume the centcore files, if a command had been written
  cy.wait(checkWaitingTime);

  // Not queued for gorgone, not handled by gorgone, not executed by the engine
  cy.execInContainer({
    command: `grep -rF "${externalCommandMarker}" /var/lib/centreon/centcore /var/log/centreon-gorgone/gorgoned.log /var/log/centreon-engine/centengine.log 2>/dev/null || true`,
    name: 'web'
  }).then(({ output }) => {
    expect(output.trim()).to.eq('');
  });
});

Then(
  'a call to the current API with this token returns HTTP {int}',
  (status: number) => {
    requestWithToken({
      headerName: 'X-AUTH-TOKEN',
      url: `${apiLatest}/monitoring/hosts`
    }).then((response) => {
      expect(response.status).to.eq(status);
    });
  }
);
