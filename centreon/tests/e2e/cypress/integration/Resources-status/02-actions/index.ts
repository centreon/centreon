import { When, Then } from 'cypress-cucumber-preprocessor/steps';

import {
  stateFilterContainer,
  actionBackgroundColors,
  actions,
  insertResourceFixtures,
  tearDownResource,
} from '../common';
import { refreshListing } from '../../../support/centreonData';
import { submitResultsViaClapi } from '../../../commons';

const serviceName = 'service_test_ack';
const serviceInDowntimeName = 'service_test_dt';

before(() => {
  insertResourceFixtures().then(() => submitResultsViaClapi());
});

When('I select the acknowledge action on a problematic Resource', () => {
  cy.contains(serviceName)
    .parents('div[role="row"]:first')
    .find('input[type="checkbox"]:first')
    .click();

  cy.get(`[aria-label="${actions.acknowledge}"]`)
    .parent('button')
    .first()
    .should('be.enabled')
    .click();

  cy.get('textarea').should('be.visible');
  cy.get('button').contains('Acknowledge').click();
});

Then('the problematic Resource is displayed as acknowledged', () => {
  cy.get(stateFilterContainer).click().get('[data-value="all"]').click();
  cy.waitUntil(() => {
    return refreshListing()
      .then(() => cy.contains(serviceName))
      .parent()
      .parent()
      .parent()
      .then((val) => {
        return (
          val.css('background-color') === actionBackgroundColors.acknowledge
        );
      });
  }, {
    timeout: 30000,
  });
});

When('I select the downtime action on a problematic Resource', () => {
  cy.contains(serviceInDowntimeName)
    .parents('div[role="row"]:first')
    .find('input[type="checkbox"]:first')
    .click();

  cy.get(`[aria-label="${actions.setDowntime}"]`)
    .parent('button')
    .first()
    .should('be.enabled')
    .click();

  cy.get('textarea').should('be.visible');
  cy.get('button').contains(`${actions.setDowntime}`).click();
});

Then('the problematic Resource is displayed as in downtime', () => {
  cy.waitUntil(() => {
    return refreshListing()
      .then(() => cy.contains(serviceInDowntimeName))
      .parent()
      .parent()
      .parent()
      .then((val) => {
        return (
          val.css('background-color') === actionBackgroundColors.inDowntime
        );
      });
  }, {
    timeout: 30000,
  });
});

after(() => {
  tearDownResource().then(() => cy.reload());
});
