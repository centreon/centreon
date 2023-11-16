import { Given, When, Then } from '@badeball/cypress-cucumber-preprocessor';

import dashboards from '../../../fixtures/dashboards/check-permissions/dashboards.json';
import dashboardAdministratorUser from '../../../fixtures/users/user-dashboard-administrator.json';
import dashboardCreatorUser from '../../../fixtures/users/user-dashboard-creator.json';

before(() => {
  cy.startWebContainer();
  cy.execInContainer({
    command: `sed -i 's@"dashboard": 0@"dashboard": 3@' /usr/share/centreon/config/features.json`,
    name: Cypress.env('dockerName')
  });
  cy.executeCommandsViaClapi('resources/clapi/config-ACL/dashboard-share.json');
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
  cy.intercept({
    method: 'POST',
    url: `/centreon/api/latest/configuration/dashboards/*/access_rights/contacts`
  }).as('addContactToDashboardShareList');
  cy.loginByTypeOfUser({
    jsonName: dashboardAdministratorUser.login,
    loginViaApi: false
  });
  cy.visit('/centreon/home/dashboards');
});

after(() => {
  cy.visit('/centreon/home/dashboards');
  cy.requestOnDatabase({
    database: 'centreon',
    query: 'DELETE FROM dashboard'
  });
  cy.stopWebContainer();
});

Given(
  'a dashboard featuring a dashboard administrator and a dashboard viewer in its share list',
  () => {
    cy.insertDashboard({ ...dashboards.fromDashboardAdministratorUser });
    cy.getByLabel({ label: 'edit access rights', tag: 'button' }).click();
    cy.getByLabel({ label: 'Open', tag: 'button' }).click();
    cy.contains(dashboardCreatorUser.login).click();
    cy.getByTestId({ testId: 'role-input' }).eq(0).click();
    cy.get('[role="listbox"]').contains('viewer').click();
    cy.getByTestId({ testId: 'add' }).click();
    cy.getByTestId({ testId: 'role-input' })
      .eq(1)
      .should('contain.text', 'viewer');
    cy.getByTestId({ testId: 'role-input' })
      .eq(2)
      .should('contain.text', 'editor');
    cy.getByLabel({ label: 'Update', tag: 'button' })
      .should('be.enabled')
      .click();
    cy.wait('@addContactToDashboardShareList');
    cy.waitUntilForDashboardRoles('edit-access-rights', 3);
  }
);

When(
  'the dashboard administrator user promotes the viewer user to an editor',
  () => {
    cy.getByTestId({ testId: 'edit-access-rights' }).invoke('show').click();
    cy.getByTestId({ testId: 'role-input' }).eq(2).contains('viewer').click();
    cy.get('[role="listbox"]').contains('editor').click();
    cy.get('[data-state="updated"]').should('exist');
    cy.getByLabel({ label: 'Update', tag: 'button' })
      .should('be.enabled')
      .click();
  }
);

Then(
  'the now-editor user can now perform update operations on the dashboard',
  () => {
    cy.logout();
    cy.loginByTypeOfUser({
      jsonName: dashboardCreatorUser.login,
      loginViaApi: false
    });
    cy.visit('/centreon/home/dashboards');
    cy.getByLabel({
      label: 'view',
      tag: 'button'
    })
      .contains(dashboards.fromDashboardAdministratorUser.name)
      .click();
    cy.url().should('match', /\/dashboards\/\d+$/);
    cy.getByTestId({ testId: 'edit' }).should('be.enabled');
    cy.getByTestId({ testId: 'share' }).should('be.enabled');
  }
);

Given(
  'a dashboard featuring a dashboard administrator and a dashboard editor in its share list',
  () => {
    cy.getByLabel({ label: 'edit access rights', tag: 'button' }).click();
    cy.getByTestId({ testId: 'role-input' })
      .eq(1)
      .should('contain.text', 'editor');
    cy.getByTestId({ testId: 'role-input' })
      .eq(2)
      .should('contain.text', 'editor');
  }
);

When(
  'the dashboard administrator user demotes the editor user to a viewer',
  () => {
    cy.getByTestId({ testId: 'role-input' }).eq(2).click();
    cy.get('[role="listbox"]').contains('viewer').click();
    cy.get('[data-state="updated"]').should('exist');
    cy.getByLabel({ label: 'Update', tag: 'button' })
      .should('be.enabled')
      .click();
  }
);

Then(
  'the now-viewer user cannot perform update operations on the dashboard anymore',
  () => {
    cy.logout();
    cy.loginByTypeOfUser({
      jsonName: dashboardCreatorUser.login,
      loginViaApi: false
    });
    cy.visit('/centreon/home/dashboards');
    cy.getByLabel({
      label: 'view',
      tag: 'button'
    })
      .contains(dashboards.fromDashboardAdministratorUser.name)
      .should('exist')
      .click();
    cy.url().should('match', /\/dashboards\/\d+$/);
    cy.getByTestId({ testId: 'edit' }).should('not.exist');
    cy.getByTestId({ testId: 'share' }).should('not.exist');
  }
);

Given(
  'a dashboard featuring a dashboard administrator and a viewer in its share list',
  () => {
    cy.getByLabel({ label: 'edit access rights', tag: 'button' }).click();
    cy.getByTestId({ testId: 'role-input' })
      .eq(1)
      .should('contain.text', 'editor');
    cy.getByTestId({ testId: 'role-input' })
      .eq(2)
      .should('contain.text', 'viewer');
  }
);

When(
  'the dashboard administrator user removes the dashboard editor user from the share list',
  () => {
    cy.getByTestId({ testId: 'remove_user' }).eq(1).click();
    cy.get('[data-state="removed"]').should('exist');
    cy.getByLabel({ label: 'Update', tag: 'button' })
      .should('be.enabled')
      .click();
  }
);

Then(
  "the dashboard is not visible anymore in the non-admin user's dashboards library",
  () => {
    cy.logout();
    cy.loginByTypeOfUser({
      jsonName: dashboardCreatorUser.login,
      loginViaApi: false
    });
    cy.visit('/centreon/home/dashboards');
    cy.getByLabel({
      label: 'view',
      tag: 'button'
    }).should('not.exist');
  }
);

Given(
  'a dashboard featuring a dashboard administrator and a user who has just been removed from the share list',
  () => {
    cy.getByLabel({ label: 'edit access rights', tag: 'button' }).click();
    cy.getByTestId({ testId: 'role-input' })
      .eq(1)
      .should('contain.text', 'editor');

    cy.getByLabel({ label: 'Open', tag: 'button' }).click();
    cy.contains(dashboardCreatorUser.login).click();
    cy.getByTestId({ testId: 'role-input' }).eq(0).click();
    cy.get('[role="listbox"]').contains('viewer').click();
    cy.getByTestId({ testId: 'add' }).click();
    cy.getByTestId({ testId: 'role-input' })
      .eq(1)
      .should('contain.text', 'viewer');
    cy.getByTestId({ testId: 'role-input' })
      .eq(2)
      .should('contain.text', 'editor');
    cy.getByLabel({ label: 'Update', tag: 'button' })
      .should('be.enabled')
      .click();
    cy.wait('@addContactToDashboardShareList');
    cy.waitUntilForDashboardRoles('edit-access-rights', 3);
    cy.getByLabel({ label: 'edit access rights', tag: 'button' }).should(
      'exist'
    );
    cy.getByLabel({ label: 'edit access rights', tag: 'button' }).click();
    cy.getByTestId({ testId: 'remove_user' }).eq(1).click();
    cy.get('[data-state="removed"]').should('exist');
  }
);

When(
  'the dashboard administrator user restores the deleted user to the share list and saves',
  () => {
    cy.getByTestId({ testId: 'restore_user' }).click();
    cy.getByLabel({ label: 'Update', tag: 'button' }).should('be.disabled');
    cy.getByLabel({ label: 'close', tag: 'button' }).click();
  }
);

Then('the restored user retains the same rights on the dashboard', () => {
  cy.logout();
  cy.loginByTypeOfUser({
    jsonName: dashboardCreatorUser.login,
    loginViaApi: false
  });
  cy.visit('/centreon/home/dashboards');
  cy.getByLabel({
    label: 'view',
    tag: 'button'
  })
    .contains(dashboards.fromDashboardAdministratorUser.name)
    .should('exist')
    .click();
  cy.url().should('match', /\/dashboards\/\d+$/);
  cy.getByTestId({ testId: 'edit' }).should('not.exist');
  cy.getByTestId({ testId: 'share' }).should('not.exist');
});
