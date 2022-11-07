<<<<<<< HEAD
import { When, Then } from 'cypress-cucumber-preprocessor/steps';

import {
  stateFilterContainer,
  resourceMonitoringApi,
  actionBackgroundColors,
  actions,
  insertResourceFixtures,
  tearDownResource,
} from '../common';
import { refreshListing } from '../../../support/centreonData';

const serviceName = 'service_test';
const serviceInDowntimeName = 'service_test_dt';

before(() => {
  insertResourceFixtures().then(() => {
    cy.get(stateFilterContainer).click().get('[data-value="all"]').click();

    cy.intercept({
      method: 'GET',
      url: resourceMonitoringApi,
    });
=======
import { When, Then, Before } from 'cypress-cucumber-preprocessor/steps';

import {
  stateFilterContainer,
  serviceName,
  serviceNameDowntime,
  resourceMonitoringApi,
  actionBackgroundColors,
  actions,
} from '../common';
import { refreshListing } from '../../../support/centreonData';

Before(() => {
  cy.get(stateFilterContainer).click().get('[data-value="all"]').click();

  cy.intercept({
    method: 'GET',
    url: resourceMonitoringApi,
>>>>>>> centreon/dev-21.10.x
  });
});

When('I select the acknowledge action on a problematic Resource', () => {
  cy.contains(serviceName)
    .parents('div[role="row"]:first')
    .find('input[type="checkbox"]:first')
    .click();

<<<<<<< HEAD
  cy.get(`[aria-label="${actions.acknowledge}"]`)
    .parent('button')
=======
  cy.get(`[title="${actions.acknowledge}"]`)
    .children('button')
>>>>>>> centreon/dev-21.10.x
    .first()
    .should('be.enabled')
    .click();

  cy.get('textarea').should('be.visible');
  cy.get('button').contains('Acknowledge').click();
});

<<<<<<< HEAD
Then('the problematic Resource is displayed as acknowledged', () => {
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
  });
});

When('I select the downtime action on a problematic Resource', () => {
  cy.contains(serviceInDowntimeName)
=======
Then('The problematic Resource is displayed as acknowledged', () => {
  refreshListing(5000);

  cy.contains(serviceName)
    .parents('div[role="cell"]:first')
    .should('have.css', 'background-color', actionBackgroundColors.acknowledge);
});

When('I select the downtime action on a problematic Resource', () => {
  cy.contains(serviceNameDowntime)
>>>>>>> centreon/dev-21.10.x
    .parents('div[role="row"]:first')
    .find('input[type="checkbox"]:first')
    .click();

<<<<<<< HEAD
  cy.get(`[aria-label="${actions.setDowntime}"]`)
    .parent('button')
=======
  cy.get(`[title="${actions.setDowntime}"]`)
    .children('button')
>>>>>>> centreon/dev-21.10.x
    .first()
    .should('be.enabled')
    .click();

  cy.get('textarea').should('be.visible');
  cy.get('button').contains(`${actions.setDowntime}`).click();
});

<<<<<<< HEAD
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
  });
});

after(() => {
  tearDownResource().then(() => cy.reload());
=======
Then('The problematic Resource is displayed as in downtime', () => {
  refreshListing(5000);

  cy.contains(serviceNameDowntime)
    .parents('div[role="cell"]:first')
    .should('have.css', 'background-color', actionBackgroundColors.inDowntime);
>>>>>>> centreon/dev-21.10.x
});
