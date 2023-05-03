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
  tearDownAckResource,
  tearDownResource,
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
    url: '/centreon/include/common/userTimezone.php'
  }).as('getTimeZone');
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

    tearDownResource().then(() => tearDownAckResource());
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

    tearDownResource().then(() => tearDownAckResource());
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
    const hostStatus = {
      host: hostChildInAcknowledgementName,
      output: `submit_${hostChildInAcknowledgementName}_${initial_status}`,
      status: initial_status
    };

    submitCustomResultsViaClapi(hostStatus);

    checkThatFixtureHostsExistInDatabase(
      hostChildInAcknowledgementName,
      `submit_${hostChildInAcknowledgementName}_${initial_status}`,
      hostStatus
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
    const serviceStatus = {
      host: hostInAcknowledgementName,
      output: `submit_${serviceInAcknowledgementName}_${initial_status}`,
      service: serviceInAcknowledgementName,
      status: initial_status
    };

    submitCustomResultsViaClapi(serviceStatus);

    checkThatFixtureServicesExistInDatabase(
      serviceInAcknowledgementName,
      `submit_${serviceInAcknowledgementName}_${initial_status}`,
      serviceStatus
    );

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

When('"persistent" checkbox is {string} in the form', (check: string) => {
  switch (check) {
    case 'unchecked':
      cy.getByLabel({ label: 'Persistent' }).uncheck();

      cy.getByLabel({ label: 'Persistent' }).should('not.be.checked');
      break;
    default:
      cy.getByLabel({ label: 'Persistent' }).check();

      cy.getByLabel({ label: 'Persistent' }).should('be.checked');
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
    let status;

    switch (resource) {
      case 'service':
        status = {
          host: hostInAcknowledgementName,
          output: `submit_${serviceInAcknowledgementName}_${changed_status}`,
          service: serviceInAcknowledgementName,
          status: changed_status
        };
        submitCustomResultsViaClapi(status);

        checkThatFixtureServicesExistInDatabase(
          serviceInAcknowledgementName,
          `submit_${serviceInAcknowledgementName}_${changed_status}`,
          status
        );
        break;
      default:
        status = {
          host: hostChildInAcknowledgementName,
          output: `submit_${hostChildInAcknowledgementName}_${changed_status}`,
          status: changed_status
        };

        submitCustomResultsViaClapi(status);

        checkThatFixtureHostsExistInDatabase(
          hostChildInAcknowledgementName,
          `submit_${hostChildInAcknowledgementName}_${changed_status}`,
          status
        );
        break;
    }
  }
);

Then('no notification are sent to the users', () => {
  checkIfNotificationsAreNotBeingSent();
  tearDownResource().then(() => tearDownAckResource());
});

Given('a resource is selected', () => {
  cy.contains(/^test_host$/)
    .parent()
    .parent()
    .find('input[type="checkbox"]:first')
    .click();
});

When('the resource is marked as acknowledged', () => {
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
      timeout: 15000
    }
  );
});

When('engine service is restarted', () => {
  cy.visit(`/centreon/main.php?p=60901`);

  cy.wait('@getTimeZone');

  cy.getIframeBody()
    .find('form input[name="select[1]"]')
    .check({ force: true });

  cy.getIframeBody()
    .find('form .list_one>td')
    .eq(1)
    .invoke('text')
    .as('pollerName');

  cy.getIframeBody()
    .find('form button[name="apply_configuration"]')
    .contains('Export configuration')
    .click();

  cy.url().should('include', `/centreon/main.php?p=60902&poller=`);

  cy.get<string>('@pollerName').then((pollerName) => {
    cy.getIframeBody()
      .find('form span[class="selection"]')
      .eq(0)
      .contains(pollerName);
  });

  cy.getIframeBody()
    .find('form input[name="gen"]')
    .eq(0)
    .check({ force: true });

  cy.getIframeBody()
    .find('form input[name="debug"]')
    .eq(0)
    .check({ force: true });

  cy.getIframeBody()
    .find('form input[name="move"]')
    .eq(0)
    .check({ force: true });

  cy.getIframeBody()
    .find('form input[name="restart"]')
    .eq(0)
    .check({ force: true });

  cy.getIframeBody()
    .find('form select[name="restart_mode"]')
    .eq(0)
    .select('Restart');

  clearCentengineLogs()
    .getIframeBody()
    .find('form input[name="submit"]')
    .eq(0)
    .click();
});

Then(
  'resource is still marked as acknowledged after listing is refreshed',
  () => {
    cy.navigateTo({
      page: 'Resources Status',
      rootItemNumber: 1
    });

    cy.wait('@getNavigationList');

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
        timeout: 15000
      }
    );

    tearDownResource().then(() => tearDownAckResource());
  }
);

Given(
  'a single resource selected on Resources Status with the criteria "state: acknowledged"',
  () => {
    cy.contains(/^test_host$/)
      .parent()
      .parent()
      .find('input[type="checkbox"]:first')
      .click();

    cy.getByLabel({ label: 'Acknowledge' }).last().click();

    cy.get('button').contains('Acknowledge').click();

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
        timeout: 15000
      }
    );

    typeToSearchInput('state:acknowledged');
  }
);

Given('a resource marked as acknowledged is selected', () => {
  cy.contains(/^test_host$/)
    .parent()
    .should('have.css', 'background-color', actionBackgroundColors.acknowledge)
    .parent()
    .find('input[type="checkbox"]:first')
    .click();
});

Given(
  'the uses uses the "Disacknowledge" action for this resource in the "More actions" menu',
  () => {
    cy.getByLabel({ label: 'More actions' }).click();

    cy.getByTestId({ tag: 'li', testId: 'Multiple Disacknowledge' }).click({
      force: true
    });

    cy.getByLabel({ label: 'Disacknowledge' }).click();
  }
);

Then('the acknowledgement is removed', () => {
  typeToSearchInput('type:host');
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
      timeout: 15000
    }
  );
});

Then(
  'the resource is not marked as acknowledged after listing is refreshed with the criteria "state: acknowledged"',
  () => {
    typeToSearchInput('state:acknowledged');

    cy.refreshListing();

    cy.should('not.contain', /^test_host$/);
  }
);

after(() => {
  tearDownResource().then(() => tearDownAckResource());
});
