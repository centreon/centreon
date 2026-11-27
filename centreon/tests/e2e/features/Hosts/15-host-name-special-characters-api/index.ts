import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';

import {
  buildCentralPollerIdQuery,
  buildGeneratedHostsFile,
  buildHostCountByNamePrefixQuery,
  buildHostNameByIdQuery,
  definedObjectTypes,
  hostAttributeValues,
  hostDefinitions
} from '../common';

const apiV2Base = '/centreon/api/latest';

const operator = 'rbt-api-operator';
const operatorPassword = 'Centreon!2021RbtApi';
const aclGroup = 'rbt-api-group';
const aclMenu = 'rbt-api-menus';
const aclResources = 'rbt-api-resources';

// Spaces are stored as underscores, the other printable characters as sent
const createdName = 'rbt-hôte é-1 [a]{b}';
const expectedCreatedName = 'rbt-hôte_é-1_[a]{b}';
const updatedName = 'rbt-hôte renommé-2 [c]';
const expectedUpdatedName = 'rbt-hôte_renommé-2_[c]';
// A refused name would carry this directive into the generated file
const extraDirective = 'rbt_extra_directive';
const refusedNamePrefix = 'rbt-line';
const nameWithLineBreak = `${refusedNamePrefix}\n${extraDirective} 1`;
const nameWithTab = `rbt-tab\t${extraDirective} 1`;
const controlCharactersMessage = 'must not contain control characters';

interface ApiResponse {
  body: unknown;
  status: number;
}

let token = '';
let pollerId = 0;
let hostId = 0;
let lastResponse: ApiResponse = { body: null, status: 0 };
let hostsFile = '';

beforeEach(() => {
  cy.startContainers();

  token = '';
  pollerId = 0;
  hostId = 0;
  lastResponse = { body: null, status: 0 };
  hostsFile = '';
});

afterEach(() => {
  cy.stopContainers();
});

const clapi = (action: string, object: string, values: string): void => {
  cy.executeActionViaClapi({ bodyContent: { action, object, values } });
};

// A request of the operator, authenticated by its API token. The options are
// built when the request runs, once the ids of the previous steps are known
const operatorRequest = (
  buildOptions: () => {
    body?: object;
    contentType?: string;
    method: string;
    url: string;
  }
): Cypress.Chainable<ApiResponse> =>
  cy.then(() => {
    const {
      body,
      contentType = 'application/json',
      method,
      url
    } = buildOptions();

    return cy
      .request({
        body,
        failOnStatusCode: false,
        headers: { 'Content-Type': contentType, 'X-AUTH-TOKEN': token },
        method,
        url: `${apiV2Base}${url}`
      })
      .then(({ body: responseBody, status }) => {
        lastResponse = { body: responseBody, status };

        return cy.wrap(lastResponse, { log: false });
      });
  });

const storedHostName = (): Cypress.Chainable<string> =>
  cy
    .then(() =>
      cy.requestOnDatabase({
        database: 'centreon',
        query: buildHostNameByIdQuery(hostId)
      })
    )
    .then(([rows]) => {
      expect(rows, `host #${hostId}`).to.have.length(1);

      return cy.wrap(String(rows[0].host_name), { log: false });
    });

const expectValidationError = (): void => {
  expect(lastResponse.status, 'status code').to.eq(422);
  expect(JSON.stringify(lastResponse.body)).to.contain(
    controlCharactersMessage
  );
};

Given('a non-admin configuration operator is logged in to the API', () => {
  clapi(
    'ADD',
    'CONTACT',
    `${operator};${operator};${operator}@localhost;${operatorPassword};0;1;en_US;local`
  );
  clapi('SETPARAM', 'CONTACT', `${operator};reach_api;1`);

  // Write access to the hosts, read access to the pollers to export them
  clapi('ADD', 'ACLMENU', `${aclMenu};${aclMenu}`);
  clapi('GRANTRW', 'ACLMENU', `${aclMenu};1;Configuration;Hosts`);
  clapi('GRANTRW', 'ACLMENU', `${aclMenu};1;Configuration;Pollers`);
  clapi('ADD', 'ACLRESOURCE', `${aclResources};${aclResources}`);
  clapi('ADDFILTER_INSTANCE', 'ACLRESOURCE', `${aclResources};Central`);
  clapi('ADD', 'ACLGROUP', `${aclGroup};${aclGroup}`);
  clapi('SETCONTACT', 'ACLGROUP', `${aclGroup};${operator}`);
  clapi('ADDMENU', 'ACLGROUP', `${aclGroup};${aclMenu}`);
  clapi('ADDRESOURCE', 'ACLGROUP', `${aclGroup};${aclResources}`);
  clapi('RELOAD', 'ACL', '');
  cy.applyAcl();

  cy.request({
    body: {
      security: { credentials: { login: operator, password: operatorPassword } }
    },
    method: 'POST',
    url: `${apiV2Base}/login`
  }).then(({ body }) => {
    expect(body.contact.is_admin, `${operator} is an administrator`).to.eq(
      false
    );

    token = body.security.token;
  });
});

Given('a poller is available to monitor hosts', () => {
  cy.requestOnDatabase({
    database: 'centreon',
    query: buildCentralPollerIdQuery()
  }).then(([rows]) => {
    expect(rows, 'central poller').to.have.length(1);

    pollerId = Number(rows[0].id);
  });
});

When(
  'the operator creates a host whose name contains printable special characters',
  () => {
    operatorRequest(() => ({
      body: { address: '127.0.0.1', name: createdName, poller_id: pollerId },
      method: 'POST',
      url: '/configuration/hosts'
    }));
  }
);

Then('the host is created with that name', () => {
  cy.then(() => {
    expect(lastResponse.status, 'status code').to.eq(201);

    hostId = Number((lastResponse.body as { id: number }).id);
    expect(hostId, 'id of the created host').to.be.greaterThan(0);
  });

  storedHostName().should('eq', expectedCreatedName);
});

When(
  'the operator updates the host name with other printable special characters',
  () => {
    operatorRequest(() => ({
      body: { name: updatedName },
      contentType: 'application/merge-patch+json',
      method: 'PATCH',
      url: `/configuration/hosts/${hostId}`
    }));
  }
);

Then('the host shows the updated name', () => {
  cy.then(() => {
    expect(lastResponse.status, 'status code').to.eq(204);
  });

  storedHostName().should('eq', expectedUpdatedName);
});

When('the operator creates a host whose name contains a line break', () => {
  operatorRequest(() => ({
    body: {
      address: '127.0.0.1',
      name: nameWithLineBreak,
      poller_id: pollerId
    },
    method: 'POST',
    url: '/configuration/hosts'
  }));
});

Then('the creation is refused with a validation error', () => {
  cy.then(() => expectValidationError());
});

When(
  'the operator updates the host name with a name containing a tab character',
  () => {
    operatorRequest(() => ({
      body: { name: nameWithTab },
      contentType: 'application/merge-patch+json',
      method: 'PATCH',
      url: `/configuration/hosts/${hostId}`
    }));
  }
);

Then('the update is refused with a validation error', () => {
  cy.then(() => expectValidationError());
});

Then('the host keeps its previous name', () => {
  storedHostName().should('eq', expectedUpdatedName);
});

When(
  'the operator generates and reloads the configuration of the poller',
  () => {
    operatorRequest(() => ({
      method: 'GET',
      url: `/configuration/monitoring-servers/${pollerId}/generate-and-reload`
    }));
  }
);

Then('the generation ends without error', () => {
  cy.then(() => {
    expect(lastResponse.status, 'status code').to.eq(204);
  });

  cy.then(() =>
    cy
      .execInContainer({
        command: `cat ${buildGeneratedHostsFile(pollerId)}`,
        name: 'web'
      })
      .then(({ output }) => {
        hostsFile = output;
      })
  );
});

Then(
  'the generated configuration contains the host once under its updated name',
  () => {
    const hostNames = hostAttributeValues(hostsFile, 'host_name');

    expect(
      hostNames.filter((name) => name === expectedUpdatedName),
      `hosts named ${expectedUpdatedName}`
    ).to.have.length(1);
    expect(hostNames, 'generated host names').not.to.include(
      expectedCreatedName
    );
  }
);

Then(
  'the generated configuration contains no object created from a host name',
  () => {
    expect(
      [...new Set(definedObjectTypes(hostsFile))],
      'object types of the hosts file'
    ).to.deep.eq(['host']);
    expect(
      hostAttributeValues(hostsFile, 'host_name'),
      'one host_name per host definition'
    ).to.have.length(hostDefinitions(hostsFile).length);
    expect(hostsFile, 'directive coming from a refused name').not.to.contain(
      extraDirective
    );

    // The refused creation left no host behind either
    cy.requestOnDatabase({
      database: 'centreon',
      query: buildHostCountByNamePrefixQuery(refusedNamePrefix)
    }).then(([rows]) => {
      expect(Number(rows[0].count), 'hosts with a refused name').to.eq(0);
    });
  }
);
