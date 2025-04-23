import { Given, When, Then } from '@badeball/cypress-cucumber-preprocessor';

import dashboardAdministratorUser from '../../../fixtures/users/user-dashboard-administrator.json';
import dashboards from '../../../fixtures/dashboards/creation/dashboards.json';
import webPageWidget from '../../../fixtures/dashboards/creation/widgets/dashboardWithWebPageWidget.json';

before(() => {
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/internal.php?object=centreon_topology&action=navigationList'
  }).as('getNavigationList');
  cy.intercept({
    method: 'GET',
    url: /\/centreon\/api\/latest\/monitoring\/resources.*$/
  }).as('resourceRequest');
  cy.startContainers();
  cy.enableDashboardFeature();
  cy.executeCommandsViaClapi(
    'resources/clapi/config-ACL/dashboard-metrics-graph.json'
  );
  cy.applyAcl();
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
    method: 'PATCH',
    url: `/centreon/api/latest/configuration/dashboards/*`
  }).as('updateDashboard');
  cy.intercept({
    method: 'GET',
    url: `/centreon/api/latest/configuration/dashboards/*`
  }).as('getDashboard');
  cy.intercept({
    method: 'GET',
    url: /\/api\/latest\/monitoring\/dashboard\/metrics\/performances\/data\?.*$/
  }).as('performanceData');
  cy.intercept({
    method: 'GET',
    url: /\/centreon\/api\/latest\/monitoring\/resources.*$/
  }).as('resourceRequest');
  cy.intercept({
    method: 'POST',
    url: '/centreon/api/latest/configuration/dashboards/favorites'
  }).as('addFavorites');
    cy.intercept({
    method: 'DELETE',
    url: '/centreon/api/latest/configuration/dashboards/*/favorites'
  }).as('deleteFavorites');
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/latest/configuration/dashboards/favorites?page=1&limit=10*'
  }).as('getFavorites');
  cy.loginByTypeOfUser({
    jsonName: dashboardAdministratorUser.login,
    loginViaApi: false
  });
});

after(() => {
  cy.stopContainers();
});

Given('a dashboard having a configured web page widget', () => {
  cy.insertDashboard({ ...dashboards.default });
  cy.visitDashboards();
});

When('the dashboard administrator clicks on the favourite icon', () => {
  cy.getByTestId({ testId: 'FavoriteIcon' }).click();
  cy.wait('@addFavorites');
  cy.contains('Show only dashboards added to favorites').click();

  cy.wait('@getFavorites').then((interception) => {
    expect(interception.response?.statusCode).to.eq(200);

    const responseBody = interception.response?.body;

    expect(responseBody.result).to.be.an('array');
    expect(responseBody.result).to.have.length.greaterThan(0);

    const dashboard = responseBody.result.find((item) => item.name === 'dashboard default');
    expect(dashboard).to.exist;
    expect(dashboard.name).to.eq('dashboard default');
    expect(dashboard.created_by.name).to.eq('user-dashboard-administrator');
  });
});

Then('the dashboard is added to the favourites list', () => {
  cy.contains(dashboards.default.name).should('be.visible');
});

Given('a dashboard having another configured web page widget', () => {
  cy.insertDashboard({ ...dashboards.fromDashboardCreatorUser });
  cy.visitDashboards();
});

When('the dashboard administrator clicks on the favourite icon of the first dashboard in the favourites list', () => {
  cy.getByTestId({ testId: 'FavoriteIcon' }).eq(0).click();
  cy.wait('@deleteFavorites');
  cy.contains('Show only dashboards added to favorites').click();

  cy.wait('@getFavorites').then((interception) => {
    expect(interception.response?.statusCode).to.eq(200);

    const responseBody = interception.response?.body;

    expect(responseBody.result).to.be.an('array').that.is.empty;
    expect(responseBody.meta).to.have.property('page', 1);
    expect(responseBody.meta).to.have.property('limit', 10);
    expect(responseBody.meta).to.have.property('total', 0);
  });
});

Then('the dashboard should be removed from the favourites list', () => {
  cy.contains(dashboards.default.name).should('not.exist');
});