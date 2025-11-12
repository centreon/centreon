import i18next from 'i18next';
import { initReactI18next } from 'react-i18next';

import { BrowserRouter as Router } from 'react-router';

import { Method, SnackbarProvider, TestQueryProvider } from '@centreon/ui';

import Commands from '..';

import { commandsEndpoint, getCommandEndpoint } from '../api';
import {
  connectorsEndpoint,
  globalMacrosEndpoint,
  pluginsEndpoint,
  standardMacrosEndpoint
} from '../api/endpoints';

const initialize = (): void => {
  i18next.use(initReactI18next).init({
    lng: 'en',
    resources: {}
  });

  cy.fixture('Commands/listCommands.json').then((connectors) => {
    cy.interceptAPIRequest({
      alias: 'getCommands',
      method: Method.GET,
      path: `**${commandsEndpoint}?**`,
      response: connectors
    });
  });

  cy.fixture('Commands/commandDetails.json').then((connector) => {
    cy.interceptAPIRequest({
      alias: 'getCommand',
      method: Method.GET,
      path: `**${getCommandEndpoint({ id: 1 })}`,
      response: connector
    });

    cy.interceptAPIRequest({
      alias: 'createCommand',
      method: Method.POST,
      path: `**${commandsEndpoint}**`,
      response: connector
    });
  });

  cy.interceptAPIRequest({
    alias: 'updateCommand',
    method: Method.PATCH,
    path: `**${getCommandEndpoint({ id: 1 })}`,
    response: {}
  });

  cy.fixture('Commands/listGlobalMacros.json').then((macro) => {
    cy.interceptAPIRequest({
      alias: 'getCommand',
      method: Method.GET,
      path: `**${globalMacrosEndpoint}?**`,
      response: macro
    });
  });

  cy.fixture('Commands/listStandardMacros.json').then((macro) => {
    cy.interceptAPIRequest({
      alias: 'listStandardMacros',
      method: Method.GET,
      path: `**${standardMacrosEndpoint}?**`,
      response: macro
    });
  });

  cy.fixture('Commands/listInstalledPlugins.json').then((connector) => {
    cy.interceptAPIRequest({
      alias: 'listInstalledPlugins',
      method: Method.GET,
      path: `**${pluginsEndpoint}?**`,
      response: connector
    });
  });

  cy.fixture('Commands/listConnecters.json').then((connector) => {
    cy.interceptAPIRequest({
      alias: 'getCommand',
      method: Method.GET,
      path: `**${connectorsEndpoint}?**`,
      response: connector
    });
  });

  cy.mount({
    Component: (
      <Router>
        <SnackbarProvider>
          <TestQueryProvider>
            <div style={{ height: '100vh' }}>
              <Commands />
            </div>
          </TestQueryProvider>
        </SnackbarProvider>
      </Router>
    )
  });
};

export default initialize;
