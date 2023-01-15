import { executeActionViaClapi } from '../../commons';

const initializeWebSSOUserAndGetLoginPage = (): Cypress.Chainable => {
  return cy
    .fixture('resources/clapi/contact-web-sso/web-sso-authentication-user.json')
    .then((contact) => executeActionViaClapi(contact))
    .then(() => cy.visit(`${Cypress.config().baseUrl}`));
};

export { initializeWebSSOUserAndGetLoginPage };
