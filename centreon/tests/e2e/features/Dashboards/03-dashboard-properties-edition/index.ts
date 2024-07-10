import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';

import dashboardsOnePage from '../../../fixtures/dashboards/navigation/dashboards-single-page.json';

before(() => {
  cy.startContainers();
  cy.enableDashboardFeature();
  cy.executeCommandsViaClapi(
    'resources/clapi/config-ACL/dashboard-configuration-creator.json'
  );
});

after(() => {
  cy.stopContainers();
});

beforeEach(() => {
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/internal.php?object=centreon_topology&action=navigationList'
  }).as('getNavigationList');
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/latest/configuration/dashboards?'
  }).as('listAllDashboards');
  cy.loginByTypeOfUser({
    jsonName: 'user-dashboard-creator',
    loginViaApi: false
  });
  cy.insertDashboardList('dashboards/navigation/dashboards-single-page.json');
});

afterEach(() => {
  cy.requestOnDatabase({
    database: 'centreon',
    query: 'DELETE FROM dashboard'
  });
});

Given(
  'a user with update rights on a dashboard featured in the dashboards library',
  () => {
    cy.visitDashboards();
  }
);

When('the user selects the properties of the dashboard', () => {
  cy.getByLabel({ label: 'More actions', tag: 'button' }).eq(3).click();
  cy.getByLabel({ label: 'Edit properties' }).click();
});

Then(
  'the update form is displayed and contains fields to update this dashboard',
  () => {
    const dashboardToEdit = dashboardsOnePage[dashboardsOnePage.length - 2];
    cy.contains('Update dashboard').should('be.visible');
    cy.getByLabel({ label: 'Name', tag: 'input' }).should(
      'have.value',
      `${dashboardToEdit.name}`
    );

    cy.getByLabel({ label: 'Description', tag: 'textarea' }).should(
      'contain.text',
      `${dashboardToEdit.description}`
    );

    cy.getByLabel({ label: 'Update', tag: 'button' }).should('be.disabled');

    cy.getByLabel({ label: 'Cancel', tag: 'button' }).should('be.enabled');
  }
);

When(
  'the user fills in the name and description fields with new compliant values',
  () => {
    cy.contains('div.MuiDialog-container', 'Update dashboard').within(() => {
      cy.getByLabel({ label: 'Name', tag: 'input' }).type(
        '{selectall}{backspace}dashboard-edited'
      );
      cy.getByLabel({ label: 'Description', tag: 'textarea' }).type(
        '{selectall}{backspace}dashboard-edited'
      );
    });
  }
);

Then('the user is allowed to update the dashboard', () => {
  cy.contains('div.MuiDialog-container', 'Update dashboard').within(() => {
    cy.getByLabel({ label: 'Update', tag: 'button' }).should('be.enabled');
  });
});

When('the user saves the dashboard with its new values', () => {
  cy.contains('div.MuiDialog-container', 'Update dashboard').within(() => {
    cy.getByLabel({ label: 'Update', tag: 'button' }).click();
  });
});

Then(
  'the dashboard is listed in the dashboards library with its new name and description',
  () => {
    const dashboardToEdit = dashboardsOnePage[dashboardsOnePage.length - 2];
    cy.contains('Dashboards').should('be.visible');
    cy.contains('Update dashboard').should('not.exist');
    cy.contains(dashboardToEdit.name).should('not.exist');
    cy.contains(dashboardToEdit.description).should('not.exist');
    cy.contains('dashboard-edited').should('exist');
  }
);

Given(
  'a user with dashboard update rights who is about to update a dashboard with new values',
  () => {
    cy.visitDashboards();

    cy.getByLabel({ label: 'More actions', tag: 'button' }).eq(3).click();
    cy.getByLabel({ label: 'Edit properties' }).click();
    cy.getByLabel({ label: 'Name', tag: 'input' }).type(
      '{selectall}{backspace}dashboard-cancel-update-changes'
    );
    cy.getByLabel({ label: 'Description', tag: 'textarea' }).type(
      '{selectall}{backspace}dashboard-cancel-update-changes'
    );
  }
);

When('the user leaves the update form without saving', () => {
  cy.getByLabel({ label: 'Cancel', tag: 'button' }).click();
});

Then('the dashboard has not been edited and features its former values', () => {
  const dashboardToEdit = dashboardsOnePage[dashboardsOnePage.length - 2];
  cy.contains('dashboard-cancel-update-changes').should('not.exist');
  cy.contains(dashboardToEdit.name).should('be.visible');
});

When(
  'the user opens the form to update the dashboard for the second time',
  () => {
    cy.getByLabel({ label: 'More actions', tag: 'button' }).eq(3).click();
    cy.getByLabel({ label: 'Edit properties' }).click();
  }
);

Then(
  'the information the user filled in the first update form has not been saved',
  () => {
    cy.getByLabel({ label: 'Name', tag: 'input' }).should(
      'not.contain.text',
      'dashboard-cancel-update-changes'
    );
    cy.getByLabel({ label: 'Description', tag: 'textarea' }).should(
      'not.contain.text',
      'dashboard-cancel-update-changes'
    );
  }
);

Given('a user with dashboard update rights in a dashboard update form', () => {
  cy.visitDashboards();

  cy.getByLabel({ label: 'More actions', tag: 'button' }).eq(3).click();
  cy.getByLabel({ label: 'Edit properties' }).click();
});

When('the user sets an empty name for this dashboard', () => {
  cy.getByLabel({ label: 'Name', tag: 'input' }).clear();
});

Then('the user cannot save the dashboard in its current state', () => {
  cy.getByLabel({ label: 'Update', tag: 'button' }).should('be.disabled');
});

When('the user enters a new name for this dashboard', () => {
  cy.getByLabel({ label: 'Name', tag: 'input' }).type('dashboard-update-name');
});

Then('the user can now save the dashboard', () => {
  cy.getByLabel({ label: 'Update', tag: 'button' }).should('be.enabled');
});

Given(
  'a user with dashboard update rights in the update form of a dashboard with description',
  () => {
    cy.visitDashboards();

    cy.getByLabel({ label: 'More actions', tag: 'button' }).eq(3).click();
    cy.getByLabel({ label: 'Edit properties' }).click();
  }
);

When('the user sets an empty description for this dashboard', () => {
  cy.getByLabel({ label: 'Description', tag: 'textarea' }).clear();
});

Then('the user can save the dashboard with an empty description', () => {
  cy.getByLabel({ label: 'Update', tag: 'button' }).should('be.enabled');
});

When('the user saves the dashboard with the description field empty', () => {
  cy.getByLabel({ label: 'Update', tag: 'button' }).click();
});

Then(
  'the dashboard is listed in the dashboard library with only its name',
  () => {
    const dashboardToEdit = dashboardsOnePage[dashboardsOnePage.length - 2];
    cy.contains('Dashboards').should('be.visible');
    cy.contains('Update dashboard').should('not.exist');
    cy.contains(dashboardToEdit.name).should('exist');
  }
);
