import { Given, When, Then } from '@badeball/cypress-cucumber-preprocessor';

import dashboards from '../../../fixtures/dashboards/check-permissions/dashboards.json';
import dashboardAdministratorUser from '../../../fixtures/users/user-dashboard-administrator.json';
import dashboardCreatorUser from '../../../fixtures/users/user-dashboard-creator.json';
import dashboardViewerUser from '../../../fixtures/users/user-dashboard-viewer.json';

before(() => {
  cy.startWebContainer();
  cy.execInContainer({
    command: `sed -i 's@"dashboard": 0@"dashboard": 3@' /usr/share/centreon/config/features.json`,
    name: Cypress.env('dockerName')
  });
  cy.executeCommandsViaClapi(
    'resources/clapi/config-ACL/dashboard-check-permissions.json'
  );
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
    url: '/centreon/api/latest/configuration/dashboards'
  }).as('createDashboard');
  cy.loginByTypeOfUser({
    jsonName: dashboardAdministratorUser.login,
    loginViaApi: true
  });
  cy.insertDashboard({ ...dashboards.fromAdministratorUser });
  cy.logoutViaAPI();
  cy.loginByTypeOfUser({
    jsonName: dashboardCreatorUser.login,
    loginViaApi: true
  });
  cy.insertDashboard({ ...dashboards.fromCreatorUser });
  cy.shareDashboardToUser({
    dashboardName: dashboards.fromCreatorUser.name,
    role: 'viewer',
    userName: dashboardViewerUser.login
  });
  cy.logoutViaAPI();
});

after(() => {
  cy.stopWebContainer();
});

afterEach(() => {
  cy.requestOnDatabase({
    database: 'centreon',
    query: 'DELETE FROM dashboard'
  });
  cy.logout();
});

Given('an admin user is logged in on a platform with dashboards', () => {
  cy.loginByTypeOfUser({
    jsonName: 'admin',
    loginViaApi: false
  });
});

When('the admin user accesses the dashboards library', () => {
  cy.visit(`${Cypress.config().baseUrl}/centreon/home/dashboards`);
});

Then(
  'the admin user can view all the dashboards configured on the platform',
  () => {
    cy.getByLabel({
      label: 'view',
      tag: 'button'
    })
      .contains(dashboards.fromAdministratorUser.name)
      .should('exist');

    cy.getByLabel({
      label: 'view',
      tag: 'button'
    })
      .contains(dashboards.fromCreatorUser.name)
      .should('exist');
  }
);

When('the admin user clicks on a dashboard', () => {
  cy.getByLabel({
    label: 'view',
    tag: 'button'
  })
    .contains(dashboards.fromAdministratorUser.name)
    .click();
});

Then(
  'the admin user is redirected to the detail page for this dashboard',
  () => {
    cy.location('pathname')
      .should('include', '/dashboards/')
      .invoke('split', '/')
      .should('not.be.empty')
      .then(Cypress._.last)
      .then(Number)
      .should('not.be', 'dashboards')
      .should('be.a', 'number');

    cy.getByLabel({ label: 'page header title' }).should(
      'contain.text',
      dashboards.fromAdministratorUser.name
    );
  }
);

Then(
  'the admin user is allowed to access the edit mode for this dashboard',
  () => {
    cy.getByTestId({ testId: 'edit_dashboard' }).click();
    cy.location('search').should('include', 'edit=true');
    cy.getByLabel({ label: 'add widget', tag: 'button' }).should('be.enabled');
    cy.getByLabel({ label: 'Exit', tag: 'button' }).click();
  }
);

Then("the admin user is allowed to update the dashboard's properties", () => {
  cy.getByLabel({ label: 'edit', tag: 'button' }).click();

  cy.getByLabel({ label: 'Name', tag: 'input' }).clear();
  cy.getByLabel({ label: 'Name', tag: 'input' }).type(
    `${dashboards.fromAdministratorUser.name}-edited`
  );
  cy.getByLabel({ label: 'Description', tag: 'textarea' }).clear();
  cy.getByLabel({ label: 'Description', tag: 'textarea' }).type(
    `${dashboards.fromAdministratorUser.description} and admin`
  );

  cy.getByLabel({ label: 'Update', tag: 'button' }).should('be.enabled');
  cy.getByLabel({ label: 'Update', tag: 'button' }).click();

  cy.reload();
  cy.contains(`${dashboards.fromAdministratorUser.name}-edited`).should(
    'exist'
  );
  cy.contains(
    `${dashboards.fromAdministratorUser.description} and admin`
  ).should('exist');
});

Given('an admin user on the dashboards library', () => {
  cy.loginByTypeOfUser({
    jsonName: 'admin',
    loginViaApi: false
  });
});

When('the admin user creates a new dashboard', () => {
  cy.visit(`${Cypress.config().baseUrl}/centreon/home/dashboards`);
  cy.getByLabel({ label: 'create', tag: 'button' }).click();
  cy.getByLabel({ label: 'Name', tag: 'input' }).type(
    dashboards.fromCurrentUser.name
  );
  cy.getByLabel({ label: 'Description', tag: 'textarea' }).type(
    'by admin user'
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
      'by admin user'
    );
    cy.getByLabel({ label: 'Exit', tag: 'button' }).click();
    cy.getByLabel({ label: 'share', tag: 'button' }).click();
    cy.contains('admin admin').should('be.visible');
    cy.getByTestId({ testId: 'role-input' }).should('contain.text', 'editor');
    cy.getByLabel({ label: 'Cancel', tag: 'button' }).click();
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
  cy.visit(`${Cypress.config().baseUrl}/centreon/home/dashboards`);
});

Then(
  'the dashboard administrator user can consult all the dashboards configured on the platform',
  () => {
    cy.getByLabel({
      label: 'view',
      tag: 'button'
    })
      .contains(dashboards.fromAdministratorUser.name)
      .should('exist');

    cy.getByLabel({
      label: 'view',
      tag: 'button'
    })
      .contains(dashboards.fromCreatorUser.name)
      .should('exist');
  }
);

When('the dashboard administrator user clicks on a dashboard', () => {
  cy.getByLabel({
    label: 'view',
    tag: 'button'
  })
    .contains(dashboards.fromAdministratorUser.name)
    .click();
});

Then(
  'the dashboard administrator user is redirected to the detail page for this dashboard',
  () => {
    cy.location('pathname')
      .should('include', '/dashboards/')
      .invoke('split', '/')
      .should('not.be.empty')
      .then(Cypress._.last)
      .then(Number)
      .should('not.be', 'dashboards')
      .should('be.a', 'number');

    cy.getByLabel({ label: 'page header title' }).should(
      'contain.text',
      dashboards.fromAdministratorUser.name
    );
  }
);

Then(
  'the dashboard administrator user is allowed to access the edit mode for this dashboard',
  () => {
    cy.getByTestId({ testId: 'edit_dashboard' }).click();
    cy.location('search').should('include', 'edit=true');
    cy.getByLabel({ label: 'add widget', tag: 'button' }).should('be.enabled');
    cy.getByLabel({ label: 'Exit', tag: 'button' }).click();
  }
);

Then(
  "the dashboard administrator user is allowed to update the dashboard's properties",
  () => {
    cy.getByLabel({ label: 'edit', tag: 'button' }).click();

    cy.getByLabel({ label: 'Name', tag: 'input' }).clear();
    cy.getByLabel({ label: 'Name', tag: 'input' }).type(
      `${dashboards.fromAdministratorUser.name}-edited`
    );
    cy.getByLabel({ label: 'Description', tag: 'textarea' }).clear();
    cy.getByLabel({ label: 'Description', tag: 'textarea' }).type(
      `${dashboards.fromAdministratorUser.description} and ${dashboardAdministratorUser.login}`
    );

    cy.getByLabel({ label: 'Update', tag: 'button' }).should('be.enabled');
    cy.getByLabel({ label: 'Update', tag: 'button' }).click();

    cy.reload();
    cy.contains(`${dashboards.fromAdministratorUser.name}-edited`).should(
      'exist'
    );
    cy.contains(
      `${dashboards.fromAdministratorUser.description} and ${dashboardAdministratorUser.login}`
    ).should('exist');
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
  cy.visit(`${Cypress.config().baseUrl}/centreon/home/dashboards`);
  cy.getByLabel({ label: 'create', tag: 'button' }).click();
  cy.getByLabel({ label: 'Name', tag: 'input' }).type(
    dashboards.fromCurrentUser.name
  );
  cy.getByLabel({ label: 'Description', tag: 'textarea' }).type(
    `by ${dashboardAdministratorUser.login}`
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
      `by ${dashboardAdministratorUser.login}`
    );
    cy.getByLabel({ label: 'Exit', tag: 'button' }).click();
    cy.getByLabel({ label: 'share', tag: 'button' }).click();
    cy.contains(`${dashboardAdministratorUser.login}`).should('be.visible');
    cy.getByTestId({ testId: 'role-input' }).should('contain.text', 'editor');
    cy.getByLabel({ label: 'Cancel', tag: 'button' }).click();
  }
);
