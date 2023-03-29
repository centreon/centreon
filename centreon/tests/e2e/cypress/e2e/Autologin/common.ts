import { applyConfigurationViaClapi } from '../../commons';

const initializeConfigACLAndGetLoginPage = (): Cypress.Chainable => {
  return cy
    .executeCommandsViaClapi(
      'resources/clapi/config-ACL/autologin-configuration-acl-user.json'
    )
    .then(applyConfigurationViaClapi)
    .then(() => cy.fixture('users/admin.json'));
};

const removeContact = (): Cypress.Chainable => {
  return cy.setUserTokenApiV1().then(() => {
    cy.executeActionViaClapi({
      bodyContent: {
        action: 'DEL',
        object: 'CONTACT',
        values: 'user1'
      }
    });
  });
};

export { removeContact, initializeConfigACLAndGetLoginPage };
