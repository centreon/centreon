import { ActionClapi } from '../../../commons';

const initializeWebSsoUserAndGetLoginPage = (): Cypress.Chainable => {
  return cy
    .fixture('resources/clapi/contact-web-sso/web-sso-authentication-user.json')
    .then((fixture: Array<ActionClapi>) => {
      fixture.forEach((action) =>
        cy.executeActionViaClapi({ bodyContent: action })
      );
    });
};

const removeWebSsoContact = (): Cypress.Chainable => {
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

export { initializeWebSsoUserAndGetLoginPage, removeWebSsoContact };
