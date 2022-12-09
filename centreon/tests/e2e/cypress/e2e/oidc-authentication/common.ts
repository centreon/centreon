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

export { removeContact, initializeOIDCUserAndGetLoginPage, oidcConfigValues };
