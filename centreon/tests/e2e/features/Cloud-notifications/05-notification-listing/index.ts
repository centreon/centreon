import { Given, When, Then } from '@badeball/cypress-cucumber-preprocessor';

import {
  checkHostsAreMonitored,
  checkServicesAreMonitored
} from '../../../commons';
import { createNotification, enableNotificationFeature } from '../common';
import notificationBody from '../../../fixtures/notifications/notification-creation.json';
import data from '../../../fixtures/notifications/data-for-notification.json';

const previousPageLabel = 'Previous page';
const nextPageLabel = 'Next page';

before(() => {
  cy.startContainers();
  enableNotificationFeature();

  cy.addHostGroup({
    name: data.hostGroups.hostGroup1.name
  })
    .addHost({
      activeCheckEnabled: false,
      checkCommand: 'check_centreon_cpu',
      hostGroup: data.hostGroups.hostGroup1.name,
      name: data.hosts.host1.name,
      template: 'generic-host'
    })
    .addService({
      activeCheckEnabled: false,
      host: data.hosts.host1.name,
      maxCheckAttempts: 1,
      name: data.services.service1.name,
      template: 'Ping-LAN'
    })
    .applyPollerConfiguration();

  checkHostsAreMonitored([
    {
      name: data.hosts.host1.name
    }
  ]);

  checkServicesAreMonitored([
    {
      name: data.services.service1.name
    }
  ]);
});

after(() => {
  cy.stopContainers();
});

beforeEach(() => {
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/internal.php?object=centreon_topology&action=navigationList'
  }).as('getNavigationList');
  cy.loginByTypeOfUser({ jsonName: 'admin' });
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/latest/configuration/notifications?page=1&limit=10*'
  }).as('getNotifications');
});

afterEach(() => {
  cy.requestOnDatabase({
    database: 'centreon',
    query: 'DELETE FROM notification'
  });
});

Given('a user with access to the Notification Rules page', () => {
  cy.navigateTo({
    page: 'Notifications',
    rootItemNumber: 3,
    subMenu: 'Notifications'
  });
});

When('no Notification Rules are configured', () => {
  cy.request({
    method: 'GET',
    url: 'centreon/api/latest/configuration/notifications'
  }).then((response) => {
    // https://github.com/cypress-io/eslint-plugin-cypress?tab=readme-ov-file#chai-and-no-unused-expressions
    // eslint-disable-next-line @typescript-eslint/no-unused-expressions
    expect(response.body.result).to.be.an('array').that.is.empty;
  });
});

When('the user goes to Notification Rules Listing', () => {
  cy.navigateTo({
    page: 'Notifications',
    rootItemNumber: 3,
    subMenu: 'Notifications'
  });
});

Then(
  'the user sees a message indicating {string} in the list',
  (message: string) => {
    cy.contains(message).should('exist');
  }
);

Then('the pagination is disabled', () => {
  cy.get('.MuiTablePagination-toolbar > > button').each(($button) => {
    cy.wrap($button).should('be.disabled');
  });
});

When('the user has {int} Notification Rules', (count: number) => {
  for (let i = 1; i <= count; i += 1) {
    const createNotificationBody = { ...notificationBody };
    createNotificationBody.name = `Notification Created ${i}`;
    createNotification(createNotificationBody);
  }
  cy.reload();
  cy.wait('@getNotifications');
});

When(
  'the user sets the number of results per page to {int}',
  (maxPerPage: number) => {
    cy.get('.MuiToolbar-root > .MuiInputBase-root').click();
    cy.get(`[data-value=${maxPerPage}]`).click();
  }
);

When('the user sets current page to {int}', (currentPage: number) => {
  for (let i = 1; i < currentPage; i += 1) {
    cy.getByLabel({ label: `${nextPageLabel}` }).click();
  }
});

Then('the user sees the total results as {int}', (count: number) => {
  cy.get('.MuiTablePagination-displayedRows')
    .invoke('text')
    .then((text) => {
      const totalNumber = Number(text.split('of')[1].trim());
      expect(totalNumber).eq(count);
    });
});

Then(
  'the user sees the link to the previous page status as {string}',
  (previousPageStatus) => {
    cy.getByLabel({ label: `${previousPageLabel}` }).should(
      `be.${previousPageStatus}`
    );
  }
);

Then(
  'the user clicks on the link to navigate to the previous page with status enabled',
  () => {
    cy.getByLabel({ label: `${previousPageLabel}` }).then(($button) => {
      if (!$button.prop('disabled')) {
        cy.wrap($button).click();
      } else {
        cy.log('The previous page is disabled and cannot be clicked.');
      }
    });
  }
);

Then(
  'the user sees the link to the next page status as {string}',
  (nextPageStatus) => {
    cy.getByLabel({ label: `${nextPageLabel}` }).should(`be.${nextPageStatus}`);
  }
);

Then(
  'the user clicks on the link to navigate to the next page with status enabled',
  () => {
    cy.getByLabel({ label: `${nextPageLabel}` }).then(($button) => {
      if (!$button.prop('disabled')) {
        cy.wrap($button).click();
      } else {
        cy.log('The next page is disabled and cannot be clicked.');
      }
    });
  }
);
