import { Given } from '@badeball/cypress-cucumber-preprocessor';

import { getPoller, insertPollerConfigAclUser } from '../common';

Given(
  'I am granted the rights to access the poller page and export the configuration',
  () => {
    insertPollerConfigAclUser();
  }
);

Given('the administrator opens the authentication configuration menu', () => {
  cy.loginByTypeOfUser({ jsonName: 'user' });
});

Given('I the platform is configured with some resources', () => {
  cy.executeCommandsViaClapi('resources/clapi/host1/01-add.json');
});

Given('some pollers are created', () => {
  getPoller('Central')
    .as('pollerId')
    .then(() => {
      cy.get('@pollerId').should('be.greaterThan', 0);
    });
});

Given('some post-generation commands are configured for each poller', () => {
  cy.get('@pollerId').then((pollerId) => {
    cy.visit(`/centreon/main.php?p=60901&o=c&server_id=${pollerId}`);

    cy.getIframeBody().find('form #pollercmd_controls').click();

    cy.getIframeBody()
      .find('form #pollercmd_0')
      .select(2)
      .should('have.value', 'submit-host-check-result');

    cy.getIframeBody()
      .find('form input[name="submitC"]')
      .eq(0)
      .click()
      .reload();
  });
});
