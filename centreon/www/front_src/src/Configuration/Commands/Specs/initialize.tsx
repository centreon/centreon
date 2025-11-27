import i18next from 'i18next';
import { initReactI18next } from 'react-i18next';

import { Provider, createStore } from 'jotai';
import { BrowserRouter as Router } from 'react-router';

import { Method, SnackbarProvider, TestQueryProvider } from '@centreon/ui';
import { userPermissionsAtom } from '@centreon/ui-context';

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

  const store = createStore();

  store.set(userPermissionsAtom, {
    manage_check_commands: true,
    manage_notification_commands: true,
    manage_discovery_commands: true,
    manage_miscellaneous_commands: true
  });

  cy.fixture('Commands/listCommands.json').then((commands) => {
    cy.interceptAPIRequest({
      alias: 'getCommands',
      method: Method.GET,
      path: `**${commandsEndpoint}?**`,
      response: commands
    });
  });

  cy.fixture('Commands/commandDetails.json').then((command) => {
    cy.interceptAPIRequest({
      alias: 'getCommand',
      method: Method.GET,
      path: `**${getCommandEndpoint({ id: 1 })}`,
      response: command
    });

    cy.interceptAPIRequest({
      alias: 'createCommand',
      method: Method.POST,
      path: `**${commandsEndpoint}**`,
      response: command
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
      alias: 'listGlobalMacros',
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

  cy.fixture('Commands/listInstalledPlugins.json').then((macro) => {
    cy.interceptAPIRequest({
      alias: 'listInstalledPlugins',
      method: Method.GET,
      path: `**${pluginsEndpoint}?**`,
      response: macro
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
            <Provider store={store}>
              <div style={{ height: '100vh' }}>
                <Commands />
              </div>
            </Provider>
          </TestQueryProvider>
        </SnackbarProvider>
      </Router>
    )
  });
};

export default initialize;
