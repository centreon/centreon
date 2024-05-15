import { Given, When, Then } from '@badeball/cypress-cucumber-preprocessor';

import data from '../../../fixtures/users/simple-user.json';
import data2 from '../../../fixtures/resources-access-management/new-host.json';
import '../commands';

beforeEach(() => {
  // using centreon-bam because we need BA modules
  // cy.startContainers({ moduleName: 'centreon-bam', useSlim: false });
  cy.startContainers();
  cy.enableResourcesAccessManagementFeature();
  cy.installCloudExtensionsOnContainer();
  // we should install cloud extension and anomaly detection
  cy.installCloudExtensionsModule();
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/internal.php?object=centreon_topology&action=navigationList'
  }).as('getNavigationList');
  cy.intercept({
    method: 'GET',
    url: '/centreon/include/common/userTimezone.php'
  }).as('getTimeZone');
  cy.intercept({
    method: 'GET',
    url: '/centreon/include/common/webServices/rest/internal.php?*'
  }).as('getContactFrame');
});

Given('I am logged in as a user with limited access', () => {
  cy.createSimpleUser(data, data2);
  cy.loginByTypeOfUser({ jsonName: 'simple-user', loginViaApi: true });
});

Given('I have restricted visibility to resources', () => {
  cy.visit(`centreon/monitoring/resources`);
  cy.waitUntil(
    () => {
      return cy.get('div[class$="-root-emptyDataCell"]').then(($element) => {
        return cy.wrap($element.length === 1);
      });
    },
    { interval: 5000, timeout: 100000 }
  );
  cy.get('div[class$="-root-emptyDataCell"]').contains('No result found');
  cy.logoutViaAPI();
});

Given('an Administrator is logged in on the platform', () => {
  cy.loginByTypeOfUser({ jsonName: 'admin' });
});

When(
  'the Administrator is redirected to the "Resource Access Management" page',
  () => {
    cy.visit(`centreon/administration/resource-access/rules`);
  }
);

Then('the Administrator clicks on the "Add" button', () => {
  cy.getByTestId({ testId: 'createResourceAccessRule' }).click();
});

When('the form is displayed', () => {
  cy.get('p[class$="-modalTitle"]')
    .contains('Create a new resource access rule')
    .should('be.visible');
});

Then(
  'the Administrator selects "Host" as the resource and fills in the required fields',
  () => {
    cy.get('#Name').type('Rule1');
    cy.getByLabel({ label: 'Select resource type', tag: 'div' }).click();
    cy.getByLabel({ label: 'Host', tag: 'li' }).click();
    cy.getByLabel({ label: 'Select resource', tag: 'input' }).click();
    cy.contains('Centreon-Database').click();
  }
);

When(
  'the Administrator selects a simple user from the contacts and clicks on "Save"',
  () => {
    cy.getByLabel({ label: 'Contacts', tag: 'input' }).type(data.login);
    cy.contains(`${data.login}`).click();
    cy.getByLabel({ label: 'Save', tag: 'button' }).click();
    cy.wait(3000);
    cy.reloadAcl();
  }
);

Then('the Administrator logs out', () => {
  cy.logoutViaAPI();
});

Given('the selected user is logged in', () => {
  cy.loginByTypeOfUser({ jsonName: 'simple-user', loginViaApi: true });
});

When('the user is redirected to monitoring "Resources" page', () => {
  cy.visit('centreon/monitoring/resources');
});

Then('the user can see the Host selected by the Administrator', () => {
  cy.contains('Centreon-Database').should('be.visible');
});

afterEach(() => {
  cy.stopContainers();
});
