import { executeActionViaClapi } from '../../commons';

const injectingWebSSOScriptsIntoContainer = (): Cypress.Chainable => {
  return cy.exec(
    `docker cp cypress/scripts/web-sso-commands.sh centreon-dev:/tmp/web-sso-commands.sh && docker exec -i ${Cypress.env(
      'dockerName'
    )} sh /tmp/web-sso-commands.sh`,
    { failOnNonZeroExit: false }
  );
};

const initializeWebSSOUserAndGetLoginPage = (): Cypress.Chainable => {
  return cy
    .fixture('resources/clapi/contact-web-sso/web-sso-authentication-user.json')
    .then((contact) => executeActionViaClapi(contact))
    .then(() => cy.visit(`${Cypress.config().baseUrl}`));
};

const removeWebSSOContact = (): Cypress.Chainable => {
  return cy.setUserTokenApiV1().then(() => {
    executeActionViaClapi({
      action: 'DEL',
      object: 'CONTACT',
      values: 'oidc'
    });
  });
};

export {
  initializeWebSSOUserAndGetLoginPage,
  removeWebSSOContact,
  injectingWebSSOScriptsIntoContainer
};
