import { Given, When, Then } from '@badeball/cypress-cucumber-preprocessor';

import dashboards from '../../../fixtures/dashboards/check-permissions/dashboards.json';
import dashboardAdministratorUser from '../../../fixtures/users/user-dashboard-administrator.json';
import dashboardCreatorUser from '../../../fixtures/users/user-dashboard-creator.json';
import dashboardViewerUser from '../../../fixtures/users/user-dashboard-viewer.json';
import dashboardCGMember1 from '../../../fixtures/users/user-dashboard-cg-member-1.json';
import dashboardCGMember2 from '../../../fixtures/users/user-dashboard-cg-member-2.json';
import dashboardCGMember3 from '../../../fixtures/users/user-dashboard-cg-member-3.json';
import dashboardCGMember4 from '../../../fixtures/users/user-dashboard-cg-member-4.json';

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
    method: 'GET',
    url: '/centreon/api/latest/configuration/dashboards/*'
  }).as('getDashboard');
  cy.intercept({
    method: 'POST',
    url: '/centreon/api/latest/configuration/dashboards'
  }).as('createDashboard');
  cy.intercept({
    method: 'PUT',
    url: `/centreon/api/latest/configuration/dashboards/*/shares`
  }).as('updateShares');
  cy.loginByTypeOfUser({
    jsonName: dashboardAdministratorUser.login,
    loginViaApi: true
  });
  cy.insertDashboard({ ...dashboards.fromDashboardAdministratorUser });
  cy.logoutViaAPI();
  cy.loginByTypeOfUser({
    jsonName: dashboardCreatorUser.login,
    loginViaApi: true
  });
  cy.insertDashboard({ ...dashboards.fromDashboardCreatorUser });
  cy.logoutViaAPI();
});

after(() => {
  cy.stopContainers();
});

afterEach(() => {
  cy.visit('/centreon/home/dashboards/library');
  cy.requestOnDatabase({
    database: 'centreon',
    query: 'DELETE FROM dashboard'
  });
  cy.logout();
});

Given('a non-admin user who is in a list of shared dashboards', () => {
  cy.loginByTypeOfUser({
    jsonName: dashboardAdministratorUser.login,
    loginViaApi: true
  });

  cy.visitDashboards();
});

When('the user selects the share option on a dashboard', () => {
  cy.contains(dashboards.fromDashboardAdministratorUser.name).click();
  cy.getByLabel({ label: 'share', tag: 'button' }).first().click();
});

Then('the user is redirected to the sharing list of the dashboard', () => {
  cy.contains('Manage access rights').should('be.visible');
  cy.get('*[class^="MuiList-root"]', { timeout: 12000 }).eq(1).should('exist');
});

Then('the creator of the dashboard is listed as its sole editor', () => {
  cy.get('*[class^="MuiList-root"]', { timeout: 12000 })
    .eq(1)
    .children()
    .its('length')
    .should('eq', 1);
  cy.get('*[class^="MuiList-root"]', { timeout: 12000 })
    .eq(1)
    .children()
    .eq(0)
    .should('contain', `${dashboardAdministratorUser.login}`);
  cy.getByTestId({ testId: `role-${dashboardAdministratorUser.login}` }).should(
    'have.value',
    'editor'
  );
});

Given('a non-admin user who has update rights on a dashboard', () => {
  cy.loginByTypeOfUser({
    jsonName: dashboardCreatorUser.login,
    loginViaApi: true
  });

  cy.visitDashboards();
});

When('the editor user sets another user as a viewer on the dashboard', () => {
  cy.contains(dashboards.fromDashboardCreatorUser.name).click();
  cy.getByLabel({ label: 'share', tag: 'button' }).click();
  cy.getByLabel({ label: 'Add a contact', tag: 'input' }).click();
  cy.contains(dashboardViewerUser.login).click();
  cy.getByTestId({ testId: 'add' }).should('be.enabled');
  cy.getByTestId({ testId: `add_role` }).parent().click();
  cy.get('[role="listbox"]').contains('Viewer').click();
  cy.getByTestId({ testId: 'add' }).click();

  cy.get('*[class^="MuiList-root"]', { timeout: 12000 })
    .eq(1)
    .children()
    .its('length')
    .should('eq', 2);
  cy.get('*[class^="MuiList-root"]', { timeout: 12000 })
    .eq(1)
    .children()
    .eq(0)
    .should('contain', `${dashboardViewerUser.login}`);

  cy.get('[data-state="added"]').should('exist');
  cy.getByLabel({ label: 'Save', tag: 'button' }).should('be.enabled').click();
  cy.wait('@updateShares');
  cy.wait('@getDashboard');
  cy.getByTestId({ testId: 'CloseIcon' }).eq(0).click();
  cy.get('.MuiAlert-message').should('not.exist');
  cy.waitUntilForDashboardRoles('share', 3);
});

Then(
  "the viewer user is listed as a viewer in the dashboard's share list",
  () => {
    cy.getByLabel({ label: 'share', tag: 'button' }).click();
    cy.get('*[class^="MuiList-root"]', { timeout: 12000 })
      .eq(1)
      .children()
      .contains(dashboardViewerUser.login)
      .should('exist');
    cy.getByTestId({ testId: `role-${dashboardViewerUser.login}` }).should(
      'have.value',
      'viewer'
    );
    cy.get('[data-state="added"]').should('not.exist');
    cy.getByLabel({ label: 'Cancel', tag: 'button' }).click();
    cy.logout();
    cy.getByLabel({ label: 'Alias', tag: 'input' }).should('exist');
  }
);

When('the viewer user logs in on the platform', () => {
  cy.loginByTypeOfUser({
    jsonName: dashboardViewerUser.login,
    loginViaApi: true
  });

  cy.visitDashboards();
});

Then(
  "the dashboard is featured in the viewer user's dashboards library",
  () => {
    cy.contains(dashboards.fromDashboardCreatorUser.name).should('exist');
  }
);

When('the viewer user clicks on the dashboard', () => {
  cy.contains(dashboards.fromDashboardCreatorUser.name).click();
});

Then(
  "the viewer user can visualize the dashboard's layout but cannot share it or update its properties",
  () => {
    cy.url().should('match', /\/dashboards\/library\/\d+$/);

    cy.getByTestId({ testId: 'edit' }).should('not.exist');
    cy.getByTestId({ testId: 'share' }).should('not.exist');
  }
);

Given(
  'a non-admin user with the dashboard administrator role is logged in on a platform with dashboards',
  () => {
    cy.loginByTypeOfUser({
      jsonName: dashboardAdministratorUser.login,
      loginViaApi: true
    });

    cy.visitDashboards();
  }
);

When(
  'the dashboard administrator user sets another user as a second editor on a dashboard',
  () => {
    cy.contains(dashboards.fromDashboardAdministratorUser.name).click();
    cy.getByLabel({ label: 'share', tag: 'button' }).click();
    cy.getByLabel({ label: 'Add a contact', tag: 'input' }).click();
    cy.contains(dashboardCreatorUser.login).click();
    cy.getByTestId({ testId: 'add' }).should('be.enabled');
    cy.getByTestId({ testId: 'add' }).click();
    cy.getByTestId({ testId: `role-${dashboardCreatorUser.login}` })
      .eq(0)
      .realClick();
    cy.get('[role="listbox"]').contains('Editor').click();
    cy.get('*[class^="MuiList-root"]', { timeout: 12000 })
      .eq(1)
      .children()
      .its('length')
      .should('eq', 2);
    cy.get('*[class^="MuiList-root"]', { timeout: 12000 })
      .eq(1)
      .children()
      .eq(0)
      .should('contain', `${dashboardCreatorUser.login}`);

    cy.getByLabel({ label: 'Save', tag: 'button' })
      .should('be.enabled')
      .click();
    cy.wait('@updateShares');
    cy.wait('@getDashboard');
    cy.getByTestId({ testId: 'CloseIcon' }).eq(0).click();
    cy.get('.MuiAlert-message').should('not.exist');
    cy.waitUntilForDashboardRoles('share', 3);
  }
);

Then(
  "the second editor user is listed as an editor in the dashboard's share list",
  () => {
    cy.getByLabel({ label: 'share', tag: 'button' }).click();
    cy.get('*[class^="MuiList-root"]', { timeout: 12000 })
      .eq(1)
      .children()
      .contains(dashboardCreatorUser.login)
      .should('exist');

    cy.get('[data-state="added"]').should('not.exist');
    cy.getByLabel({ label: 'Cancel', tag: 'button' }).click();
    cy.logout();
    cy.getByLabel({ label: 'Alias', tag: 'input' }).should('exist');
  }
);

When('the second editor user logs in on the platform', () => {
  cy.loginByTypeOfUser({
    jsonName: dashboardCreatorUser.login,
    loginViaApi: true
  });

  cy.visitDashboards();
});

Then(
  "the dashboard is featured in the second editor user's dashboards library",
  () => {
    cy.contains(dashboards.fromDashboardAdministratorUser.name).should('exist');
  }
);

When('the second editor user clicks on the dashboard', () => {
  cy.contains(dashboards.fromDashboardCreatorUser.name).click();
});

Then(
  "the second editor can visualize the dashboard's layout and can share it or update its properties",
  () => {
    cy.url().should('match', /\/dashboards\/library\/\d+$/);

    cy.getByTestId({ testId: 'edit' }).should('be.enabled');
    cy.getByTestId({ testId: 'share' }).should('be.enabled');
  }
);

Given('a non-admin editor user with creator rights on a dashboard', () => {
  cy.loginByTypeOfUser({
    jsonName: dashboardCreatorUser.login,
    loginViaApi: true
  });

  cy.visitDashboards();
});

When(
  'the editor user sets read permissions on the dashboard to a contact group',
  () => {
    cy.contains(dashboards.fromDashboardCreatorUser.name).click();
    cy.getByLabel({ label: 'share', tag: 'button' }).click();
    cy.contains('Contact group').click();
    cy.getByLabel({ label: 'Add a contact group', tag: 'input' }).click();
    cy.contains('dashboard-contact-group-viewer').click();
    cy.getByTestId({ testId: 'add' }).should('be.enabled');
    cy.getByTestId({ testId: 'add_role' }).parent().click();
    cy.get('[role="listbox"]').contains('Viewer').click();
    cy.getByTestId({ testId: 'add' }).click();

    cy.get('*[class^="MuiList-root"]', { timeout: 12000 })
      .eq(1)
      .children()
      .its('length')
      .should('eq', 2);
    cy.get('*[class^="MuiList-root"]', { timeout: 12000 })
      .eq(1)
      .children()
      .eq(0)
      .should('contain', 'dashboard-contact-group-viewer');

    cy.getByLabel({ label: 'Save', tag: 'button' })
      .should('be.enabled')
      .click();
    cy.wait('@updateShares');
    cy.wait('@getDashboard');
    cy.getByTestId({ testId: 'CloseIcon' }).eq(0).click();
    cy.get('.MuiAlert-message').should('not.exist');
    cy.waitUntilForDashboardRoles('share', 3);
  }
);

Then(
  'any member of the contact group has access to the dashboard in the dashboards library but cannot share it or update its properties',
  () => {
    cy.getByLabel({ label: 'share', tag: 'button' }).click();
    cy.contains('Contact group').click();
    cy.get('*[class^="MuiList-root"]', { timeout: 12000 })
      .eq(1)
      .children()
      .contains('dashboard-contact-group-viewer')
      .should('exist');
    cy.getByTestId({ testId: 'role-dashboard-contact-group-viewer' }).should(
      'have.value',
      'viewer'
    );
    cy.get('[data-state="added"]').should('not.exist');
    cy.getByLabel({ label: 'Cancel', tag: 'button' }).click();

    cy.logout();
    cy.getByLabel({ label: 'Alias', tag: 'input' }).should('exist');

    cy.loginByTypeOfUser({
      jsonName: dashboardCGMember1.login,
      loginViaApi: true
    });

    cy.visitDashboard(dashboards.fromDashboardCreatorUser.name);

    cy.getByTestId({ testId: 'edit' }).should('not.exist');
    cy.getByTestId({ testId: 'share' }).should('not.exist');
    cy.logout();
    cy.getByLabel({ label: 'Alias', tag: 'input' }).should('exist');

    cy.loginByTypeOfUser({
      jsonName: dashboardCGMember2.login,
      loginViaApi: true
    });

    cy.visitDashboard(dashboards.fromDashboardCreatorUser.name);

    cy.getByTestId({ testId: 'edit' }).should('not.exist');
    cy.getByTestId({ testId: 'share' }).should('not.exist');
  }
);

Given('a non-admin editor user who has creator rights on a dashboard', () => {
  cy.loginByTypeOfUser({
    jsonName: dashboardCreatorUser.login,
    loginViaApi: true
  });

  cy.visitDashboards();
});

When(
  'the editor user sets write permissions on the dashboard to a contact group',
  () => {
    cy.contains(dashboards.fromDashboardCreatorUser.name).click();
    cy.getByLabel({ label: 'share', tag: 'button' }).click();
    cy.contains('Contact group').click();
    cy.getByLabel({ label: 'Add a contact group', tag: 'input' }).click();
    cy.contains('dashboard-contact-group-creator').click();
    cy.getByTestId({ testId: 'add' }).should('be.enabled');
    cy.getByTestId({ testId: 'add_role' }).parent().click();
    cy.get('[role="listbox"]').contains('Editor').click();
    cy.getByTestId({ testId: 'add' }).click();

    cy.get('*[class^="MuiList-root"]', { timeout: 12000 })
      .eq(1)
      .children()
      .its('length')
      .should('eq', 2);
    cy.get('*[class^="MuiList-root"]', { timeout: 12000 })
      .eq(1)
      .children()
      .eq(0)
      .should('contain', 'dashboard-contact-group-creator');

    cy.getByLabel({ label: 'Save', tag: 'button' })
      .should('be.enabled')
      .click();
    cy.wait('@updateShares');
    cy.wait('@getDashboard');
    cy.getByTestId({ testId: 'CloseIcon' }).eq(0).click();
    cy.get('.MuiAlert-message').should('not.exist');
    cy.waitUntilForDashboardRoles('share', 3);
  }
);

Then(
  'any member of the contact group has access to the dashboard in the dashboards library and can share it or update its properties',
  () => {
    cy.getByLabel({ label: 'share', tag: 'button' }).click();
    cy.contains('Contact group').click();
    cy.get('*[class^="MuiList-root"]', { timeout: 12000 })
      .eq(1)
      .children()
      .contains('dashboard-contact-group-creator')
      .should('exist');
    cy.getByTestId({ testId: 'role-dashboard-contact-group-creator' }).should(
      'have.value',
      'editor'
    );
    cy.get('[data-state="added"]').should('not.exist');
    cy.getByLabel({ label: 'Cancel', tag: 'button' }).click();

    cy.logout();
    cy.getByLabel({ label: 'Alias', tag: 'input' }).should('exist');

    cy.loginByTypeOfUser({
      jsonName: dashboardCGMember3.login,
      loginViaApi: true
    });

    cy.visitDashboard(dashboards.fromDashboardCreatorUser.name);

    cy.getByTestId({ testId: 'edit' }).should('be.enabled');
    cy.getByTestId({ testId: 'share' }).should('be.enabled');
    cy.logout();
    cy.getByLabel({ label: 'Alias', tag: 'input' }).should('exist');

    cy.loginByTypeOfUser({
      jsonName: dashboardCGMember4.login,
      loginViaApi: true
    });
    cy.visitDashboard(dashboards.fromDashboardCreatorUser.name);

    cy.getByTestId({ testId: 'edit' }).should('be.enabled');
    cy.getByTestId({ testId: 'share' }).should('be.enabled');
  }
);

Given(
  'a non-admin editor user who has update rights on a dashboard with read permissions given to a contact group',
  () => {
    cy.loginByTypeOfUser({
      jsonName: dashboardCreatorUser.login,
      loginViaApi: true
    });

    cy.visitDashboard(dashboards.fromDashboardCreatorUser.name);
    cy.getByLabel({ label: 'share', tag: 'button' }).click();
    cy.contains('Contact group').click();
    cy.getByLabel({ label: 'Add a contact group', tag: 'input' }).click();
    cy.contains('dashboard-contact-group-creator').click();
    cy.getByTestId({ testId: 'add' }).should('be.enabled');
    cy.getByTestId({ testId: 'add' }).click();
    cy.getByTestId({
      testId: 'role-dashboard-contact-group-creator'
    }).realClick();
    cy.get('[role="listbox"]').contains('Viewer').click();
    cy.get('*[class^="MuiList-root"]', { timeout: 12000 })
      .eq(1)
      .children()
      .its('length')
      .should('eq', 2);
    cy.get('*[class^="MuiList-root"]', { timeout: 12000 })
      .eq(1)
      .children()
      .eq(0)
      .should('contain', 'dashboard-contact-group-creator');

    cy.getByLabel({ label: 'Save', tag: 'button' })
      .should('be.enabled')
      .click();
    cy.wait('@updateShares');
    cy.wait('@getDashboard');
    cy.getByTestId({ testId: 'CloseIcon' }).eq(0).click();
    cy.get('.MuiAlert-message').should('not.exist');
    cy.waitUntilForDashboardRoles('share', 3);
  }
);

When(
  'the editor user sets write permissions on the dashboard to a specific user of the contact group',
  () => {
    cy.getByLabel({ label: 'share', tag: 'button' }).click();
    cy.contains('Contact').click();
    cy.getByLabel({ label: 'Add a contact', tag: 'input' }).click();
    cy.contains(dashboardCGMember3.login).click();
    cy.getByTestId({ testId: 'add' }).should('be.enabled');
    cy.getByTestId({ testId: `add_role` }).parent().click();
    cy.get('[role="listbox"]').contains('Editor').click();
    cy.getByTestId({ testId: 'add' }).click();

    cy.get('*[class^="MuiList-root"]', { timeout: 12000 })
      .eq(1)
      .children()
      .its('length')
      .should('eq', 3);
    cy.get('*[class^="MuiList-root"]', { timeout: 12000 })
      .eq(1)
      .children()
      .eq(0)
      .should('contain', `${dashboardCGMember3.login}`);

    cy.getByLabel({ label: 'Save', tag: 'button' })
      .should('be.enabled')
      .click();
    cy.get('.MuiAlert-message').should('not.exist');
    cy.wait('@updateShares');
  }
);

Then(
  'the user whose permissions have been overridden can perform write operations on the dashboard',
  () => {
    cy.logout();
    cy.getByLabel({ label: 'Alias', tag: 'input' }).should('exist');

    cy.loginByTypeOfUser({
      jsonName: dashboardCGMember3.login,
      loginViaApi: true
    });

    cy.visitDashboard(dashboards.fromDashboardCreatorUser.name);

    cy.getByTestId({ testId: 'edit' }).should('be.enabled');
    cy.getByTestId({ testId: 'share' }).should('be.enabled');
  }
);

Then(
  'the other users of the contact group still have read-only permissions on the dashboard',
  () => {
    cy.logout();
    cy.getByLabel({ label: 'Alias', tag: 'input' }).should('exist');

    cy.loginByTypeOfUser({
      jsonName: dashboardCGMember4.login,
      loginViaApi: true
    });

    cy.visitDashboard(dashboards.fromDashboardCreatorUser.name);

    cy.getByTestId({ testId: 'edit' }).should('not.exist');
    cy.getByTestId({ testId: 'share' }).should('not.exist');
  }
);

Given(
  "a dashboard featuring a dashboard administrator as editor, and three users who are not part of the dashboard's share list",
  () => {
    cy.loginByTypeOfUser({
      jsonName: dashboardAdministratorUser.login,
      loginViaApi: true
    });
    cy.visitDashboard(dashboards.fromDashboardAdministratorUser.name);
    cy.getByLabel({ label: 'share', tag: 'button' }).click();
  }
);

When('the admin user appoints one of the users as an editor', () => {
  cy.getByLabel({ label: 'Open', tag: 'button' }).click();
  cy.contains(dashboardCreatorUser.login).click();
  cy.getByTestId({ testId: 'add' }).should('be.enabled');
  cy.getByTestId({ testId: 'add' }).click();
  cy.getByTestId({ testId: `role-${dashboardCreatorUser.login}` }).realClick();
  cy.get('[role="listbox"]').contains('Editor').click();
  cy.getByLabel({ label: 'Save', tag: 'button' }).should('be.enabled').click();
  cy.wait('@updateShares');
});

Then(
  'the newly appointed editor user can appoint another user as an editor',
  () => {
    cy.visitDashboards();
    cy.logout();
    cy.getByLabel({ label: 'Alias', tag: 'input' }).should('exist');

    cy.loginByTypeOfUser({
      jsonName: dashboardCreatorUser.login,
      loginViaApi: true
    });
    cy.visitDashboard(dashboards.fromDashboardAdministratorUser.name);
    cy.getByLabel({ label: 'share', tag: 'button' }).click();
    cy.contains('Contact').click();
    cy.getByLabel({ label: 'Open', tag: 'button' }).click();
    cy.contains(dashboardCGMember3.login).click();
    cy.getByTestId({ testId: 'add' }).should('be.enabled');
    cy.getByTestId({ testId: 'add' }).click();
    cy.getByTestId({ testId: `role-${dashboardCGMember3.login}` }).realClick();
    cy.get('[role="listbox"]').contains('Editor').click();
    cy.getByLabel({ label: 'Save', tag: 'button' })
      .should('be.enabled')
      .click();
    cy.wait('@updateShares');
    cy.wait('@getDashboard');
    cy.getByTestId({ testId: 'CloseIcon' }).eq(0).click();
    cy.get('.MuiAlert-message').should('not.exist');
    cy.waitUntilForDashboardRoles('share', 4);
    cy.getByLabel({ label: 'share', tag: 'button' }).click();
    cy.get('*[class^="MuiList-root"]', { timeout: 12000 })
      .eq(1)
      .children()
      .contains(dashboardCGMember3.login)
      .should('exist');

    cy.get('[data-state="added"]').should('not.exist');
    cy.getByLabel({ label: 'Cancel', tag: 'button' }).click();
  }
);

Then(
  'the newly appointed editor user can appoint another user as a viewer',
  () => {
    cy.getByLabel({ label: 'share', tag: 'button' }).click();
    cy.getByLabel({ label: 'Open', tag: 'button' }).click();
    cy.contains(dashboardViewerUser.login).click();
    cy.getByTestId({ testId: 'add' }).should('be.enabled');
    cy.getByTestId({ testId: 'add' }).click();
    cy.getByTestId({ testId: `role-${dashboardViewerUser.login}` }).realClick();
    cy.get('[role="listbox"]').contains('Viewer').click();
    cy.getByLabel({ label: 'Save', tag: 'button' })
      .should('be.enabled')
      .click();
    cy.wait('@updateShares');
    cy.wait('@getDashboard');
    cy.getByTestId({ testId: 'CloseIcon' }).eq(0).click();
    cy.get('.MuiAlert-message').should('not.exist');
    cy.waitUntilForDashboardRoles('share', 5);
    cy.getByLabel({ label: 'share', tag: 'button' }).click();
    cy.get('*[class^="MuiList-root"]', { timeout: 12000 })
      .eq(1)
      .children()
      .contains(dashboardViewerUser.login)
      .should('exist');

    cy.get('[data-state="added"]').should('not.exist');
    cy.getByLabel({ label: 'Cancel', tag: 'button' }).click();
  }
);
