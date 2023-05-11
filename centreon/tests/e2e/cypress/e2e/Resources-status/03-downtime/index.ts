import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';

import { checkThatServicesExistInDatabase } from '../../../commons';
import {
  actionBackgroundColors,
  checkIfUserNotificationsAreEnabled,
  insertDtResources,
  searchInput,
  secondServiceInDtName,
  serviceInDtName,
  tearDownResource
} from '../common';

before(() => {
  cy.startWebContainer({
    useSlim: false,
    version: 'MON-17222-monitoring-acknowledgment-automated'
  });
});

beforeEach(() => {
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/internal.php?object=centreon_topology&action=navigationList'
  }).as('getNavigationList');
  cy.intercept({
    method: 'POST',
    url: '/centreon/api/latest/monitoring/resources/downtime'
  }).as('postSaveDowntime');
});

Given('the user have the necessary rights to page Resource Status', () => {
  cy.loginByTypeOfUser({
    jsonName: 'admin',
    loginViaApi: true
  });

  cy.get(searchInput).should('exist');
});

Given('the user have the necessary rights to set downtime', () => {
  cy.getByTestId({ testId: 'Multiple Set Downtime' }).should('be.visible');
});

Given('minimally one resource with and notifications enabled on user', () => {
  insertDtResources();

  checkThatServicesExistInDatabase({
    serviceDesc: [serviceInDtName, secondServiceInDtName]
  });

  checkIfUserNotificationsAreEnabled();

  cy.refreshListing();

  cy.getByLabel({ label: 'State filter' }).click();

  cy.get('[data-value="all"]').click();
});

Given('resource selected', () => {
  cy.contains(serviceInDtName)
    .parent()
    .parent()
    .find('input[type="checkbox"]:first')
    .click();
});

When('the user click on the "Set downtime" action', () => {
  cy.getByTestId({ testId: 'Multiple Set Downtime' }).last().click();
});

When(
  'the user fill in the required fields on the start date now, and validate it',
  () => {
    cy.getByLabel({ label: 'Set downtime' }).last().click();
  }
);

Then('the user must be notified of the sending of the order', () => {
  cy.wait('@postSaveDowntime').then(() => {
    cy.contains('Downtime command sent').should('have.length', 1);
  });
});

Then('I see the resource as downtime in the listing', () => {
  cy.waitUntil(
    () => {
      return cy
        .refreshListing()
        .then(() => cy.contains(serviceInDtName))
        .parent()
        .then((val) => {
          return (
            val.css('background-color') === actionBackgroundColors.inDowntime
          );
        });
    },
    {
      timeout: 15000
    }
  );

  tearDownResource();
});

Given('multiple resources selected', () => {
  cy.contains(serviceInDtName)
    .parent()
    .parent()
    .find('input[type="checkbox"]:first')
    .click();

  cy.contains(secondServiceInDtName)
    .parent()
    .parent()
    .find('input[type="checkbox"]:first')
    .click();
});

Then(
  'the user should see the downtime resources appear in the listing after a refresh',
  () => {
    cy.waitUntil(
      () => {
        cy.refreshListing()
          .then(() => cy.contains(serviceInDtName))
          .parent()
          .then((val) => {
            return (
              val.css('background-color') === actionBackgroundColors.inDowntime
            );
          });

        return cy
          .refreshListing()
          .then(() => cy.contains(secondServiceInDtName))
          .parent()
          .then((val) => {
            return (
              val.css('background-color') === actionBackgroundColors.inDowntime
            );
          });
      },
      {
        timeout: 15000
      }
    );
  }
);

after(() => {
  tearDownResource();
});
