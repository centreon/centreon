import { Then, When } from '@badeball/cypress-cucumber-preprocessor';

const service = 'Ping';
const host = 'Centreon-Server';

before(() => {
  cy.startWebContainer();
});

beforeEach(() => {
  cy.intercept({
    method: 'GET',
    url: '/monitor/api/internal.php?object=centreon_topology&action=navigationList'
  }).as('getNavigationList');
  cy.intercept({
    method: 'GET',
    url: '/monitor/include/common/userTimezone.php'
  }).as('getTimeZone');
  cy.intercept({
    method: 'GET',
    url: '/monitor/api/latest/monitoring/resources/hosts/*/services/*'
  }).as('getResourceDetails');
});

When(
  'I update the base uri within the corresponding web server configuration file',
  () => {
    cy.execInContainer({
      command:
        'bash -c "sed -i \'0,/centreon/s//monitor/\' /etc/httpd/conf.d/10-centreon.conf"',
      name: Cypress.env('dockerName')
    });
  }
);

When('I reload the web server', () => {
  cy.execInContainer({
    command: 'systemctl restart httpd',
    name: Cypress.env('dockerName')
  });
});

Then('I can authenticate to the centreon platform', () => {
  cy.loginByTypeOfUser({ jsonName: 'admin' });

  cy.url().should('contain', '/monitor');
});

Then(
  'the resource icons are displayed in configuration and monitoring pages',
  () => {
    cy.getByLabel({ label: 'State filter' }).click();

    cy.get('[data-value="all"]').click();

    cy.contains(service).should('exist');

    cy.contains(host).should('exist');

    cy.navigateTo({
      page: 'Hosts',
      rootItemNumber: 3,
      subMenu: 'Hosts'
    });

    cy.wait('@getTimeZone').then(() => {
      cy.getIframeBody().contains(host).should('exist');
    });

    cy.navigateTo({
      page: 'Services by host',
      rootItemNumber: 3,
      subMenu: 'Services'
    });

    cy.wait('@getTimeZone').then(() => {
      cy.getIframeBody().contains(service).should('exist');
    });
  }
);

Then(
  'the detailed information of the monitoring resources are displayed',
  () => {
    cy.navigateTo({
      page: 'Resources Status',
      rootItemNumber: 1
    });

    cy.get('header').parent().children().eq(1).contains('OK').should('exist');

    cy.get('header').parent().children().eq(1).contains('Up').should('exist');

    cy.contains(host).click();

    cy.wait('@getResourceDetails');

    cy.get('#panel-content').should('be.visible');

    cy.get('#panel-content').contains('OK - 127.0.0.1');
  }
);

after(() => {
  cy.stopWebContainer();
});
