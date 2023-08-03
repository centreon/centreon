/* eslint-disable cypress/unsafe-to-chain-command */
const keycloakURL = Cypress.env('OPENID_IMAGE_URL');

const SAMLConfigVales = {
  entityID: `${keycloakURL}/realms/Centreon_SSO`,
  loginAttribute: 'email',
  logoutURL: `${keycloakURL}/realms/Centreon_SSO/protocol/saml`,
  remoteLoginURL: `${keycloakURL}/realms/Centreon_SSO/protocol/saml/clients/platform-saml-2`,
  x509Certificate:
    'MIICrTCCAZUCBgGFOHJWgDANBgkqhkiG9w0BAQsFADAaMRgwFgYDVQQDDA9wbGF0Zm9ybS1zYW1sLTIwHhcNMjIxMjIyMDYwNjM1WhcNMzIxMjIyMDYwODE1WjAaMRgwFgYDVQQDDA9wbGF0Zm9ybS1zYW1sLTIwggEiMA0GCSqGSIb3DQEBAQUAA4IBDwAwggEKAoIBAQDwHVxEbRpy2/LC9AbGLQqyzTlm5HxeUJDNdI5pcZvzA8mHqRIhhe6uYL1zN9q/NhC8ptHQ323eE9j5FJRG1QSpbJ9ThhEleieLYNyxfjWv/wyI12ULswGlayFDTn/4ZZeRGMR5ELJ/a8kSWfESWxWS+a3Cu+WsJBDkA34BwTxMxFF9CcGQfxP05QqJOivDpRt9BqDnW2crsIlzYKwFkSCwUoJfyzfr96Tp6nI7rmOwIERjc2qNIJtybzKMUydlmZ/yN/qYZjB8kJibsianIOJYFwkdQZ/l2yztzlsgfcr78xIu7lfWdYFfdqdqrzFOpqzIeZXpItLPQV1Mvnneof/fAgMBAAEwDQYJKoZIhvcNAQELBQADggEBAN28Y2R3LIMWKogZt4f1tTy+5lVIHnhQz/ZEMGQCxiBJlT+wInhebuxRWyGcYAZsnwLgTYIntpPYkw+fpO6Grd2+Oz8+Uq2v13vlWouKGqf07UVImTebhVNAHNa78WuUVrbZzomh99HcFaSqlv/pCpTqFV0MOqyhSq2HB+Qkgb9XCbvzukVaLvtQ+ym5BS2SIDLF0wDBCpgRQeMzSD4IHB4jDaOvrxmyeJd8tY/98eTcLeplYepuf836VjedXW4UpvbbSsJSwDYF3j4gvUTGcTsq+2hhVrnEbrTA8bHIjQ40hUguEZ1Vrk5vvXXoScKf5BiTeSBN2cGHuFy20oHvstg='
};

const configureSAML = (): Cypress.Chainable => {
  cy.getByLabel({ label: 'Remote login URL', tag: 'input' }).type(
    SAMLConfigVales.remoteLoginURL,
    { force: true }
  );

  cy.getByLabel({ label: 'Issuer (Entity ID) URL', tag: 'input' }).type(
    SAMLConfigVales.entityID,
    { force: true }
  );

  cy.getByLabel({
    label: 'Copy/paste x.509 certificate',
    tag: 'textarea'
  }).type(SAMLConfigVales.x509Certificate);

  cy.getByLabel({
    label: 'User ID (login) attribute for Centreon user',
    tag: 'input'
  }).type(SAMLConfigVales.loginAttribute, { force: true });

  cy.getByLabel({
    label: 'Both identity provider and Centreon UI',
    tag: 'input'
  }).check();

  return cy
    .getByLabel({ label: 'Logout URL', tag: 'input' })
    .type(SAMLConfigVales.logoutURL, { force: true });
};

const navigateToSAMLConfigPage = (): Cypress.Chainable => {
  cy.navigateTo({
    page: 'Authentication',
    rootItemNumber: 4
  })
    .get('div[role="tablist"] button:nth-child(4)')
    .click();

  cy.wait('@getSAMLProvider');

  return cy
    .getByLabel({ label: 'Identity provider' })
    .eq(0)
    .contains('Identity provider')
    .click();
};

const initializeSAMLUser = (): Cypress.Chainable => {
  return cy
    .fixture('resources/clapi/contact-SAML/SAML-authentication-user.json')
    .then((contact) => cy.executeActionViaClapi({ bodyContent: contact }));
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

export {
  initializeSAMLUser,
  removeContact,
  configureSAML,
  navigateToSAMLConfigPage
};
