import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { INTERCEPTORS } from 'fixtures/shared/constants/interceptors';

import {
  buildContactAdminQuery,
  buildPersonaClapiActions,
  isPersona,
  type Persona,
  parseApiBody,
  personaPassword
} from '../common';

interface Credentials {
  login: string;
  password: string;
}

const targetContact = 'rbt-clapi-target';
const targetPassword = 'Centreon!2021RbtClapi';

let lastResponse: Cypress.Response<unknown> | null = null;

beforeEach(() => {
  cy.startContainers();

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

const contactAdminFlag = (): Cypress.Chainable<string> =>
  cy
    .requestOnDatabase({
      database: 'centreon',
      query: buildContactAdminQuery(targetContact)
    })
    .then(([rows]) => {
      expect(rows, `contact ${targetContact}`).to.have.length(1);

      return cy.wrap(String(rows[0].contact_admin), { log: false });
    });

Given('a contact without admin rights exists', () => {
  cy.executeActionViaClapi({
    bodyContent: {
      action: 'ADD',
      object: 'CONTACT',
      values: `${targetContact};${targetContact};${targetContact}@localhost;${targetPassword};0;1;en_US;local`
    }
  });

  contactAdminFlag().should('eq', '0');
});

Given('the {word} user is logged in', (persona: string) => {
  if (!isPersona(persona)) {
    throw new Error(`Unknown persona "${persona}"`);
  }

  createPersona(persona);
  loginAsPersona(persona);
});

When('the user runs a CLAPI action through the internal API', () => {
  // The session cookie is the only credential of the call
  cy.request({
    body: {
      action: 'SETPARAM',
      object: 'CONTACT',
      values: `${targetContact};admin;1`
    },
    failOnStatusCode: false,
    headers: { 'Content-Type': 'application/json' },
    method: 'POST',
    url: INTERCEPTORS.api.internal_clapi
  }).then((response) => {
    lastResponse = response;
  });
});

Then('the action is {word}', (outcome: string) => {
  const body = parseApiBody(lastResponse?.body);

  if (outcome === 'accepted') {
    expect(lastResponse?.status).to.eq(200);
    expect(body).to.have.property('result');

    return;
  }

  if (outcome !== 'refused') {
    throw new Error(`Unknown outcome "${outcome}"`);
  }

  // CentreonClapi::authorize() accepts a super administrator only
  expect(lastResponse?.status).to.eq(403);
  expect(body).to.eq('Forbidden');
});

Then("the contact's admin rights are {word}", (state: string) => {
  const expectedFlags: Record<string, string> = {
    changed: '1',
    unchanged: '0'
  };

  if (expectedFlags[state] === undefined) {
    throw new Error(`Unknown contact state "${state}"`);
  }

  contactAdminFlag().should('eq', expectedFlags[state]);
});
