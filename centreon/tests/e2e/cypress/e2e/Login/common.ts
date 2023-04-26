import { applyConfigurationViaClapi, insertFixture } from '../../commons';

const initializeContactData = (): Cypress.Chainable => {
  const files = ['resources/clapi/contact1/01-add.json'];

  return cy.wrap(Promise.all(files.map(insertFixture)));
};

const insertContactFixture = (): Cypress.Chainable => {
  return initializeContactData()
    .then(applyConfigurationViaClapi)
    .then(() => cy.visit(`${Cypress.config().baseUrl}`))
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

export { insertContactFixture, removeContact };
