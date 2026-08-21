import { Given, Then, When } from '@badeball/cypress-cucumber-preprocessor';
import { INTERCEPTORS } from 'fixtures/shared/constants/interceptors';
import { PAGES } from 'fixtures/shared/constants/pages';

import commands from '../../../fixtures/commands/command-api.json';
import {
  assertLatestChangelogRow,
  openChangelogListing,
  openObjectTimeline
} from '../common';

beforeEach(() => {
  cy.startContainers();
  cy.intercept({
    method: 'GET',
    url: INTERCEPTORS.api.navigation_list
  }).as('getNavigationList');
  cy.intercept({
    method: 'GET',
    url: INTERCEPTORS.pages.time_zone
  }).as('getTimeZone');
  cy.intercept({
    method: 'GET',
    url: `${INTERCEPTORS.api.commands_configuration}?page=*`
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
    openChangelogListing();
    assertLatestChangelogRow('service_ok', 'Added', 'Command');
  }
);

// Values command.command_type takes in the changelog, per command kind.
const commandTypeCode: Record<string, string> = {
  CHECK: '2',
  DISCOVERY: '4',
  MISCELLANEOUS: '3',
  NOTIFICATION: '1'
};

Then(
  'the informations of the log are the same as those of the {string} command',
  (type: string) => {
    const command = commands[type.toLowerCase()];
    expect(command, `fixture for command type "${type}"`).to.exist;

    openObjectTimeline(command.name);
    cy.expandTimelineCard('Added');

    cy.checkLogDetail('command_activate', '', command.is_activated ? '1' : '0');
    cy.checkLogDetail('command_name', '', command.name);
    cy.checkLogDetail('command_type', '', commandTypeCode[type]);
    cy.checkLogDetail('command_line', '', `${command.command_line}`);
    cy.checkLogDetail('enable_shell', '', command.is_shell_enabled ? '1' : '0');
  }
);

afterEach(() => {
  cy.stopContainers();
});
