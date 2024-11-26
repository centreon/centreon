import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';

import {
  checkHostsAreMonitored,
  checkServicesAreMonitored
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
  typeToSearchInput
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

  cy.intercept({
    method: 'GET',
    url: '/centreon/api/latest/users/filters/events-view?page=1&limit=100'
  }).as('getLastestUserFilters');

  cy.intercept({
    method: 'GET',
    url: '/centreon/include/common/userTimezone.php'
  }).as('getTimeZone');

  cy.intercept('/centreon/api/latest/monitoring/resources*').as(
    'monitoringEndpoint'
  );

  cy.startContainers();
  cy.loginByTypeOfUser({
    jsonName: 'admin',
    loginViaApi: true
  });
});

Given('the user has the necessary rights to page Resource Status', () => {
  cy.get(searchInput).should('exist');
});

Given(
  'the user has the necessary rights to acknowledge & disacknowledge',
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

  cy.get('li[role="menuitem"][value="State"]').click();

  cy.get('li[role="menuitem"][value="Information"]').click();

  cy.get('li[role="menuitem"][value="Tries"]').click();

  cy.get('li[role="menuitem"][value="Parent"]').click();

  cy.get('[aria-label="Add columns"]').click();
});

When('the user uses one of the "Acknowledge" actions', () => {
  cy.getByLabel({ label: 'Acknowledge' }).last().click();
});

When(
  'the user fills in the required fields in the form with default parameters "sticky checked"',
  () => {
    cy.get('textarea').should('be.visible');

    cy.getByLabel({ label: 'Notify' }).should('not.be.checked');

    cy.getByLabel({ label: 'Sticky' }).should('be.checked');
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
  'the previously selected resource is marked as acknowledged in the listing with the corresponding color',
  () => {
    cy.wait('@getLastestUserFilters');

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
        timeout: 30000
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
  }
);

Given(
  'multiple resources selected on Resources Status with the "Resource Problems" filter enabled',
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
  'the previously selected resources are marked as acknowledged in the listing with the corresponding color',
  () => {
    cy.wait('@getLastestUserFilters');

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
          .then((val) => {
            return (
              val.css('background-color') === actionBackgroundColors.acknowledge
            );
          });
      },
      {
        timeout: 30000
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
  typeToSearchInput(criteria);
});

Given(
  'a resource of host is selected with {string}',
  (initial_status: string) => {
    checkHostsAreMonitored([
      {
        name: hostChildInAcknowledgementName
      }
    ]);

    const hostStatus = {
      host: hostChildInAcknowledgementName,
      output: `submit_${hostChildInAcknowledgementName}_${initial_status}`,
      status: initial_status
    };

    submitCustomResultsViaClapi(hostStatus);

    checkHostsAreMonitored([
      {
        name: hostChildInAcknowledgementName,
        output: hostStatus.output
      }
    ]);

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
    const serviceStatus = {
      host: hostInAcknowledgementName,
      output: `submit_${serviceInAcknowledgementName}_${initial_status}`,
      service: serviceInAcknowledgementName,
      status: initial_status
    };

    checkServicesAreMonitored([{ name: serviceInAcknowledgementName }]);

    submitCustomResultsViaClapi(serviceStatus);

    cy.refreshListing();

    cy.contains(serviceInAcknowledgementName)
      .parent()
      .parent()
      .find('input[type="checkbox"]:first')
      .click();
  }
);

When('"sticky" checkbox is {string} in the form', (check: string) => {
  switch (check) {
    case 'unchecked':
      cy.getByLabel({ label: 'Sticky' }).uncheck();
      cy.getByLabel({ label: 'Sticky' }).should('not.be.checked');
      break;
    default:
      cy.getByLabel({ label: 'Sticky' }).check();
      cy.getByLabel({ label: 'Sticky' }).should('be.checked');
      break;
  }
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

  cy.wait('@getLastestUserFilters');

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
      timeout: 30000
    }
  );
});

When(
  'the {string} status changes to {string}',
  (resource: string, changed_status: string) => {
    clearCentengineLogs();
    let status;

    switch (resource) {
      case 'service':
        status = {
          host: hostInAcknowledgementName,
          output: `submit_${serviceInAcknowledgementName}_${changed_status}`,
          service: serviceInAcknowledgementName,
          status: changed_status
        };

        checkServicesAreMonitored([{ name: serviceInAcknowledgementName }]);

        submitCustomResultsViaClapi(status);

        checkServicesAreMonitored([
          {
            name: serviceInAcknowledgementName,
            status: changed_status
          }
        ]);
        break;
      default:
        status = {
          host: hostChildInAcknowledgementName,
          output: `submit_${hostChildInAcknowledgementName}_${changed_status}`,
          status: changed_status
        };

        checkHostsAreMonitored([
          {
            name: hostChildInAcknowledgementName
          }
        ]);

        submitCustomResultsViaClapi(status);

        checkHostsAreMonitored([
          {
            name: hostChildInAcknowledgementName,
            output: status.output,
            status: status.status
          }
        ]);
        break;
    }
  }
);

Then('no notification are sent to the users', () => {
  checkIfNotificationsAreNotBeingSent();
});

Given(
  'a single resource selected on Resources Status with the criteria "state: acknowledged"',
  () => {
    cy.contains(/^test_host$/)
      .parent()
      .parent()
      .find('input[type="checkbox"]:first')
      .click();

    cy.getByLabel({ label: 'Acknowledge' }).last().click();

    cy.getByTestId({tag:'button', testId:'Confirm'}).contains('Acknowledge').click();

    cy.wait('@postAcknowledgments').then(() => {
      cy.contains('Acknowledge command sent').should('have.length', 1);
    });

    typeToSearchInput('type:host');

    cy.waitUntil(
      () => {
        return cy
          .refreshListing()
          .then(() => cy.contains(/^test_host$/))
          .parent()
          .then((val) => {
            return (
              val.css('background-color') === actionBackgroundColors.acknowledge
            );
          });
      },
      {
        timeout: 30000
      }
    );

    typeToSearchInput('state:acknowledged');
  }
);

Given('a resource marked as acknowledged is selected', () => {
  typeToSearchInput('type:host h.name:test_host state:acknowledged');

  cy.waitUntil(
    () => {
      return cy
        .refreshListing()
        .then(() => cy.contains(/^test_host$/))
        .parent()
        .then((val) => {
          return (
            val.css('background-color') === actionBackgroundColors.acknowledge
          );
        });
    },
    {
      timeout: 30000
    }
  );

  cy.contains(/^test_host$/)
    .parent()
    .parent()
    .find('input[type="checkbox"]:first')
    .click();
});

Given(
  'the user uses the "Disacknowledge" action for this resource in the "More actions" menu',
  () => {
    cy.getByLabel({ label: 'More actions' }).click();

    // forced click because More actions tooltip is on top of Disacknowledge text
    cy.getByTestId({ tag: 'li', testId: 'Multiple Disacknowledge' }).click({
      force: true
    });

    cy.getByLabel({ label: 'Disacknowledge' }).click();
  }
);

Then('the acknowledgement is removed', () => {
  cy.refreshListing();

  typeToSearchInput('type:host h.name:test_host');

  cy.waitUntil(
    () => {
      return cy
        .refreshListing()
        .then(() => cy.contains(/^test_host$/))
        .parent()
        .then((val) => {
          return val.css('background-color') === actionBackgroundColors.normal;
        });
    },
    {
      timeout: 30000
    }
  );
});

Then(
  'the resource is not marked as acknowledged after listing is refreshed with the criteria "state: acknowledged"',
  () => {
    cy.refreshListing();

    typeToSearchInput('state:acknowledged');

    cy.wait('@monitoringEndpoint');

    cy.contains(/^test_host$/).should('not.exist');
  }
);

afterEach(() => {
  cy.stopContainers();
});
