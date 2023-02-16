import { When, Then } from '@badeball/cypress-cucumber-preprocessor';

import {
  stateFilterContainer,
  actionBackgroundColors,
  actions,
  insertResourceFixtures,
  tearDownResource
} from '../common';
import { submitResultsViaClapi } from '../../../commons';

const serviceInAcknowledgementName = 'service_test_ack';
const serviceInDowntimeName = 'service_test_dt';

before(() => {
  insertResourceFixtures().then(submitResultsViaClapi);
});

beforeEach(() => {
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/internal.php?object=centreon_topology&action=navigationList'
  }).as('getNavigationList');

  cy.loginByTypeOfUser({
    jsonName: 'admin',
    preserveToken: true
  });

  cy.get('[aria-label="Add columns"]').click();

  cy.contains('State').click();

  cy.get('[aria-label="Add columns"]').click();
});

When('I select the acknowledge action on a problematic Resource', () => {
  cy.contains(serviceInAcknowledgementName)
    .parent()
    .parent()
    .find('input[type="checkbox"]:first')
    .click();

  cy.getByLabel({ label: actions.acknowledge }).last().click();

  cy.get('textarea').should('be.visible');
  cy.get('button').contains('Acknowledge').click();
});

Then('the problematic Resource is displayed as acknowledged', () => {
  cy.get(stateFilterContainer).click().get('[data-value="all"]').click();
  cy.waitUntil(() => {
    return cy
      .refreshListing()
      .then(() => cy.contains(serviceInAcknowledgementName))
      .parent()
      .then((val) => {
        return (
          val.css('background-color') === actionBackgroundColors.acknowledge
        );
      });
  }, {
    timeout: 15000
  });
});

When('I select the downtime action on a problematic Resource', () => {
  cy.contains(serviceInDowntimeName)
    .parent()
    .parent()
    .find('input[type="checkbox"]:first')
    .click();

  cy.getByLabel({ label: actions.setDowntime }).last().click();

  cy.get('textarea').should('be.visible');
  cy.get('button').contains(`${actions.setDowntime}`).click();
});

Then('the problematic Resource is displayed as in downtime', () => {
  cy.get(stateFilterContainer).click();
  cy.get('li[data-value="all"]').click({ force: true });

  cy.waitUntil(() => {
    return cy
      .refreshListing()
      .then(() => cy.contains(serviceInDowntimeName))
      .parent()
      .then((val) => {
        return (
          val.css('background-color') === actionBackgroundColors.inDowntime
        );
      });
  }, {
    timeout: 60000
  });
});

after(() => {
  tearDownResource().then(() => cy.reload());
});
