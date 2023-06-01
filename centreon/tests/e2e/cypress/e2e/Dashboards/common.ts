/* eslint-disable @typescript-eslint/no-namespace */

interface Dashboard {
  description: string;
  name: string;
}

const insertDashboardList = (fixtureUrl: string): Cypress.Chainable => {
  return cy.fixture(fixtureUrl).then((dashboardList) => {
    dashboardList.forEach((dashboard) => {
      insertDashboard(dashboard);
    });
  });
};

const insertDashboard = (dashboardBody: Dashboard): Cypress.Chainable => {
  return cy.request({
    body: {
      ...dashboardBody
    },
    method: 'POST',
    url: '/centreon/api/latest/configuration/dashboards'
  });
};

export { insertDashboardList };
