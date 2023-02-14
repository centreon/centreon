import { executeActionViaClapi } from '../../commons';

const oidcConfigValues = {
  authEndpoint: '/auth',
  baseUrl: 'http://172.17.0.3:8080/realms/Centreon_SSO/protocol/openid-connect',
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
    .type(oidcConfigValues.baseUrl, { force: true });
  cy.getByLabel({ label: 'Authorization endpoint', tag: 'input' })
    .clear({ force: true })
    .type(oidcConfigValues.authEndpoint, { force: true });
  cy.getByLabel({ label: 'Token endpoint', tag: 'input' })
    .clear({ force: true })
    .type(oidcConfigValues.tokenEndpoint, { force: true });
  cy.getByLabel({ label: 'Client ID', tag: 'input' })
    .clear({ force: true })
    .type(oidcConfigValues.clientID, { force: true });
  cy.getByLabel({ label: 'Client secret', tag: 'input' })
    .clear({ force: true })
    .type(oidcConfigValues.clientSecret, { force: true });
  cy.getByLabel({ label: 'Login attribute path', tag: 'input' })
    .clear({ force: true })
    .type(oidcConfigValues.loginAttrPath, { force: true });
  cy.getByLabel({ label: 'Introspection token endpoint', tag: 'input' })
    .clear({ force: true })
    .type(oidcConfigValues.introspectionTokenEndpoint, { force: true });
  cy.getByLabel({
    label: 'Use basic authentication for token endpoint authentication',
    tag: 'input'
  }).uncheck({ force: true });

  return cy.getByLabel({ label: 'Disable verify peer', tag: 'input' }).check({
    force: true
  });
};

const getUserContactId = (userName: string): Cypress.Chainable => {
  const query = `SELECT contact_id FROM contact WHERE contact_alias = '${userName}';`;
  const command = `docker exec -i ${Cypress.env(
    'dockerName'
  )} mysql -ucentreon -pcentreon centreon -e "${query}"`;

  return cy
    .exec(command, { failOnNonZeroExit: true, log: true })
    .then(({ code, stdout, stderr }) => {
      if (!stderr && code === 0) {
        const idUser = parseInt(stdout.split('\n')[1], 10);

        return cy.wrap(idUser || '0');
      }

      return cy.log(`Can't execute command on database.`);
    });
};

const getAccessGroupId = (accessGroupName: string): Cypress.Chainable => {
  const query = `SELECT acl_group_id FROM acl_groups WHERE acl_group_name = '${accessGroupName}';`;
  const command = `docker exec -i ${Cypress.env(
    'dockerName'
  )} mysql -ucentreon -pcentreon centreon <<< "${query}"`;

  return cy
    .exec(command, { failOnNonZeroExit: true, log: true })
    .then(({ code, stdout, stderr }) => {
      if (!stderr && code === 0) {
        const accessGroupid = parseInt(stdout.split('\n')[1], 10);

        return cy.wrap(accessGroupid || '0');
      }

      return cy.log(`Can't execute command on database.`);
    });
};

export {
  removeContact,
  initializeOIDCUserAndGetLoginPage,
  configureOpenIDConnect,
  getUserContactId,
  getAccessGroupId
};
