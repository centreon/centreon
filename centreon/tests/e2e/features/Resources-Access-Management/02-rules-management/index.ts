import { Given, When, Then } from '@badeball/cypress-cucumber-preprocessor';

import data from '../../../fixtures/users/simple-user.json';
import data2 from '../../../fixtures/users/new-simple-user.json';
import host_data from '../../../fixtures/resources-access-management/new-host.json';
import data_bv from '../../../fixtures/resources-access-management/bv-names.json';
import data_ba from '../../../fixtures/resources-access-management/ba-names.json';

import '../commands';

beforeEach(() => {
  cy.startContainers();
  // install BAM and cloud extensions modules
  // cy.installBamModuleOnContainer();
  // cy.installCloudExtensionsOnContainer();
  // // we should install cloud extension and anomaly detection
  // cy.installBamModule();
  // cy.installCloudExtensionsModule();
  cy.enableResourcesAccessManagementFeature();
  cy.grantBaAccessToUsers();
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
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/internal.php?object=centreon_bam_top_counter&action=getBamTopCounterData'
  }).as('getTopCounter');
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/internal.php?object=centreon_topcounter&action=user'
  }).as('getTopCounteruser');
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/internal.php?object=centreon_topcounter&action=pollersListIssues'
  }).as('getTopCounterpoller');
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/internal.php?object=centreon_topcounter&action=servicesStatus'
  }).as('getTopCounterservice');
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/internal.php?object=centreon_topcounter&action=hosts_status'
  }).as('getTopCounterhosts');
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/latest/administration/resource-access/rules'
  }).as('getRules');
});

Given('I am logged in as a user with limited access', () => {
  cy.setUserTokenApiV1();
  // user should have access to ba
  cy.addContact({
    admin: data.admin,
    email: data.email,
    name: data.login,
    password: data.password
  });
  cy.loginByTypeOfUser({ jsonName: 'admin' });
  cy.addRightsForUser(data);
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

When('a new host is created', () => {
  cy.addHost({
    activeCheckEnabled: false,
    address: host_data.adress,
    checkCommand: 'check_centreon_cpu',
    hostGroup: host_data.hostGroups.hostGroup1.name,
    name: host_data.hosts.host1.name,
    template: 'generic-host'
  });
  cy.applyPollerConfiguration();
});

Then(
  'the Administrator is redirected to the "Resource Access Management" page',
  () => {
    // all resources should be deleted first
    cy.navigateTo({
      page: 'Resources Access',
      rootItemNumber: 4,
      subMenu: 'ACL'
    });
    cy.on('window:confirm', (text) => {
      expect(text).to.equal('Do you confirm the deletion ?');

      return true;
    });
    cy.getIframeBody()
      .find('td.ListColPicker input[type="checkbox"]')
      .parent()
      .click();
    cy.getIframeBody()
      .find('table.ToolbarTable.table')
      .find('select[name="o2"]')
      .select('Delete');
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
    cy.wait('@getTopCounteruser');
    cy.wait('@getTopCounterpoller');
    cy.wait('@getTopCounterservice');
    cy.wait('@getTopCounterhosts');
    cy.applyAcl();
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

When(
  'the Administrator selects "Business view" as the resource and fills in the required fields',
  () => {
    cy.addBvsAndBas(data_bv, data_ba);
    cy.get('#Name').type('Rule1');
    cy.getByLabel({ label: 'Select resource type', tag: 'div' }).click();
    cy.getByLabel({ label: 'Business view', tag: 'li' }).click();
    cy.getByLabel({ label: 'Select resource', tag: 'input' }).click();
    cy.contains('BV1').click();
  }
);

When(
  'the user is redirected to the monitoring "Business Activity" page',
  () => {
    cy.navigateTo({
      page: 'Monitoring',
      rootItemNumber: 1,
      subMenu: 'Business Activity'
    });
  }
);

Then('the user can access the selected business view', () => {
  cy.getIframeBody()
    .find('span[aria-labelledby$="-bv_filter-container"]')
    .click();
  cy.getIframeBody().contains('BV1').click();
});

Then('the Administrator selects "All hosts"', () => {
  cy.contains('span', 'All hosts')
    .parent()
    .within(() => {
      cy.getByTestId({ testId: 'CheckBoxOutlineBlankIcon' }).parent().click();
    });
});

Then('the user can see all hosts', () => {
  cy.contains('Centreon-Database').should('be.visible');
  cy.contains('Centreon-Server').should('be.visible');
  // we should add a counter or verify a certain length ..
});

Then('the Administrator selects "All Business views"', () => {
  cy.contains('span', 'All business views')
    .parent()
    .within(() => {
      cy.getByTestId({ testId: 'CheckBoxOutlineBlankIcon' }).parent().click();
    });
});

Then('the user can access all the business views', () => {
  cy.getIframeBody()
    .find('span[aria-labelledby$="-bv_filter-container"]')
    .click();
  data_bv.forEach((value) => {
    cy.getIframeBody().contains(value.Bv);
  });
});

Then('the Administrator selects "All contacts" and clicks on "Save"', () => {
  cy.contains('span', 'All contacts')
    .parent()
    .within(() => {
      cy.getByTestId({ testId: 'CheckBoxOutlineBlankIcon' }).parent().click();
    });
  cy.getByLabel({ label: 'Save', tag: 'button' }).click();
  cy.wait('@getTopCounteruser');
  cy.wait('@getTopCounterpoller');
  cy.wait('@getTopCounterservice');
  cy.wait('@getTopCounterhosts');
  cy.applyAcl();
});

Given('a new user is created', () => {
  cy.loginByTypeOfUser({ jsonName: 'admin' });
  cy.addContact({
    admin: data2.admin,
    email: data2.email,
    name: data2.login,
    password: data2.password
  });
  cy.addRightsForUser(data2);
  cy.applyAcl();
});

When('the user that was just created is logged in', () => {
  cy.loginByTypeOfUser({ jsonName: 'new-simple-user', loginViaApi: true });
});

afterEach(() => {
  cy.stopContainers();
});
