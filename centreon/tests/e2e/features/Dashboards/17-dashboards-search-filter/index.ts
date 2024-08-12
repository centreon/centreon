import dashboards from '../../../fixtures/dashboards/navigation/dashboards-single-page.json';
import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';

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
        url: '/centreon/api/latest/configuration/dashboards?page=*'
    }).as('getDashboardsList');

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

Given('a Centreon User with dashboard edition rights on dashboard listing page',
    () => {
        cy.visitDashboards();
    }
);

When('the user sets a right value in the search filter',
    () => {
        cy.getByTestId({ tag: 'input', testId: 'Search' }).type(`${dashboards[0].name}{enter}`);
        cy.wait('@getDashboardsList');
    }
);

Then('the dashboards that respect the filter are displayed',
    () => {
        cy.wait('@getDashboardsList');
        cy.get('[class$="-intersectionRow"]')
            .each(($row) => {
                cy.wrap($row)
                    .find('p:first-of-type')
                    .should('contain.text', dashboards[0].name);
            });
    }
);

Given('Given a Centreon User with dashboard edition rights on dashboard listing page',
    () => {
        cy.visitDashboards();
    }
);

When('the user sets the wrong value in the search filter',
    () => {
        cy.getByTestId({ tag: 'input', testId: 'Search' }).type(`xxx{enter}`);
        cy.wait('@getDashboardsList');
    }
);

Then('no dashboards records are returned',
    () => {
        cy.contains('No result found').should('be.visible');
    }
);