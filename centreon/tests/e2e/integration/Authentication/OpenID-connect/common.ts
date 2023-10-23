/* eslint-disable cypress/no-unnecessary-waiting */

import { ActionClapi } from '../../../../commons';

const oidcConfigValues = {
  authEndpoint: '/auth',
  baseUrl: 'http://172.17.0.3:8080/realms/Centreon_SSO/protocol/openid-connect',
  clientID: 'centreon-oidc-frontend',
  clientSecret: 'IKbUBottl5eoyhf0I5Io2nuDsTA85D50',
  introspectionTokenEndpoint: '/token/introspect',
  loginAttrPath: 'preferred_username',
  scopes: 'openid',
  tokenEndpoint: '/token'
};

const initializeOIDCUserAndGetLoginPage = (): Cypress.Chainable => {
  return cy
    .fixture('resources/clapi/contact-OIDC/OIDC-authentication-user.json')
    .then((fixture: Array<ActionClapi>) => {
      fixture.forEach((action) =>
        cy.executeActionViaClapi({ bodyContent: action })
      );
    });
};

const removeContact = (): Cypress.Chainable => {
  return cy.setUserTokenApiV1().then(() => {
    cy.executeActionViaClapi({
      bodyContent: {
        action: 'DEL',
        object: 'CONTACT',
        values: 'oidc'
      }
    });
  });
};

const configureOpenIDConnect = (): Cypress.Chainable => {
  cy.getByLabel({ label: 'Base URL', tag: 'input' }).type(
    `{selectall}{backspace}${oidcConfigValues.baseUrl}`,
    { force: true }
  );
  cy.getByLabel({ label: 'Authorization endpoint', tag: 'input' }).type(
    `{selectall}{backspace}${oidcConfigValues.authEndpoint}`,
    { force: true }
  );
  cy.getByLabel({ label: 'Token endpoint', tag: 'input' }).type(
    `{selectall}{backspace}${oidcConfigValues.tokenEndpoint}`,
    { force: true }
  );
  cy.getByLabel({ label: 'Client ID', tag: 'input' }).type(
    `{selectall}{backspace}${oidcConfigValues.clientID}`,
    { force: true }
  );
  cy.getByLabel({ label: 'Client secret', tag: 'input' }).type(
    `{selectall}{backspace}${oidcConfigValues.clientSecret}`,
    { force: true }
  );
  cy.getByLabel({ label: 'Scopes', tag: 'input' }).type(
    `{selectall}{backspace}${oidcConfigValues.scopes}`,
    { force: true }
  );
  cy.getByLabel({ label: 'Login attribute path', tag: 'input' }).type(
    `{selectall}{backspace}${oidcConfigValues.loginAttrPath}`,
    { force: true }
  );
  cy.getByLabel({ label: 'Introspection token endpoint', tag: 'input' }).type(
    `{selectall}{backspace}${oidcConfigValues.introspectionTokenEndpoint}`,
    { force: true }
  );
  cy.getByLabel({
    label: 'Use basic authentication for token endpoint authentication',
    tag: 'input'
  }).uncheck({ force: true });

  return cy.getByLabel({ label: 'Disable verify peer', tag: 'input' }).check({
    force: true
  });
};

export {
  removeContact,
  initializeOIDCUserAndGetLoginPage,
  configureOpenIDConnect
};
