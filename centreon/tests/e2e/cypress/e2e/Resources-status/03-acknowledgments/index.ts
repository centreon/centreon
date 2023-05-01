import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';

import {
  checkThatFixtureHostsExistInDatabase,
  checkThatFixtureServicesExistInDatabase
} from '../../../commons';
import {
  actionBackgroundColors,
  checkIfNotificationsAreNotBeingSent,
  checkIfUserNotificationsAreEnabled,
  clearCentengineLogs,
  hostChildInAcknowledgementName,
  hostInAcknowledgementName,
  insertAckResourceFixtures,
  searchInput,
  serviceInAcknowledgementName,
  submitCustomResultsViaClapi,
  tearDownResource
} from '../common';

beforeEach(() => {
  cy.intercept({
    method: 'POST',
    url: '/centreon/api/latest/monitoring/resources/acknowledge'
  }).as('postAcknowledgments');
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/internal.php?object=centreon_topology&action=navigationList'
  }).as('getNavigationList');
});

Given('the user have the necessary rights to page Ressource Status', () => {
  cy.loginByTypeOfUser({
    jsonName: 'admin',
    preserveToken: true
  });

  cy.get(searchInput).should('exist');
});

Given(
  'the user have the necessary rights to acknowledge & disacknowledge',
  () => {
    cy.getByLabel({ label: 'Acknowledge' }).should('be.visible');
  }
);

Given(
  'there are at least two resources of each type with a problem and notifications enabled for the user',
  () => {
    insertAckResourceFixtures();

    checkIfUserNotificationsAreEnabled();

    cy.refreshListing();
  }
);

Given(
  'a single resource selected on Resources Status with the "Resource Problems" filter enabled',
  () => {
    cy.contains('Unhandled alerts');

    cy.contains(serviceInAcknowledgementName)
      .parent()
      .parent()
      .find('input[type="checkbox"]:first')
      .click();
  }
);

Given('acknowledgment column is enabled in Resource Status', () => {
  cy.get('[aria-label="Add columns"]').click();

  cy.contains('State').click();

  cy.get('[aria-label="Add columns"]').click();
});

When('the user uses one of the "Acknowledge" actions', () => {
  cy.getByLabel({ label: 'Acknowledge' }).last().click();
});

When(
  'the user fills in the required fields in the form with default parameters "sticky & persistent checked"',
  () => {
    cy.get('textarea').should('be.visible');

    cy.getByLabel({ label: 'Notify' }).should('not.be.checked');

    cy.getByLabel({ label: 'Sticky' }).should('be.checked');

    cy.getByLabel({ label: 'Persistent' }).should('be.checked');
  }
);

When('the user applies the acknowledgement', () => {
  cy.get('button').contains('Acknowledge').click();
});

Then(
  'the user is notified by the UI about the acknowledgement command being sent',
  () => {
    cy.wait('@postAcknowledgments').then(() => {
      cy.contains('Acknowledge command sent').should('have.length', 1);
    });
  }
);

Then(
  'the previously selected resource is marked as acknowledged in the listing with the corresponding colour',
  () => {
    cy.getByLabel({ label: 'State filter' }).click();

    cy.get('[data-value="all"]').click();

    cy.waitUntil(
      () => {
        return cy
          .refreshListing()
          .then(() => cy.contains(serviceInAcknowledgementName))
          .parent()
          .then((val) => {
            return (
              val.css('background-color') === actionBackgroundColors.acknowledge
            );
          });
      },
      {
        timeout: 15000
      }
    );
  }
);

Then(
  'the previously selected resource is marked as acknowledged in the listing with the acknowledgement icon',
  () => {
    cy.contains(serviceInAcknowledgementName)
      .parent()
      .parent()
      .getByLabel({ label: `${serviceInAcknowledgementName} Acknowledged` })
      .should('be.visible');
  }
);

Then(
  'the tooltip on acknowledgement icon contains the information related to the acknowledgment',
  () => {
    cy.contains(serviceInAcknowledgementName)
      .parent()
      .parent()
      .getByLabel({ label: `${serviceInAcknowledgementName} Acknowledged` })
      .trigger('mouseover');

    cy.get('div[role="tooltip"]').should('be.visible');

    cy.logout();

    cy.getByLabel({ label: 'Alias', tag: 'input' }).should('exist');

    tearDownResource();
  }
);

Given(
  'a multiple resources selected on Resources Status with the "Resource Problems" filter enabled',
  () => {
    cy.contains('Unhandled alerts');

    cy.contains(serviceInAcknowledgementName)
      .parent()
      .parent()
      .find('input[type="checkbox"]:first')
      .click();

    cy.contains(hostInAcknowledgementName)
      .parent()
      .parent()
      .find('input[type="checkbox"]:first')
      .click();
  }
);

Then(
  'the previously selected resources are marked as acknowledged in the listing with the corresponidng colour',
  () => {
    cy.getByLabel({ label: 'State filter' }).click();

    cy.get('[data-value="all"]').click();

    cy.waitUntil(
      () => {
        cy.refreshListing()
          .then(() => cy.contains(hostInAcknowledgementName))
          .parent()
          .then((val) => {
            return (
              val.css('background-color') === actionBackgroundColors.acknowledge
            );
          });

        return cy
          .refreshListing()
          .then(() => cy.contains(serviceInAcknowledgementName))
          .parent()
          .parent()
          .then((val) => {
            return (
              val.css('background-color') === actionBackgroundColors.acknowledge
            );
          });
      },
      {
        timeout: 15000
      }
    );
  }
);

Then(
  'the previously selected resources is marked as acknowledged in the listing with the acknowledgement icon',
  () => {
    cy.contains(serviceInAcknowledgementName)
      .parent()
      .parent()
      .getByLabel({ label: `${serviceInAcknowledgementName} Acknowledged` })
      .should('be.visible');

    cy.contains(hostInAcknowledgementName)
      .parent()
      .parent()
      .getByLabel({ label: `${hostInAcknowledgementName} Acknowledged` })
      .should('be.visible');
  }
);

Then(
  'the tooltip on acknowledgement icon for each resource contains the information related to the acknowledgment',
  () => {
    cy.contains(serviceInAcknowledgementName)
      .parent()
      .parent()
      .getByLabel({ label: `${serviceInAcknowledgementName} Acknowledged` })
      .trigger('mouseover');

    cy.get('div[role="tooltip"]').should('be.visible');

    cy.contains(hostInAcknowledgementName)
      .parent()
      .parent()
      .getByLabel({ label: `${hostInAcknowledgementName} Acknowledged` })
      .trigger('mouseover');

    cy.get('div[role="tooltip"]').should('be.visible');
  }
);

Given('the "Resource Problems" filter enabled', () => {
  cy.contains('Unhandled alerts');
});

Given('criteria is {string}', (criteria: string) => {
  cy.get(searchInput).as('searchInput');

  cy.get('@searchInput').clear();

  cy.get('@searchInput').type(criteria);

  cy.get('@searchInput').type('{esc}{enter}');
});

Given(
  'a resource of host is selected with {string}',
  (initial_status: string) => {
    submitCustomResultsViaClapi({
      host: hostChildInAcknowledgementName,
      output: `submit_${hostChildInAcknowledgementName}_${initial_status}`,
      status: initial_status
    });

    checkThatFixtureHostsExistInDatabase(
      hostChildInAcknowledgementName,
      `submit_${hostChildInAcknowledgementName}_${initial_status}`
    );

    cy.refreshListing();

    cy.contains(hostChildInAcknowledgementName)
      .parent()
      .parent()
      .find('input[type="checkbox"]:first')
      .click();
  }
);

Given(
  'a resource of service is selected with {string}',
  (initial_status: string) => {
    submitCustomResultsViaClapi({
      host: hostInAcknowledgementName,
      output: `submit_${serviceInAcknowledgementName}_${initial_status}`,
      service: serviceInAcknowledgementName,
      status: initial_status
    });

    checkThatFixtureServicesExistInDatabase(
      serviceInAcknowledgementName,
      `submit_${serviceInAcknowledgementName}_${initial_status}`
    );

    cy.refreshListing();

    cy.contains(serviceInAcknowledgementName)
      .parent()
      .parent()
      .find('input[type="checkbox"]:first')
      .click();
  }
);

When('"sticky" checkbox is checked in the form', () => {
  cy.getByLabel({ label: 'Sticky' }).should('be.checked');
});

When('"persistent" checkbox is unchecked in the form', () => {
  cy.getByLabel({ label: 'Persistent' }).uncheck();

  cy.getByLabel({ label: 'Persistent' }).should('not.be.checked');
});

When('the {string} resource is marked as acknowledged', (resource: string) => {
  let resourceName = '';

  switch (resource) {
    case 'service':
      resourceName = serviceInAcknowledgementName;
      break;
    default:
      resourceName = hostChildInAcknowledgementName;
      break;
  }

  cy.getByLabel({ label: 'State filter' }).click();

  cy.get('[data-value="all"]').click();

  cy.waitUntil(
    () => {
      return cy
        .refreshListing()
        .then(() => cy.contains(resourceName))
        .parent()
        .then((val) => {
          return (
            val.css('background-color') === actionBackgroundColors.acknowledge
          );
        });
    },
    {
      timeout: 15000
    }
  );
});

When(
  'the {string} status changes to {string}',
  (resource: string, changed_status: string) => {
    clearCentengineLogs();

    switch (resource) {
      case 'service':
        submitCustomResultsViaClapi({
          host: hostInAcknowledgementName,
          output: `submit_${serviceInAcknowledgementName}_${changed_status}`,
          service: serviceInAcknowledgementName,
          status: changed_status
        });

        checkThatFixtureServicesExistInDatabase(
          serviceInAcknowledgementName,
          `submit_${serviceInAcknowledgementName}_${changed_status}`
        );
        break;
      default:
        submitCustomResultsViaClapi({
          host: hostChildInAcknowledgementName,
          output: `submit_${hostChildInAcknowledgementName}_${changed_status}`,
          status: changed_status
        });

        checkThatFixtureHostsExistInDatabase(
          hostChildInAcknowledgementName,
          `submit_${hostChildInAcknowledgementName}_${changed_status}`
        );
        break;
    }
  }
);

Then('no notification are sent to the users', () => {
  checkIfNotificationsAreNotBeingSent();
  tearDownResource().then(() => cy.reload());
});
