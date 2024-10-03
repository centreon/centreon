import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';

import { checkServicesAreMonitored } from '../../../commons';
import {
  actionBackgroundColors,
  checkIfUserNotificationsAreEnabled,
  searchInput
} from '../common';

const serviceInDtName = 'service1';
const secondServiceInDtName = 'service2';

beforeEach(() => {
  cy.startContainers();

  cy.intercept({
    method: 'GET',
    url: '/centreon/api/internal.php?object=centreon_topology&action=navigationList'
  }).as('getNavigationList');

  cy.intercept({
    method: 'GET',
    url: '/centreon/api/latest/users/filters/events-view?page=1&limit=100'
  }).as('getLastestUserFilters');

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
  }).wait('@getLastestUserFilters');

  cy.disableListingAutoRefresh();

  cy.get(searchInput).should('exist');
});

Given('the user have the necessary rights to set downtime', () => {
  cy.getByTestId({ testId: 'mainSetDowntime' }).should('be.visible');
});

Given('minimally one resource with notifications enabled on user', () => {
  cy.addHost({
    activeCheckEnabled: false,
    checkCommand: 'check_centreon_cpu',
    name: 'host1',
    template: 'generic-host'
  })
    .addService({
      activeCheckEnabled: false,
      host: 'host1',
      maxCheckAttempts: 1,
      name: serviceInDtName,
      template: 'SNMP-DISK-/'
    })
    .addService({
      activeCheckEnabled: false,
      host: 'host1',
      maxCheckAttempts: 1,
      name: secondServiceInDtName,
      template: 'Ping-LAN'
    })
    .applyPollerConfiguration();

  checkServicesAreMonitored([
    {
      name: serviceInDtName
    },
    {
      name: secondServiceInDtName
    }
  ]);

  checkIfUserNotificationsAreEnabled();

  cy.refreshListing();

  cy.getByLabel({ label: 'State filter' }).click();

  cy.get('[data-value="all"]').click();
});

Given('a resource is selected', () => {
  cy.contains(serviceInDtName)
    .parent()
    .parent()
    .find('input[type="checkbox"]:first')
    .click();
});

When('the user click on the "Set downtime" action', () => {
  cy.getByTestId({ testId: 'mainSetDowntime' }).last().click();
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
  checkServicesAreMonitored([
    {
      inDowntime: true,
      name: serviceInDtName
    }
  ]);

  cy.refreshListing()
    .then(() => cy.contains(serviceInDtName))
    .parent()
    .then((val) => {
      return val.css('background-color') === actionBackgroundColors.inDowntime;
    });
});

Given('multiple resources are selected', () => {
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
    checkServicesAreMonitored([
      {
        inDowntime: true,
        name: serviceInDtName
      },
      {
        inDowntime: true,
        name: secondServiceInDtName
      }
    ]);

    cy.refreshListing()
      .then(() => cy.contains(serviceInDtName))
      .parent()
      .then((val) => {
        return (
          val.css('background-color') === actionBackgroundColors.inDowntime
        );
      })
      .then(() => cy.contains(secondServiceInDtName))
      .parent()
      .then((val) => {
        return (
          val.css('background-color') === actionBackgroundColors.inDowntime
        );
      });

    cy.waitForDowntime({
      host: 'host1',
      service: serviceInDtName
    });
    cy.waitForDowntime({
      host: 'host1',
      service: secondServiceInDtName
    });
  }
);

Given('a resource is in downtime', () => {
  cy.contains(serviceInDtName)
    .parent()
    .parent()
    .find('input[type="checkbox"]:first')
    .click();

  cy.getByTestId({ testId: 'mainSetDowntime' }).last().click();

  cy.getByLabel({ label: 'Set downtime' }).last().click();

  cy.wait('@postSaveDowntime').then(() => {
    cy.contains('Downtime command sent').should('have.length', 1);
  });

  checkServicesAreMonitored([
    {
      inDowntime: true,
      name: serviceInDtName
    }
  ]);

  cy.refreshListing()
    .then(() => cy.contains(serviceInDtName))
    .parent()
    .then((val) => {
      return val.css('background-color') === actionBackgroundColors.inDowntime;
    });

  cy.waitForDowntime({
    host: 'host1',
    service: serviceInDtName
  });
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

Then('the user starts downtime configuration on the resource', () => {
  cy.get('@serviceInDT').check();
  cy.get('@serviceInDT').should('be.checked');
});

Then('the user cancels the downtime configuration', () => {
  cy.getIframeBody().find('form input[name="submit2"]').as('cancelButton');

  cy.window().then((win) => {
    cy.stub(win, 'confirm').returns(true);
  });

  cy.get('@cancelButton').first().click();
});

Then('the line disappears from the listing', () => {
  cy.wait('@getTimeZone');
  cy.waitUntil(
    () => {
      cy.getIframeBody().find('input[name="SearchB"]').click();
      cy.wait('@getTimeZone');

      return cy
        .getIframeBody()
        .find('.ListTable tr:not(.ListHeader)')
        .first()
        .children()
        .then((val) => {
          return val.text().trim() === 'No downtime scheduled';
        });
    },
    {
      interval: 5000,
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
  checkServicesAreMonitored([
    {
      inDowntime: false,
      name: serviceInDtName
    }
  ]);

  cy.refreshListing()
    .then(() => cy.contains(serviceInDtName))
    .parent()
    .then((val) => {
      return val.css('background-color') === actionBackgroundColors.normal;
    });
});

Given('multiple resources are in downtime', () => {
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

  cy.getByTestId({ testId: 'mainSetDowntime' }).last().click();

  cy.getByLabel({ label: 'Set downtime' }).last().click();

  cy.wait('@postSaveDowntime').then(() => {
    cy.contains('Downtime command sent').should('have.length', 1);
  });

  checkServicesAreMonitored([
    {
      inDowntime: true,
      name: serviceInDtName
    },
    {
      inDowntime: true,
      name: secondServiceInDtName
    }
  ]);

  cy.refreshListing()
    .then(() => cy.contains(serviceInDtName))
    .parent()
    .then((val) => {
      return val.css('background-color') === actionBackgroundColors.inDowntime;
    })
    .then(() => cy.contains(secondServiceInDtName))
    .parent()
    .then((val) => {
      return val.css('background-color') === actionBackgroundColors.inDowntime;
    });

  cy.waitForDowntime({
    host: 'host1',
    service: serviceInDtName
  });
  cy.waitForDowntime({
    host: 'host1',
    service: secondServiceInDtName
  });
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

Then('the user starts downtime configuration on the resources', () => {
  cy.get('@serviceInDT').check();
  cy.get('@serviceInDT').should('be.checked');

  cy.get('@secondServiceInDT').check();
  cy.get('@secondServiceInDT').should('be.checked');
});

Then('the lines disappears from the listing', () => {
  cy.wait('@getTimeZone');
  cy.waitUntil(
    () => {
      cy.getIframeBody().find('input[name="SearchB"]').click();
      cy.wait('@getTimeZone');

      return cy
        .getIframeBody()
        .find('.ListTable tr:not(.ListHeader)')
        .first()
        .children()
        .then((val) => {
          return val.text().trim() === 'No downtime scheduled';
        });
    },
    {
      interval: 5000,
      timeout: 15000
    }
  );
});

Then('the resources should not be in Downtime anymore', () => {
  checkServicesAreMonitored([
    {
      inDowntime: false,
      name: serviceInDtName
    },
    {
      inDowntime: false,
      name: secondServiceInDtName
    }
  ]);

  cy.refreshListing()
    .then(() => cy.contains(serviceInDtName))
    .parent()
    .then((val) => {
      return val.css('background-color') === actionBackgroundColors.normal;
    })
    .then(() => cy.contains(secondServiceInDtName))
    .parent()
    .then((val) => {
      return val.css('background-color') === actionBackgroundColors.normal;
    });
});

afterEach(() => {
  cy.stopContainers();
});
