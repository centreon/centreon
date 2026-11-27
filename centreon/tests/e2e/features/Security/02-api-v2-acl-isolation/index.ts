import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';

import {
  applyConfigurationViaClapi,
  checkHostsAreMonitored
} from '../../../commons';
import {
  apiV2Base,
  buildHostAcknowledgedQuery,
  buildHostIdQuery
} from '../common';

interface MonitoringResource {
  name: string;
}

const userPassword = 'Centreon!2021RbtIdor';
const sessionLoginUrl =
  '/centreon/authentication/providers/configurations/local';
// Time for Engine to process an acknowledgement that would have been sent
const acknowledgementWaitingTime = 15_000;

let lastResponse: Cypress.Response<unknown> | null = null;
let listedResources: Array<string> = [];

beforeEach(() => {
  cy.startContainers();

  lastResponse = null;
  listedResources = [];
});

afterEach(() => {
  cy.stopContainers();
});

const clapi = (action: string, object: string, values: string): void => {
  cy.executeActionViaClapi({ bodyContent: { action, object, values } });
};

const getHostId = (hostName: string): Cypress.Chainable<number> =>
  cy
    .requestOnDatabase({
      database: 'centreon',
      query: buildHostIdQuery(hostName)
    })
    .then(([rows]) => {
      expect(rows, `host ${hostName}`).to.have.length(1);

      return cy.wrap(Number(rows[0].host_id), { log: false });
    });

// Session of the user, as the web interface opens it
const loginAs = (user: string): void => {
  cy.clearCookies();
  cy.request({
    body: { login: user, password: userPassword },
    log: false,
    method: 'POST',
    url: sessionLoginUrl
  })
    .its('status')
    .should('eq', 200);
};

Given(
  'the hosts {word} and {word} are each visible to a different access group',
  (firstHost: string, secondHost: string) => {
    [firstHost, secondHost].forEach((host) => {
      clapi('ADD', 'HOST', `${host};${host};127.0.0.1;generic-host;central;`);
      clapi('SETPARAM', 'HOST', `${host};active_checks_enabled;0`);
      clapi('SETPARAM', 'HOST', `${host};passive_checks_enabled;1`);
      clapi('SETPARAM', 'HOST', `${host};max_check_attempts;1`);
      clapi('ADD', 'ACLRESOURCE', `${host}-resource;${host}-resource`);
      clapi('GRANT_HOST', 'ACLRESOURCE', `${host}-resource;${host}`);
      clapi('ADD', 'ACLGROUP', `${host}-group;${host}-group`);
      clapi('ADDRESOURCE', 'ACLGROUP', `${host}-group;${host}-resource`);
    });
    applyConfigurationViaClapi();
    checkHostsAreMonitored([{ name: firstHost }, { name: secondHost }]);

    // Both hosts down: Engine accepts an acknowledgement only on a problem
    cy.submitResults(
      [firstHost, secondHost].map((host) => ({
        host,
        output: 'rbt idor down',
        status: 'down'
      }))
    );
    checkHostsAreMonitored([
      { name: firstHost, status: 'down' },
      { name: secondHost, status: 'down' }
    ]);
  }
);

Given(
  'the non-admin user {word} belongs only to the access group that sees {word}',
  (user: string, host: string) => {
    clapi(
      'ADD',
      'CONTACT',
      `${user};${user};${user}@localhost;${userPassword};0;1;en_US;local`
    );
    clapi('SETCONTACT', 'ACLGROUP', `${host}-group;${user}`);

    // The user may acknowledge hosts: a refusal can only come from the ACL
    clapi('ADD', 'ACLACTION', `${user}-actions;${user}-actions`);
    clapi('GRANT', 'ACLACTION', `${user}-actions;host_acknowledgement`);
    clapi('ADDACTION', 'ACLGROUP', `${host}-group;${user}-actions`);
    clapi('RELOAD', 'ACL', '');

    cy.applyAcl();
  }
);

When(
  '{word} requests the details of {word} by its id through API v2',
  (user: string, host: string) => {
    getHostId(host).then((hostId) => {
      loginAs(user);

      cy.request({
        failOnStatusCode: false,
        method: 'GET',
        url: `${apiV2Base}/monitoring/hosts/${hostId}`
      }).then((response) => {
        lastResponse = response;
      });
    });
  }
);

Then(
  'the response is 403 or 404 and does not contain {word}',
  (host: string) => {
    expect(lastResponse?.status).to.be.oneOf([403, 404]);
    expect(JSON.stringify(lastResponse?.body ?? '')).to.not.contain(host);
  }
);

When('{word} lists the monitoring resources', (user: string) => {
  loginAs(user);

  cy.request({
    method: 'GET',
    url: `${apiV2Base}/monitoring/resources?limit=100`
  }).then(({ body }) => {
    listedResources = (body.result as Array<MonitoringResource>).map(
      ({ name }) => name
    );
  });
});

Then(
  '{word} is listed and {word} is not',
  (allowed: string, denied: string) => {
    expect(listedResources).to.include(allowed);
    expect(listedResources).to.not.include(denied);
  }
);

When('{word} acknowledges {word} by its id', (user: string, host: string) => {
  getHostId(host).then((hostId) => {
    loginAs(user);

    // The per-host route: the bulk route silently skips a resource out of the ACL
    cy.request({
      body: {
        comment: 'rbt idor acknowledgement',
        is_notify_contacts: false,
        is_persistent_comment: true,
        is_sticky: true,
        with_services: false
      },
      failOnStatusCode: false,
      method: 'POST',
      url: `${apiV2Base}/monitoring/hosts/${hostId}/acknowledgements`
    }).then((response) => {
      lastResponse = response;
    });
  });
});

Then('the call is refused and {word} is not acknowledged', (host: string) => {
  expect(lastResponse?.status).to.be.within(400, 499);

  cy.wait(acknowledgementWaitingTime);

  cy.requestOnDatabase({
    database: 'centreon_storage',
    query: buildHostAcknowledgedQuery(host)
  }).then(([rows]) => {
    expect(rows, `host ${host} in real-time`).to.have.length(1);
    expect(Number(rows[0].acknowledged)).to.eq(0);
  });
});
