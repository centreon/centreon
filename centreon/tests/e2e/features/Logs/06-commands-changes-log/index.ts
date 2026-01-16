import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';

import commands from '../../../fixtures/commands/command-api.json';
import { PAGES } from 'fixtures/shared/constants/pages';

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
  cy.intercept({
    method: 'GET',
    url: '/centreon/api/latest/configuration/commands?page=*'
  }).as('getCommandsList');
});

Given('a user is logged in a Centreon server via APIv2', () => {
  cy.loginAsAdminViaApiV2();
  cy.visit('/').url().should('include', '/monitoring/resources');
});

When(
  'a call to the endpoint "Add" a {string} command is done via APIv2',
  (type: string) => {
    const commandType = commands[type.toLowerCase()];
    if (commandType) {
      cy.addSubjectViaApiV2(
        commandType,
        '/centreon/api/latest/configuration/commands'
      );
    }
  }
);

Then(
  'a new {string} command is displayed on the {string} commands page',
  (type: string) => {
    cy.visit(PAGES.configuration.commands);
    cy.wait('@getCommandsList');
    switch (type) {
      case 'NOTIFICATION': {
        // Search for the command
        cy.searchForCommandsByName(commands.notification.name);
        break;
      }
      case 'CHECK': {
        // Search for the command
        cy.searchForCommandsByName(commands.check.name);
        break;
      }
      case 'MISCELLANEOUS': {
        // Search for the command
        cy.searchForCommandsByName(commands.miscellaneous.name);
        break;
      }
      case 'DISCOVERY': {
        // Search for the command
        cy.searchForCommandsByName(commands.discovery.name);
        break;
      }
      default:
        break;
    }
  }
);

Then(
  'a new "Added" ligne of log is getting added to the page Administration > Logs',
  () => {
    cy.visit(PAGES.configuration.logsLegacy);
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
      .should('contain.text', 'commands');
  }
);

Then(
  'the informations of the log are the same as those of the {string} command',
  (type: string) => {
    switch (type) {
      case 'NOTIFICATION': {
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
        cy.checkLogDetails(
          1,
          1,
          'command_activate',
          '',
          commands.notification.is_activated ? '1' : '0'
        );
        cy.checkLogDetails(
          1,
          2,
          'command_name',
          '',
          commands.notification.name
        );
        cy.checkLogDetails(1, 3, 'command_type', '', '1');
        cy.checkLogDetails(
          1,
          4,
          'command_line',
          '',
          `${commands.notification.command_line}`
        );
        cy.checkLogDetails(
          1,
          5,
          'enable_shell',
          '',
          commands.notification.is_shell_enabled ? '1' : '0'
        );
        break;
      }
      case 'CHECK': {
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
        cy.checkLogDetails(
          1,
          1,
          'command_activate',
          '',
          commands.check.is_activated ? '1' : '0'
        );
        cy.checkLogDetails(1, 2, 'command_name', '', commands.check.name);
        cy.checkLogDetails(1, 3, 'command_type', '', '2');
        cy.checkLogDetails(
          1,
          4,
          'command_line',
          '',
          `${commands.check.command_line}`
        );
        cy.checkLogDetails(
          1,
          5,
          'enable_shell',
          '',
          commands.check.is_shell_enabled ? '1' : '0'
        );
        break;
      }
      case 'MISCELLANEOUS': {
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
        cy.checkLogDetails(
          1,
          1,
          'command_activate',
          '',
          commands.miscellaneous.is_activated ? '1' : '0'
        );
        cy.checkLogDetails(
          1,
          2,
          'command_name',
          '',
          commands.miscellaneous.name
        );
        cy.checkLogDetails(1, 3, 'command_type', '', '3');
        cy.checkLogDetails(
          1,
          4,
          'command_line',
          '',
          `${commands.miscellaneous.command_line}`
        );
        cy.checkLogDetails(
          1,
          5,
          'enable_shell',
          '',
          commands.miscellaneous.is_shell_enabled ? '1' : '0'
        );
        break;
      }
      case 'DISCOVERY': {
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
        cy.checkLogDetails(
          1,
          1,
          'command_activate',
          '',
          commands.discovery.is_activated ? '1' : '0'
        );
        cy.checkLogDetails(1, 2, 'command_name', '', commands.discovery.name);
        cy.checkLogDetails(1, 3, 'command_type', '', '4');
        cy.checkLogDetails(
          1,
          4,
          'command_line',
          '',
          `${commands.discovery.command_line}`
        );
        cy.checkLogDetails(
          1,
          5,
          'enable_shell',
          '',
          commands.discovery.is_shell_enabled ? '1' : '0'
        );
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
