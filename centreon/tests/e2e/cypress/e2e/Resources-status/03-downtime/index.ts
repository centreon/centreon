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
  cy.startWebContainer();
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
  cy.intercept({
    method: 'GET',
    url: '/centreon/include/common/userTimezone.php'
  }).as('getTimeZone');
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

    tearDownResource();
  }
);

Given('a resource is in downtime', () => {
  cy.contains(serviceInDtName)
    .parent()
    .parent()
    .find('input[type="checkbox"]:first')
    .click();

  cy.getByTestId({ testId: 'Multiple Set Downtime' }).last().click();

  cy.getByLabel({ label: 'Set downtime' }).last().click();

  cy.wait('@postSaveDowntime').then(() => {
    cy.contains('Downtime command sent').should('have.length', 1);
  });

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
});

Given('that you have to go to the downtime page', () => {
  cy.navigateTo({
    page: 'Downtimes',
    rootItemNumber: 1,
    subMenu: 'Downtimes'
  });
});

When('I search for the resource currently "In Downtime" in the list', () => {
  cy.wait('@getTimeZone').then(() => {
    cy.getIframeBody()
      .contains(serviceInDtName)
      .parent()
      .parent()
      .find('input[type="checkbox"]:first')
      .as('serviceInDT');
  });
});

Then('the user selects the checkbox and clicks on the "Cancel" action', () => {
  cy.get('@serviceInDT').check().should('be.checked');

  cy.getIframeBody().find('form input[name="submit2"]').as('cancelButton');

  cy.get('@cancelButton').first().trigger('click');
});

Then('the user confirms the cancellation of the downtime', () => {
  cy.on('window:confirm', () => {
    return true;
  });
});

Then('the line disappears from the listing', () => {
  cy.waitUntil(
    () => {
      return cy
        .reload()
        .then(() =>
          cy.getIframeBody().find('.ListTable tr:not(.ListHeader)').first()
        )
        .children()
        .then((val) => {
          return val.text().trim() === 'No downtime scheduled';
        });
    },
    {
      timeout: 15000
    }
  );
});

Then('the user goes to the Resource Status page', () => {
  cy.navigateTo({
    page: 'Resources Status',
    rootItemNumber: 1
  });
});

Then('the resource should not be in Downtime anymore', () => {
  cy.waitUntil(
    () => {
      return cy
        .refreshListing()
        .then(() => cy.contains(serviceInDtName))
        .parent()
        .then((val) => {
          return val.css('background-color') === actionBackgroundColors.normal;
        });
    },
    {
      timeout: 15000
    }
  );

  tearDownResource();
});

Given('multiple resources are on downtime', () => {
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

  cy.getByTestId({ testId: 'Multiple Set Downtime' }).last().click();

  cy.getByLabel({ label: 'Set downtime' }).last().click();

  cy.wait('@postSaveDowntime').then(() => {
    cy.contains('Downtime command sent').should('have.length', 1);
  });

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
});

When('I search for the resources currently "In Downtime" in the list', () => {
  cy.wait('@getTimeZone').then(() => {
    cy.getIframeBody()
      .contains(serviceInDtName)
      .parent()
      .parent()
      .find('input[type="checkbox"]:first')
      .as('serviceInDT');

    cy.getIframeBody()
      .contains(secondServiceInDtName)
      .parent()
      .parent()
      .find('input[type="checkbox"]:first')
      .as('secondServiceInDT');
  });
});

Then(
  'the user selects the checkboxes and clicks on the "Cancel" action',
  () => {
    cy.get('@serviceInDT').check().should('be.checked');

    cy.get('@secondServiceInDT').check().should('be.checked');

    cy.getIframeBody().find('form input[name="submit2"]').as('cancelButton');

    cy.get('@cancelButton').first().click();
  }
);

Then('the lines disappears from the listing', () => {
  cy.waitUntil(
    () => {
      return cy
        .reload()
        .then(() =>
          cy.getIframeBody().find('.ListTable tr:not(.ListHeader)').first()
        )
        .children()
        .then((val) => {
          return val.text().trim() === 'No downtime scheduled';
        });
    },
    {
      timeout: 15000
    }
  );
});

Then('the resources should not be in Downtime anymore', () => {
  cy.waitUntil(
    () => {
      cy.refreshListing()
        .then(() => cy.contains(serviceInDtName))
        .parent()
        .then((val) => {
          return val.css('background-color') === actionBackgroundColors.normal;
        });

      return cy
        .refreshListing()
        .then(() => cy.contains(secondServiceInDtName))
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

after(() => {
  tearDownResource();
});
