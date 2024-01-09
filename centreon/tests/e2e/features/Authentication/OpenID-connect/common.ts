/* eslint-disable cypress/no-unnecessary-waiting */

import { ActionClapi } from '../../../commons';

interface OidcConfigValues {
  authEndpoint: string;
  baseUrl: string;
  clientID: string;
  clientSecret: string;
  introspectionTokenEndpoint: string;
  loginAttrPath: string;
  scopes: string;
  tokenEndpoint: string;
}

const getOidcConfigValues = ({ providerAddress }): OidcConfigValues => ({
  authEndpoint: '/auth',
  baseUrl: `http://${providerAddress}:8080/realms/Centreon_SSO/protocol/openid-connect`,
  clientID: 'centreon-oidc-frontend',
  clientSecret: 'IKbUBottl5eoyhf0I5Io2nuDsTA85D50',
  introspectionTokenEndpoint: '/token/introspect',
  loginAttrPath: 'preferred_username',
  scopes: 'openid',
  tokenEndpoint: '/token'
});

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
  cy.contains('Enable OpenID Connect authentication').should('be.visible');

  return cy.getContainerIpAddress('openid').then((containerIpAddress) => {
    const oidcConfigValues = getOidcConfigValues({
      providerAddress: containerIpAddress
    });

    // Identity provider section
    cy.getByLabel({ label: 'Identity provider', tag: 'div' }).click();
    cy.getByLabel({ label: 'Base URL', tag: 'input' })
      .should('be.visible')
      .type(`{selectall}{backspace}${oidcConfigValues.baseUrl}`);
    cy.getByLabel({ label: 'Authorization endpoint', tag: 'input' })
      .should('be.visible')
      .type(`{selectall}{backspace}${oidcConfigValues.authEndpoint}`);
    cy.getByLabel({ label: 'Token endpoint', tag: 'input' })
      .should('be.visible')
      .type(`{selectall}{backspace}${oidcConfigValues.tokenEndpoint}`);
    cy.getByLabel({ label: 'Client ID', tag: 'input' })
      .should('be.visible')
      .type(`{selectall}{backspace}${oidcConfigValues.clientID}`);
    cy.getByLabel({ label: 'Client secret', tag: 'input' })
      .should('be.visible')
      .type(`{selectall}{backspace}${oidcConfigValues.clientSecret}`);
    cy.getByLabel({ label: 'Scopes', tag: 'input' })
      .should('be.visible')
      .type(`{selectall}{backspace}${oidcConfigValues.scopes}`);
    cy.getByLabel({ label: 'Login attribute path', tag: 'input' })
      .should('be.visible')
      .type(`{selectall}{backspace}${oidcConfigValues.loginAttrPath}`);
    cy.getByLabel({ label: 'Introspection token endpoint', tag: 'input' })
      .should('be.visible')
      .type(
        `{selectall}{backspace}${oidcConfigValues.introspectionTokenEndpoint}`
      );
    cy.getByLabel({
      label: 'Use basic authentication for token endpoint authentication',
      tag: 'input'
    }).uncheck();

    return cy
      .getByLabel({ label: 'Disable verify peer', tag: 'input' })
      .check();
  });
};

export {
  removeContact,
  initializeOIDCUserAndGetLoginPage,
  configureOpenIDConnect
};
