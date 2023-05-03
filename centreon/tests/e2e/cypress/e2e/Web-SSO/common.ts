const injectWebSSOScriptsIntoContainer = (): Cypress.Chainable => {
  return cy
    .exec(
      `docker cp cypress/scripts/web-sso-commands.sh ${Cypress.env(
        'dockerName'
      )}:/tmp/web-sso-commands.sh`
    )
    .then(() => {
      cy.exec(
        `docker exec -i ${Cypress.env(
          'dockerName'
        )} sh /tmp/web-sso-commands.sh`
      );
      cy.exec(`docker exec -i ${Cypress.env('dockerName')} pkill httpd`);
      cy.exec(
        `docker exec -i ${Cypress.env(
          'dockerName'
        )} sh /usr/share/centreon/container.d/60-apache.sh`,
        { failOnNonZeroExit: false }
      );
    });
};

const initializeWebSSOUserAndGetLoginPage = (): Cypress.Chainable => {
  return cy
    .fixture('resources/clapi/contact-web-sso/web-sso-authentication-user.json')
    .then((contact) => cy.executeActionViaClapi({ bodyContent: contact }));
};

const removeWebSSOContact = (): Cypress.Chainable => {
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
  initializeWebSSOUserAndGetLoginPage,
  removeWebSSOContact,
  injectWebSSOScriptsIntoContainer
};
