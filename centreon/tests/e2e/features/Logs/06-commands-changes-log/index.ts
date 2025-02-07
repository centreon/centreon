/* eslint-disable prettier/prettier */
/* eslint-disable cypress/unsafe-to-chain-command */
import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';

import commands from '../../../fixtures/commands/command.json';

beforeEach(() => {
  cy.startContainers();
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/internal.php?object=centreon_topology&action=navigationList'
  }).as('getNavigationList');
  cy.intercept({
    method: 'GET',
    url: '/centreon/include/common/userTimezone.php'
  }).as('getTimeZone');
});

Given('a user is logged in a Centreon server via APIv2', () => {
  cy.loginAsAdminViaApiV2();
  cy.visit('/').url().should('include', '/monitoring/resources');
});

When('a call to the endpoint "Add" a {string} command is done via APIv2', (type: string) => {
  const commandType = commands[type.toLowerCase()];
  if (commandType) {
   cy.addSubjectViaAPIv2(commandType, '/centreon/api/latest/configuration/commands');
  }
});

Then('a new {string} command is displayed on the {string} commands page', (type: string) => {
  switch(type) { 
     case "NOTIFICATION": { 
        cy.navigateTo({
            page: 'Notifications',
            rootItemNumber: 3,
            subMenu: 'Commands'
        });
        cy.wait('@getTimeZone');
        cy.waitForElementInIframe(
        '#main-content',
        `a:contains("${commands.notification.name}")`
        );
        cy.getIframeBody()
        .contains('a', commands.notification.name)
        .should('be.visible');
            break; 
      } 
      case "CHECK": { 
        cy.navigateTo({
            page: 'Checks',
            rootItemNumber: 3,
            subMenu: 'Commands'
        });
        cy.wait('@getTimeZone');
        cy.waitForElementInIframe(
        '#main-content',
        `a:contains("${commands.check.name}")`
        );
        cy.getIframeBody()
        .contains('a', commands.check.name)
        .should('be.visible');
        break;
      } 
      case "MISCELLANEOUS": { 
        cy.navigateTo({
            page: 'Miscellaneous',
            rootItemNumber: 3,
            subMenu: 'Commands'
        });
        cy.wait('@getTimeZone');
        cy.waitForElementInIframe(
        '#main-content',
        `a:contains("${commands.miscellaneous.name}")`
        );
        cy.getIframeBody()
        .contains('a', commands.miscellaneous.name)
        .should('be.visible');
        break;
      }
      case "DISCOVERY": { 
        cy.navigateTo({
            page: 'Discovery',
            rootItemNumber: 3,
            subMenu: 'Commands'
        });
        cy.wait('@getTimeZone');
        cy.waitForElementInIframe(
        '#main-content',
        `a:contains("${commands.discovery.name}")`
        );
        cy.getIframeBody()
        .contains('a', commands.discovery.name)
        .should('be.visible');
        break;
      }
      default: 
        break; 
     }
  }
);

Then('a new "Added" ligne of log is getting added to the page Administration > Logs', () => {
    cy.navigateTo({
      page: 'Logs',
      rootItemNumber: 4
    });
    cy.wait('@getTimeZone');
    cy.waitForElementInIframe(
      '#main-content',
      'span[class*="badge service_ok"]'
    );
    cy.getIframeBody()
      .contains('span.badge.service_ok', 'Added')
      .should('exist');

    cy.getIframeBody()
      .find('tr.list_one')
      .find('td')
      .eq(2)
      .should('contain.text', 'command');
  }
);

Then(
  'the informations of the log are the same as those of the {string} command',
  (type: string) => {
    switch(type) { 
     case "NOTIFICATION": { 
        cy.getIframeBody().contains(commands.notification.name).click();
        cy.waitForElementInIframe(
        '#main-content',
        'a[href="./main.php?p=508"].btc.bt_success'
        );
        cy.getIframeBody()
        .find('td.ListColHeaderCenter')
        .eq(0)
        .should('contain.text', commands.notification.name);
        cy.getIframeBody().contains('td', 'Create by admin').should('exist');
        cy.checkLogDetails(1, 0, 'Field Name', 'Before', 'After');
        cy.checkLogDetails(1, 1, 'command_name', '', commands.notification.name);
        cy.checkLogDetails(1, 2, 'command_line', '', commands.notification.command_line);
        cy.checkLogDetails(1, 3, 'enable_shell', '', '0');
        cy.checkLogDetails(1, 4, 'command_type', '', `${commands.notification.type}`);
        cy.checkLogDetails(1, 5, 'argument_example', '', `${commands.notification.argument_example}`);
        cy.checkLogDetails(1, 6, 'connectors', '', `${commands.notification.connector_id}`);
        cy.checkLogDetails(1, 7, 'graph_id', '', `${commands.notification.graph_template_id}`);
        break; 
      } 
      case "CHECK": { 
        cy.getIframeBody().contains(commands.check.name).click();
        cy.waitForElementInIframe(
        '#main-content',
        'a[href="./main.php?p=508"].btc.bt_success'
        );
        cy.getIframeBody()
        .find('td.ListColHeaderCenter')
        .eq(0)
        .should('contain.text', commands.check.name);
        cy.getIframeBody().contains('td', 'Create by admin').should('exist');
        cy.checkLogDetails(1, 0, 'Field Name', 'Before', 'After');
        cy.checkLogDetails(1, 1, 'command_name', '', commands.check.name);
        cy.checkLogDetails(1, 2, 'command_line', '', commands.check.command_line);
        cy.checkLogDetails(1, 3, 'enable_shell', '', '0');
        cy.checkLogDetails(1, 4, 'command_type', '', `${commands.check.type}`);
        cy.checkLogDetails(1, 5, 'argument_example', '', `${commands.check.argument_example}`);
        cy.checkLogDetails(1, 6, 'connectors', '', `${commands.check.connector_id}`);
        cy.checkLogDetails(1, 7, 'graph_id', '', `${commands.check.graph_template_id}`);
        break;
      } 
      case "MISCELLANEOUS": { 
        cy.getIframeBody().contains(commands.miscellaneous.name).click();
        cy.waitForElementInIframe(
        '#main-content',
        'a[href="./main.php?p=508"].btc.bt_success'
        );
        cy.getIframeBody()
        .find('td.ListColHeaderCenter')
        .eq(0)
        .should('contain.text', commands.miscellaneous.name);
        cy.getIframeBody().contains('td', 'Create by admin').should('exist');
        cy.checkLogDetails(1, 0, 'Field Name', 'Before', 'After');
        cy.checkLogDetails(1, 1, 'command_name', '', commands.miscellaneous.name);
        cy.checkLogDetails(1, 2, 'command_line', '', commands.miscellaneous.command_line);
        cy.checkLogDetails(1, 3, 'enable_shell', '', '0');
        cy.checkLogDetails(1, 4, 'command_type', '', `${commands.miscellaneous.type}`);
        cy.checkLogDetails(1, 5, 'argument_example', '', `${commands.miscellaneous.argument_example}`);
        cy.checkLogDetails(1, 6, 'connectors', '', `${commands.miscellaneous.connector_id}`);
        cy.checkLogDetails(1, 7, 'graph_id', '', `${commands.miscellaneous.graph_template_id}`);
        break;
      }
      case "DISCOVERY": { 
        cy.getIframeBody().contains(commands.discovery.name).click();
        cy.waitForElementInIframe(
        '#main-content',
        'a[href="./main.php?p=508"].btc.bt_success'
        );
        cy.getIframeBody()
        .find('td.ListColHeaderCenter')
        .eq(0)
        .should('contain.text', commands.discovery.name);
        cy.getIframeBody().contains('td', 'Create by admin').should('exist');
        cy.checkLogDetails(1, 0, 'Field Name', 'Before', 'After');
        cy.checkLogDetails(1, 1, 'command_name', '', commands.discovery.name);
        cy.checkLogDetails(1, 2, 'command_line', '', commands.discovery.command_line);
        cy.checkLogDetails(1, 3, 'enable_shell', '', '0');
        cy.checkLogDetails(1, 4, 'command_type', '', `${commands.discovery.type}`);
        cy.checkLogDetails(1, 5, 'argument_example', '', `${commands.discovery.argument_example}`);
        cy.checkLogDetails(1, 6, 'connectors', '', `${commands.discovery.connector_id}`);
        cy.checkLogDetails(1, 7, 'graph_id', '', `${commands.discovery.graph_template_id}`);
        break;
      }
      default: 
        break; 
     }
  }
);

afterEach(() => {
    cy.stopContainers();
});