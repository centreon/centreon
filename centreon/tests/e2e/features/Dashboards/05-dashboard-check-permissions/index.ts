import { Given, When, Then } from '@badeball/cypress-cucumber-preprocessor';
import { last } from 'ramda';

import dashboards from '../../../fixtures/dashboards/check-permissions/dashboards.json';
import adminUser from '../../../fixtures/users/admin.json';
import dashboardAdministratorUser from '../../../fixtures/users/user-dashboard-administrator.json';
import dashboardCreatorUser from '../../../fixtures/users/user-dashboard-creator.json';
import dashboardViewerUser from '../../../fixtures/users/user-dashboard-viewer.json';

before(() => {
  cy.startContainers();
  cy.enableDashboardFeature();
  cy.executeCommandsViaClapi(
    'resources/clapi/config-ACL/dashboard-check-permissions.json'
  );
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/internal.php?object=centreon_topology&action=navigationList'
  }).as('getNavigationList');
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/latest/configuration/dashboards**'
  }).as('listAllDashboards');
  cy.intercept({
    method: 'POST',
    url: '/centreon/api/latest/configuration/dashboards'
  }).as('createDashboard');
  cy.loginByTypeOfUser({
    jsonName: adminUser.login,
    loginViaApi: true
  });
  cy.insertDashboard(dashboards.fromAdminUser);
  cy.logoutViaAPI();
  cy.loginByTypeOfUser({
    jsonName: dashboardAdministratorUser.login,
    loginViaApi: true
  });
  cy.insertDashboard(dashboards.fromDashboardAdministratorUser);
  cy.logoutViaAPI();
  cy.loginByTypeOfUser({
    jsonName: dashboardCreatorUser.login,
    loginViaApi: true
  });
  cy.insertDashboard(dashboards.fromDashboardCreatorUser);
  cy.logoutViaAPI();
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
    method: 'POST',
    url: '/centreon/api/latest/configuration/dashboards'
  }).as('createDashboard');
});

after(() => {
  cy.requestOnDatabase({
    database: 'centreon',
    query: 'DELETE FROM dashboard'
  });
  cy.stopContainers();
});

afterEach(() => {
  cy.visit('/centreon/home/dashboards');
  cy.logout();
});

Given('an admin user is logged in on a platform with dashboards', () => {
  cy.loginByTypeOfUser({
    jsonName: adminUser.login,
    loginViaApi: false
  });
});

When('the admin user accesses the dashboards library', () => {
  cy.visitDashboards();
});

Then(
  'the admin user can view all the dashboards configured on the platform',
  () => {
    cy.contains(dashboards.fromAdminUser.name).should('exist');

    cy.contains(dashboards.fromDashboardAdministratorUser.name).should('exist');

    cy.contains(dashboards.fromDashboardCreatorUser.name).should('exist');
  }
);

When('the admin user clicks on a dashboard', () => {
  cy.contains(dashboards.fromAdminUser.name).click();
});

Then(
  'the admin user is redirected to the detail page for this dashboard',
  () => {
    cy.location('pathname')
      .should('include', '/dashboards/')
      .invoke('split', '/')
      .should('not.be.empty')
      .then(last)
      .then(Number)
      .should('not.be', 'dashboards')
      .should('be.a', 'number');

    cy.getByLabel({ label: 'page header title' }).should(
      'contain.text',
      dashboards.fromAdminUser.name
    );

    cy.getByLabel({ label: 'page header description' }).should(
      'contain.text',
      dashboards.fromAdminUser.description
    );
  }
);

Then(
  'the admin user is allowed to access the edit mode for this dashboard',
  () => {
    cy.getByTestId({ testId: 'edit_dashboard' }).click();
    cy.location('search').should('include', 'edit=true');
    cy.get('button[type=button]').contains('Add a widget').should('exist');
    cy.getByLabel({ label: 'Cancel', tag: 'button' }).click();
  }
);

Then("the admin user is allowed to update the dashboard's properties", () => {
  cy.getByLabel({ label: 'edit', tag: 'button' }).click();

  cy.contains('div.MuiDialog-container', 'Update dashboard').within(() => {
    cy.get('input[aria-label="Name"]').type(
      `{selectall}{backspace}${dashboards.fromAdminUser.name}-edited`
    );

    cy.get('textarea[aria-label="Description"]').type(
      `{selectall}{backspace}${dashboards.fromAdminUser.description}, edited by ${adminUser.login}`
    );

    cy.getByLabel({ label: 'Update', tag: 'button' })
      .should('be.enabled')
      .click();
  });

  cy.getByLabel({ label: 'page header title' })
    .should('contain.text', `${dashboards.fromAdminUser.name}-edited`)
    .should('be.visible');

  cy.getByLabel({ label: 'page header description' })
    .should(
      'contain.text',
      `${dashboards.fromAdminUser.description}, edited by ${adminUser.login}`
    )
    .should('be.visible');
});

Given('an admin user on the dashboards library', () => {
  cy.loginByTypeOfUser({
    jsonName: adminUser.login,
    loginViaApi: false
  });
});

When('the admin user creates a new dashboard', () => {
  cy.visitDashboards();
  cy.getByTestId({ testId: 'create-dashboard' }).eq(0).click();
  cy.getByLabel({ label: 'Name', tag: 'input' }).type(
    dashboards.fromCurrentUser.name
  );
  cy.getByLabel({ label: 'Description', tag: 'textarea' }).type(
    `created by ${adminUser.login}`
  );
  cy.getByTestId({ testId: 'submit' }).click();
  cy.wait('@createDashboard');
});

Then(
  'the dashboard is created and is noted as the creation of the admin user',
  () => {
    cy.getByLabel({ label: 'page header title' }).should(
      'contain.text',
      dashboards.fromCurrentUser.name
    );
    cy.getByLabel({ label: 'page header description' }).should(
      'contain.text',
      `created by ${adminUser.login}`
    );
    cy.getByLabel({ label: 'Cancel', tag: 'button' }).click();
    cy.getByLabel({ label: 'share', tag: 'button' }).click();
    cy.contains('admin admin').should('be.visible');
    cy.getByTestId({ testId: 'role-admin admin' }).should(
      'have.value',
      'editor'
    );
    cy.getByLabel({ label: 'Cancel', tag: 'button' }).click();
  }
);

Given('an admin user who has just created a dashboard', () => {
  cy.loginByTypeOfUser({
    jsonName: adminUser.login,
    loginViaApi: true
  });

  cy.visitDashboards();

  cy.contains(dashboards.fromCurrentUser.name).should('exist');
});

When('the admin user deletes the newly created dashboard', () => {
  cy.contains(dashboards.fromCurrentUser.name)
    .parent()
    .parent()
    .find('button[aria-label="More actions"]')
    .click();

  cy.getByLabel({ label: 'Delete', tag: 'li' }).click();

  cy.getByLabel({ label: 'Delete', tag: 'button' }).click();
  cy.wait('@listAllDashboards');
});

Then(
  "the admin's dashboard is deleted and does not appear anymore in the dashboards library",
  () => {
    cy.contains(dashboards.fromCurrentUser.name).should('not.exist');
  }
);

Given(
  'a non-admin user with the dashboard administrator role is logged in on a platform with dashboards',
  () => {
    cy.loginByTypeOfUser({
      jsonName: dashboardAdministratorUser.login,
      loginViaApi: false
    });
  }
);

When('the dashboard administrator user accesses the dashboards library', () => {
  cy.visitDashboards();
});

Then(
  'the dashboard administrator user can consult all the dashboards configured on the platform',
  () => {
    cy.contains(dashboards.fromAdminUser.name).should('exist');

    cy.contains(dashboards.fromDashboardAdministratorUser.name).should('exist');

    cy.contains(dashboards.fromDashboardCreatorUser.name).should('exist');
  }
);

When('the dashboard administrator user clicks on a dashboard', () => {
  cy.contains(dashboards.fromDashboardAdministratorUser.name).click();
});

Then(
  'the dashboard administrator user is redirected to the detail page for this dashboard',
  () => {
    cy.location('pathname')
      .should('include', '/dashboards/')
      .invoke('split', '/')
      .should('not.be.empty')
      .then(last)
      .then(Number)
      .should('not.be', 'dashboards')
      .should('be.a', 'number');

    cy.getByLabel({ label: 'page header title' }).should(
      'contain.text',
      dashboards.fromDashboardAdministratorUser.name
    );

    cy.getByLabel({ label: 'page header description' }).should(
      'contain.text',
      dashboards.fromDashboardAdministratorUser.description
    );
  }
);

Then(
  'the dashboard administrator user is allowed to access the edit mode for this dashboard',
  () => {
    cy.getByTestId({ testId: 'edit_dashboard' }).click();
    cy.location('search').should('include', 'edit=true');
    cy.get('button[type=button]').contains('Add a widget').should('exist');
    cy.getByLabel({ label: 'Cancel', tag: 'button' }).click();
  }
);

Then(
  "the dashboard administrator user is allowed to update the dashboard's properties",
  () => {
    cy.getByLabel({ label: 'edit', tag: 'button' }).click();

    cy.contains('div.MuiDialog-container', 'Update dashboard').within(() => {
      cy.get('input[aria-label="Name"]').type(
        `{selectall}{backspace}${dashboards.fromDashboardAdministratorUser.name}-edited`
      );

      cy.get('textarea[aria-label="Description"]').type(
        `{selectall}{backspace}${dashboards.fromDashboardAdministratorUser.description}, edited by ${dashboardAdministratorUser.login}`
      );

      cy.get('button[aria-label="Update"]').should('be.enabled').click();
    });

    cy.getByLabel({ label: 'page header title' })
      .should('be.visible')
      .should(
        'contain.text',
        `${dashboards.fromDashboardAdministratorUser.name}-edited`
      );

    cy.getByLabel({ label: 'page header description' })
      .should('be.visible')
      .should(
        'contain.text',
        `${dashboards.fromDashboardAdministratorUser.description}, edited by ${dashboardAdministratorUser.login}`
      );
  }
);

Given(
  'a non-admin user with the administrator role on the dashboard feature',
  () => {
    cy.loginByTypeOfUser({
      jsonName: dashboardAdministratorUser.login,
      loginViaApi: false
    });
  }
);

When('the dashboard administrator user creates a new dashboard', () => {
  cy.visitDashboards();
  cy.getByTestId({ testId: 'create-dashboard' }).eq(0).click();
  cy.getByLabel({ label: 'Name', tag: 'input' }).type(
    dashboards.fromCurrentUser.name
  );
  cy.getByLabel({ label: 'Description', tag: 'textarea' }).type(
    `created by ${dashboardAdministratorUser.login}`
  );
  cy.getByTestId({ testId: 'submit' }).click();
  cy.wait('@createDashboard');
});

Then(
  'the dashboard is created and is noted as the creation of the dashboard administrator user',
  () => {
    cy.getByLabel({ label: 'page header title' }).should(
      'contain.text',
      dashboards.fromCurrentUser.name
    );
    cy.getByLabel({ label: 'page header description' }).should(
      'contain.text',
      `created by ${dashboardAdministratorUser.login}`
    );
    cy.getByLabel({ label: 'Cancel', tag: 'button' }).click();
    cy.getByLabel({ label: 'share', tag: 'button' }).click();
    cy.contains(`${dashboardAdministratorUser.login}`).should('be.visible');
    cy.getByTestId({
      testId: `role-${dashboardAdministratorUser.login}`
    }).should('have.value', 'editor');
    cy.getByLabel({ label: 'Cancel', tag: 'button' }).click();
  }
);

Given('a dashboard administrator user who has just created a dashboard', () => {
  cy.loginByTypeOfUser({
    jsonName: dashboardAdministratorUser.login,
    loginViaApi: true
  });

  cy.visitDashboards();
  cy.contains(dashboards.fromCurrentUser.name).should('exist');
});

When(
  'the dashboard administrator user deletes the newly created dashboard',
  () => {
    cy.contains(dashboards.fromCurrentUser.name)
      .parent()
      .parent()
      .find('button[aria-label="More actions"]')
      .click();

    cy.getByLabel({ label: 'Delete', tag: 'li' }).click();

    cy.getByLabel({ label: 'Delete', tag: 'button' }).click();
    cy.wait('@listAllDashboards');
  }
);

Then(
  "the dashboard administrator's dashboard is deleted and does not appear anymore in the dashboards library",
  () => {
    cy.contains(dashboards.fromCurrentUser.name).should('not.exist');
  }
);

Given(
  'a non-admin user with the dashboard editor role is logged in on a platform with dashboards',
  () => {
    cy.loginByTypeOfUser({
      jsonName: dashboardCreatorUser.login,
      loginViaApi: false
    });
  }
);

When('the dashboard editor user accesses the dashboards library', () => {
  cy.visitDashboards();
});

Then(
  'a list of the dashboards the dashboard editor user has access to is displayed',
  () => {
    cy.contains(dashboards.fromAdminUser.name).should('not.exist');

    cy.contains(dashboards.fromDashboardAdministratorUser.name).should(
      'not.exist'
    );

    cy.contains(dashboards.fromDashboardCreatorUser.name).should('exist');
  }
);

When('the dashboard editor user clicks on a dashboard', () => {
  cy.contains(dashboards.fromDashboardCreatorUser.name).click();
});

Then(
  'the dashboard editor user is redirected to the detail page for this dashboard',
  () => {
    cy.location('pathname')
      .should('include', '/dashboards/')
      .invoke('split', '/')
      .should('not.be.empty')
      .then(last)
      .then(Number)
      .should('not.be', 'dashboards')
      .should('be.a', 'number');

    cy.getByLabel({ label: 'page header title' }).should(
      'contain.text',
      dashboards.fromDashboardCreatorUser.name
    );

    cy.getByLabel({ label: 'page header description' }).should(
      'contain.text',
      dashboards.fromDashboardCreatorUser.description
    );
  }
);

Then(
  'the dashboard editor user is allowed to access the edit mode for this dashboard',
  () => {
    cy.getByTestId({ testId: 'edit_dashboard' }).click();
    cy.location('search').should('include', 'edit=true');
    cy.get('button[type=button]').contains('Add a widget').should('exist');
    cy.getByLabel({ label: 'Cancel', tag: 'button' }).click();
    cy.getByTestId({ testId: 'edit_dashboard' }).should('be.visible');
  }
);

Then(
  "the dashboard editor user is allowed to update the dashboard's properties",
  () => {
    cy.getByLabel({ label: 'edit', tag: 'button' }).click();

    cy.contains('div.MuiDialog-container', 'Update dashboard').within(() => {
      cy.get('input[aria-label="Name"]').type(
        `{selectall}{backspace}${dashboards.fromDashboardCreatorUser.name}-edited`
      );

      cy.get('textarea[aria-label="Description"]').type(
        `{selectall}{backspace}${dashboards.fromDashboardCreatorUser.description}, edited by ${dashboardCreatorUser.login}`
      );

      cy.get('button[aria-label="Update"]').should('be.enabled').click();
    });

    cy.getByLabel({ label: 'page header title' })
      .should(
        'contain.text',
        `${dashboards.fromDashboardCreatorUser.name}-edited`
      )
      .should('be.visible');

    cy.getByLabel({ label: 'page header description' })
      .should(
        'contain.text',
        `${dashboards.fromDashboardCreatorUser.description}, edited by ${dashboardCreatorUser.login}`
      )
      .should('be.visible');
  }
);

Given('a non-admin user with the editor role on the dashboard feature', () => {
  cy.loginByTypeOfUser({
    jsonName: dashboardCreatorUser.login,
    loginViaApi: false
  });
});

When('the dashboard editor user creates a new dashboard', () => {
  cy.navigateTo({
    page: 'Dashboards',
    rootItemNumber: 0
  });
  cy.wait('@listAllDashboards');
  cy.getByTestId({ testId: 'create-dashboard' }).eq(0).click();
  cy.getByLabel({ label: 'Name', tag: 'input' }).type(
    dashboards.fromCurrentUser.name
  );
  cy.getByLabel({ label: 'Description', tag: 'textarea' }).type(
    `created by ${dashboardCreatorUser.login}`
  );
  cy.getByTestId({ testId: 'submit' }).click();
  cy.wait('@createDashboard');
});

Then(
  'the dashboard is created and is noted as the creation of the dashboard editor user',
  () => {
    cy.getByLabel({ label: 'page header title' }).should(
      'contain.text',
      dashboards.fromCurrentUser.name
    );
    cy.getByLabel({ label: 'page header description' }).should(
      'contain.text',
      `created by ${dashboardCreatorUser.login}`
    );
    cy.getByLabel({ label: 'Cancel', tag: 'button' }).click();
    cy.getByLabel({ label: 'share', tag: 'button' }).click();
    cy.contains(`${dashboardCreatorUser.login}`).should('be.visible');
    cy.getByTestId({ testId: `role-${dashboardCreatorUser.login}` }).should(
      'have.value',
      'editor'
    );
    cy.getByLabel({ label: 'Cancel', tag: 'button' }).click();
  }
);

Given('a dashboard editor user who has just created a dashboard', () => {
  cy.loginByTypeOfUser({
    jsonName: dashboardCreatorUser.login,
    loginViaApi: false
  });
  cy.visitDashboards();

  cy.contains(dashboards.fromCurrentUser.name).should('exist');
});

When('the dashboard editor user deletes the newly created dashboard', () => {
  cy.contains(dashboards.fromCurrentUser.name)
    .parent()
    .parent()
    .find('button[aria-label="More actions"]')
    .click();

  cy.getByLabel({ label: 'Delete', tag: 'li' }).click();

  cy.getByLabel({ label: 'Delete', tag: 'button' }).click();
  cy.wait('@listAllDashboards');
});

Then(
  "the dashboard editor's dashboard is deleted and does not appear anymore in the dashboards library",
  () => {
    cy.contains(dashboards.fromCurrentUser.name).should('not.exist');
  }
);

Given(
  'a non-admin user with the dashboard viewer role is logged in on a platform',
  () => {
    cy.loginByTypeOfUser({
      jsonName: dashboardAdministratorUser.login,
      loginViaApi: true
    });
    cy.visitDashboard(dashboards.fromDashboardCreatorUser.name);
    cy.getByLabel({ label: 'share', tag: 'button' }).click();
    cy.getByLabel({ label: 'Open', tag: 'button' }).click();
    cy.contains(dashboardViewerUser.login).click();
    cy.getByTestId({ testId: 'add' }).click();
    cy.getByLabel({ label: 'Save', tag: 'button' })
      .should('be.enabled')
      .click();
    cy.logoutViaAPI();
    cy.loginByTypeOfUser({
      jsonName: dashboardViewerUser.login,
      loginViaApi: false
    });
  }
);

When('the dashboard viewer user accesses the dashboards library', () => {
  cy.visit('/centreon/home/dashboards');
});

Then(
  'a list of the dashboards the dashboard viewer user has access to is displayed',
  () => {
    cy.contains(dashboards.fromAdminUser.name).should('not.exist');

    cy.contains(dashboards.fromDashboardAdministratorUser.name).should(
      'not.exist'
    );

    cy.contains(dashboards.fromDashboardCreatorUser.name).should('exist');
  }
);

When('the dashboard viewer user clicks on a dashboard', () => {
  cy.contains(dashboards.fromDashboardCreatorUser.name).click();
});

Then(
  'the dashboard viewer user is redirected to the detail page for this dashboard',
  () => {
    cy.location('pathname')
      .should('include', '/dashboards/')
      .invoke('split', '/')
      .should('not.be.empty')
      .then(last)
      .then(Number)
      .should('not.be', 'dashboards')
      .should('be.a', 'number');

    cy.getByLabel({ label: 'page header title' }).should(
      'contain.text',
      dashboards.fromDashboardCreatorUser.name
    );
    cy.getByLabel({ label: 'page header description' }).should(
      'contain.text',
      dashboards.fromDashboardCreatorUser.description
    );
  }
);

Then(
  'the dashboard viewer user does not have access to any update or share-related options on a dashboard',
  () => {
    cy.getByTestId({ testId: 'edit' }).should('not.exist');
    cy.getByTestId({ testId: 'share' }).should('not.exist');
  }
);

Given('a non-admin user with the viewer role on the dashboard feature', () => {
  cy.loginByTypeOfUser({
    jsonName: dashboardViewerUser.login,
    loginViaApi: false
  });
});

When('the dashboard viewer accesses the dashboards library', () => {
  cy.navigateTo({
    page: 'Dashboards',
    rootItemNumber: 0
  });
  cy.wait('@listAllDashboards');
});

Then('the option to create a new dashboard is not displayed', () => {
  cy.getByTestId({ testId: 'create-dashboard' }).should('not.exist');
});

Given('a dashboard viewer user who could not create a dashboard', () => {
  cy.loginByTypeOfUser({
    jsonName: dashboardViewerUser.login,
    loginViaApi: true
  });

  cy.visitDashboards();
});

When('the dashboard viewer user tries to delete a dashboard', () => {
  cy.contains(dashboards.fromDashboardCreatorUser.name).should('exist');
});

Then('the button to delete a dashboard does not appear', () => {
  cy.getByTestId({ testId: 'delete' }).should('not.exist');
});
