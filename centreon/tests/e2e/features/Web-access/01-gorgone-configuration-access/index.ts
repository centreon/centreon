import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { INTERCEPTORS } from 'fixtures/shared/constants/interceptors';

import {
  buildCentralPollerQuery,
  buildPersonaClapiActions,
  gorgoneConfigurationTitle,
  gorgoneRefusalMessages,
  isPersona,
  type Persona,
  personaPassword
} from '../common';

interface Credentials {
  login: string;
  password: string;
}

interface Poller {
  id: number;
  name: string;
}

let poller: Poller = { id: 0, name: '' };
let lastResponse: Cypress.Response<unknown> | null = null;

beforeEach(() => {
  cy.startContainers();

  poller = { id: 0, name: '' };
  lastResponse = null;
});

afterEach(() => {
  cy.stopContainers();
});

const createPersona = (persona: Persona): void => {
  const actions = buildPersonaClapiActions(persona);

  if (actions.length === 0) {
    return;
  }

  actions.forEach((bodyContent) => {
    cy.executeActionViaClapi({ bodyContent });
  });
  cy.executeActionViaClapi({
    bodyContent: { action: 'RELOAD', object: 'ACL', values: '' }
  });

  // Compute the centreon_acl table now rather than waiting for the cron
  cy.applyAcl();
};

// Session of the persona, as the login page opens it
const loginAsPersona = (persona: Persona): void => {
  const credentials: Cypress.Chainable<Credentials> =
    persona === 'super_admin'
      ? cy.fixture('users/admin.json')
      : cy.wrap<Credentials>(
          { login: String(persona), password: personaPassword },
          { log: false }
        );

  credentials.then(({ login, password }) => {
    cy.clearCookies();
    cy.request({
      body: { login, password },
      log: false,
      method: 'POST',
      url: INTERCEPTORS.api.local_authentication
    })
      .its('status')
      .should('eq', 200);
  });
};

Given('a poller is configured on the platform', () => {
  cy.requestOnDatabase({
    database: 'centreon',
    query: buildCentralPollerQuery()
  }).then(([rows]) => {
    expect(rows, 'central poller').to.have.length(1);

    poller = { id: Number(rows[0].id), name: String(rows[0].name) };
  });
});

Given('the {word} user is logged in', (persona: string) => {
  if (!isPersona(persona)) {
    throw new Error(`Unknown persona "${persona}"`);
  }

  createPersona(persona);
  loginAsPersona(persona);
});

When('the user requests the Gorgone configuration of the poller', () => {
  // Request sent by the Gorgone configuration icon of Configuration > Pollers
  cy.request({
    failOnStatusCode: false,
    method: 'GET',
    url: `${INTERCEPTORS.pages.gorgone_configuration_popup}?id=${poller.id}`
  }).then((response) => {
    lastResponse = response;
  });
});

Then('the Gorgone configuration is {word}', (outcome: string) => {
  const body = String(lastResponse?.body ?? '');

  expect(lastResponse?.status).to.eq(200);

  if (outcome === 'returned') {
    expect(body).to.contain(gorgoneConfigurationTitle);
    expect(body).to.contain(`gorgoned-${poller.name}`);
    expect(body).to.contain(`id: ${poller.id}`);

    return;
  }

  if (outcome !== 'refused') {
    throw new Error(`Unknown outcome "${outcome}"`);
  }

  expect(gorgoneRefusalMessages).to.include(body.trim());
  expect(body).to.not.contain(gorgoneConfigurationTitle);
  expect(body).to.not.contain('gorgonecore');
});
