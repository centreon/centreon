import { Given, When, Then } from '@badeball/cypress-cucumber-preprocessor';

import dashboards from '../../../fixtures/dashboards/check-permissions/dashboards.json';
import dashboardAdministratorUser from '../../../fixtures/users/user-dashboard-administrator.json';
import dashboardCreatorUser from '../../../fixtures/users/user-dashboard-creator.json';

before(() => {
  cy.startContainers();
  cy.enableDashboardFeature();
  cy.executeCommandsViaClapi('resources/clapi/config-ACL/dashboard-share.json');
});

beforeEach(() => {
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/internal.php?object=centreon_topology&action=navigationList'
  }).as('getNavigationList');
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/latest/configuration/dashboards**'
  }).as('listAllDashboards');
  cy.intercept({
    method: 'PUT',
    url: `/centreon/api/latest/configuration/dashboards/*/shares`
  }).as('updateShares');
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/latest/configuration/dashboards/*'
  }).as('getDashboard');
  cy.loginByTypeOfUser({
    jsonName: dashboardAdministratorUser.login,
    loginViaApi: false
  });
});

after(() => {
  cy.navigateTo({
    page: 'Dashboards',
    rootItemNumber: 0
  });
  cy.requestOnDatabase({
    database: 'centreon',
    query: 'DELETE FROM dashboard'
  });
  cy.stopContainers();
});

Given(
  'a dashboard featuring a dashboard administrator and a dashboard viewer in its share list',
  () => {
    cy.insertDashboard({ ...dashboards.fromDashboardAdministratorUser });
    cy.navigateTo({
      page: 'Dashboards',
      rootItemNumber: 0
    });
    cy.wait('@listAllDashboards');
    cy.getByTestId({ testId: 'Share with contacts' })
      .should('be.visible')
      .click();
    cy.getByLabel({ label: 'Open', tag: 'button' }).click();
    cy.contains(dashboardCreatorUser.login).click();
    cy.getByTestId({ testId: 'add' }).click();
    cy.getByTestId({ testId: `role-${dashboardCreatorUser.login}` })
      .eq(0)
      .realClick();
    cy.get('[role="listbox"]').contains('Viewer').click();
    cy.getByTestId({ testId: 'role-user-dashboard-creator' }).should(
      'have.value',
      'viewer'
    );
    cy.getByTestId({ testId: 'role-user-dashboard-administrator' }).should(
      'have.value',
      'editor'
    );
    cy.getByLabel({ label: 'Save', tag: 'button' })
      .should('be.enabled')
      .click();
    cy.wait('@updateShares');
    cy.wait('@getDashboard');
    cy.getByTestId({ testId: 'CloseIcon' }).eq(0).click();
    cy.get('.MuiAlert-message').should('not.exist');
    cy.waitUntilForDashboardRoles('ShareIcon', 4, 1);
  }
);

When(
  'the dashboard administrator user promotes the viewer user to an editor',
  () => {
    cy.getByTestId({ testId: 'Share with contacts' })
      .should('be.visible')
      .click();
    cy.getByTestId({ testId: 'role-user-dashboard-creator' }).realClick();
    cy.get('[role="listbox"]').contains('Editor').click();
    cy.get('[data-state="updated"]').should('exist');
    cy.getByLabel({ label: 'Save', tag: 'button' })
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
    cy.navigateTo({
      page: 'Dashboards',
      rootItemNumber: 0
    });
    cy.contains(dashboards.fromDashboardAdministratorUser.name).click();
    cy.url().should('match', /\/dashboards\/library\/\d+$/);
    cy.getByTestId({ testId: 'edit' }).should('be.enabled');
    cy.getByTestId({ testId: 'share' }).should('be.enabled');
  }
);

Given(
  'a dashboard featuring a dashboard administrator and a dashboard editor in its share list',
  () => {
    cy.navigateTo({
      page: 'Dashboards',
      rootItemNumber: 0
    });
    cy.wait('@listAllDashboards');
    cy.getByTestId({ testId: 'Share with contacts' })
      .should('be.visible')
      .click();
    cy.getByTestId({ testId: 'role-user-dashboard-creator' }).should(
      'have.value',
      'editor'
    );
    cy.getByTestId({ testId: 'role-user-dashboard-administrator' }).should(
      'have.value',
      'editor'
    );
  }
);

When(
  'the dashboard administrator user demotes the editor user to a viewer',
  () => {
    cy.getByTestId({ testId: 'role-user-dashboard-creator' }).realClick();
    cy.get('[role="listbox"]').contains('Viewer').click();
    cy.get('[data-state="updated"]').should('exist');
    cy.getByLabel({ label: 'Save', tag: 'button' })
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
    cy.navigateTo({
      page: 'Dashboards',
      rootItemNumber: 0
    });
    cy.contains(dashboards.fromDashboardAdministratorUser.name)
      .should('exist')
      .click();
    cy.url().should('match', /\/dashboards\/library\/\d+$/);
    cy.getByTestId({ testId: 'edit' }).should('not.exist');
    cy.getByTestId({ testId: 'share' }).should('not.exist');
  }
);

Given(
  'a dashboard featuring a dashboard administrator and a viewer in its share list',
  () => {
    cy.navigateTo({
      page: 'Dashboards',
      rootItemNumber: 0
    });
    cy.wait('@listAllDashboards');
    cy.getByTestId({ testId: 'Share with contacts' })
      .should('be.visible')
      .click();
    cy.getByTestId({ testId: 'role-user-dashboard-creator' }).should(
      'have.value',
      'viewer'
    );
    cy.getByTestId({ testId: 'role-user-dashboard-administrator' }).should(
      'have.value',
      'editor'
    );
  }
);

When(
  'the dashboard administrator user removes the dashboard editor user from the share list',
  () => {
    cy.getByTestId({ testId: 'DeleteOutlineIcon' }).eq(1).click();
    cy.get('[data-state="removed"]').should('exist');
    cy.getByLabel({ label: 'Save', tag: 'button' })
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
    cy.navigateTo({
      page: 'Dashboards',
      rootItemNumber: 0
    });
    // FIXME
    cy.contains('Dashboards').should('not.exist');
  }
);

Given(
  'a dashboard featuring a dashboard administrator and a user who has just been removed from the share list',
  () => {
    cy.navigateTo({
      page: 'Dashboards',
      rootItemNumber: 0
    });
    cy.wait('@listAllDashboards');
    cy.getByTestId({ testId: 'Share with contacts' })
      .should('be.visible')
      .click();
    cy.getByTestId({ testId: 'role-user-dashboard-administrator' }).should(
      'have.value',
      'editor'
    );

    cy.getByLabel({ label: 'Open', tag: 'button' }).click();
    cy.contains(dashboardCreatorUser.login).click();
    cy.getByTestId({ testId: 'add' }).click();
    cy.getByTestId({ testId: `role-${dashboardCreatorUser.login}` })
      .eq(0)
      .realClick();
    cy.get('[role="listbox"]').contains('Viewer').click();
    cy.getByTestId({ testId: 'role-user-dashboard-creator' }).should(
      'have.value',
      'viewer'
    );
    cy.getByTestId({ testId: 'role-user-dashboard-administrator' }).should(
      'have.value',
      'editor'
    );
    cy.getByLabel({ label: 'Save', tag: 'button' })
      .should('be.enabled')
      .click();
    cy.wait('@updateShares');
    cy.wait('@getDashboard');
    cy.getByTestId({ testId: 'CloseIcon' }).eq(0).click();
    cy.get('.MuiAlert-message').should('not.exist');
    cy.waitUntilForDashboardRoles('ShareIcon', 4, 1);
    cy.getByTestId({ testId: 'ShareIcon' }).should('be.visible').click();
    cy.getByTestId({ testId: 'DeleteOutlineIcon' }).eq(1).click();
    cy.get('[data-state="removed"]').should('exist');
  }
);

When(
  'the dashboard administrator user restores the deleted user to the share list and saves',
  () => {
    cy.getByTestId({ testId: 'RotateLeftIcon' }).click();
    cy.getByLabel({ label: 'Save', tag: 'button' }).should('be.disabled');
    cy.getByLabel({ label: 'close', tag: 'button' }).click();
  }
);

Then('the restored user retains the same rights on the dashboard', () => {
  cy.logout();
  cy.loginByTypeOfUser({
    jsonName: dashboardCreatorUser.login,
    loginViaApi: false
  });
  cy.navigateTo({
    page: 'Dashboards',
    rootItemNumber: 0
  });
  cy.wait('@listAllDashboards');
  cy.contains(dashboards.fromDashboardAdministratorUser.name)
    .should('exist')
    .click();
  cy.url().should('match', /\/dashboards\/library\/\d+$/);
  cy.getByTestId({ testId: 'edit' }).should('not.exist');
  cy.getByTestId({ testId: 'share' }).should('not.exist');
});
