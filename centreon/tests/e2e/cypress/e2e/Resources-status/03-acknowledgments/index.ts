import { Given } from '@badeball/cypress-cucumber-preprocessor';

import {
  checkIfUserNotificationsAreEnabled,
  insertResourceFixtures
} from '../common';

Given('the user have the necessary rights to page Ressource Status', () => {
  cy.loginByTypeOfUser({
    jsonName: 'admin',
    preserveToken: true
  });

  cy.contains('Centreon-Server');
});

Given(
  'the user have the necessary rights to acknowledge & disacknowledge',
  () => {
    cy.getByLabel({ label: 'Acknowledge' }).should('exist');
  }
);

Given(
  'there are at least two resources of each type with a problem and notifications enabled for the user',
  () => {
    insertResourceFixtures();

    checkIfUserNotificationsAreEnabled();
  }
);
