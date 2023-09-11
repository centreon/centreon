import { Then, When } from '@badeball/cypress-cucumber-preprocessor';

const service = 'Ping';
const host = 'Centreon-Server';

before(() => {
  cy.startWebContainer();
});

beforeEach(() => {
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/internal.php?object=centreon_topology&action=navigationList'
  }).as('getNavigationList');
  cy.intercept({
    method: 'GET',
    url: '/centreon/include/common/userTimezone.php'
  }).as('getTimeZone');
});

When(
  'I update the base uri within the corresponding web server configuration file',
  () => {
    cy.execInContainer({
      command:
        'sed -i \'s/"/centreon"/"/monitor"/\' /etc/httpd/conf.d/10-centreon.conf',
      name: 'centreon-dev'
    });
  }
);

When('I reload the web server', () => {
  cy.execInContainer({
    command: 'pkill httpd && sh /usr/share/centreon/container.d/60-apache.sh',
    name: 'centreon-dev'
  });
});

Then('I can authenticate to the centreon platform', () => {
  cy.loginByTypeOfUser({ jsonName: 'admin' });
});

Then(
  'the resource icons are displayed in configuration and monitoring pages',
  () => {
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

    cy.get('header').parent().children().eq(1).contains('Ok').should('exist');
    cy.get('header').parent().children().eq(1).contains('Up').should('exist');
  }
);

after(() => {
  cy.stopWebContainer().stopOpenIdProviderContainer();
});
