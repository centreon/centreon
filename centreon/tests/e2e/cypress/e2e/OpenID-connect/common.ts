import { executeActionViaClapi } from '../../commons';

const oidcConfigValues = {
  authEndpoint: '/auth',
  baseUrl:
    'http://10.25.11.254:8080/auth/realms/Centreon_SSO/protocol/openid-connect',
  clientID: 'centreon-oidc-frontend',
  clientSecret: 'IKbUBottl5eoyhf0I5Io2nuDsTA85D50',
  introspectionTokenEndpoint: '/token/introspect',
  loginAttrPath: 'preferred_username',
  tokenEndpoint: '/token'
};

const initializeOIDCUserAndGetLoginPage = (): Cypress.Chainable => {
  return cy
    .fixture('resources/clapi/contact-OIDC/OIDC-authentication-user.json')
    .then((contact) => executeActionViaClapi(contact))
    .then(() => cy.visit(`${Cypress.config().baseUrl}`));
};

const removeContact = (): Cypress.Chainable => {
  return cy.setUserTokenApiV1().then(() => {
    executeActionViaClapi({
      action: 'DEL',
      object: 'CONTACT',
      values: 'oidc'
    });
  });
};

const configureOpenIDConnect = (): Cypress.Chainable => {
  cy.getByLabel({ label: 'Base URL', tag: 'input' })
    .clear({ force: true })
    .type(oidcConfigValues.baseUrl);
  cy.getByLabel({ label: 'Authorization endpoint', tag: 'input' })
    .clear({ force: true })
    .type(oidcConfigValues.authEndpoint);
  cy.getByLabel({ label: 'Token endpoint', tag: 'input' })
    .clear({ force: true })
    .type(oidcConfigValues.tokenEndpoint);
  cy.getByLabel({ label: 'Client ID', tag: 'input' })
    .clear({ force: true })
    .type(oidcConfigValues.clientID);
  cy.getByLabel({ label: 'Client secret', tag: 'input' })
    .clear({ force: true })
    .type(oidcConfigValues.clientSecret);
  cy.getByLabel({ label: 'Login attribute path', tag: 'input' })
    .clear({ force: true })
    .type(oidcConfigValues.loginAttrPath);
  cy.getByLabel({ label: 'Introspection token endpoint', tag: 'input' })
    .clear({ force: true })
    .type(oidcConfigValues.introspectionTokenEndpoint);
  cy.getByLabel({
    label: 'Use basic authentication for token endpoint authentication',
    tag: 'input'
  }).uncheck();
  cy.getByLabel({ label: 'Disable verify peer', tag: 'input' }).check();

  return cy.getByLabel({ label: 'save button', tag: 'button' }).click();
};

export {
  removeContact,
  initializeOIDCUserAndGetLoginPage,
  configureOpenIDConnect
};
