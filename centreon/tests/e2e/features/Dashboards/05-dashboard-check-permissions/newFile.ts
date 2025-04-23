import { Given } from '@badeball/cypress-cucumber-preprocessor';

import dashboards from '../../../fixtures/dashboards/check-permissions/dashboards.json';
import dashboardAdministratorUser from '../../../fixtures/users/user-dashboard-administrator.json';

Given(
  'a non-admin user with the dashboard viewer role is logged in on a platform with dashboards',
  () => {
    cy.loginByTypeOfUser({
      jsonName: dashboardAdministratorUser.login,
      loginViaApi: true
    });
    cy.visit('/centreon/home/dashboards');
    cy.contains(dashboards.fromDashboardCreatorUser.name).click();
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
