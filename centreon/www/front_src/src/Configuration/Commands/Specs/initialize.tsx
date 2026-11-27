import { SnackbarProvider } from '@centreon/ui';
import {
  defaultLimitPerPageAtom,
  type UserPermissions,
  userPermissionsAtom
} from '@centreon/ui-context';

import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { interceptAPIRequest, Method } from '@test-setup/api';
import { render } from '@test-setup/render';
import type { RenderResult } from '@testing-library/react';
import i18next from 'i18next';
import { createStore, Provider } from 'jotai';
import { initReactI18next } from 'react-i18next';
import { BrowserRouter as Router } from 'react-router';

import Commands from '..';
import { commandsEndpoint, getCommandEndpoint } from '../api';
import {
  connectorsEndpoint,
  globalMacrosEndpoint,
  pluginsEndpoint,
  standardMacrosEndpoint
} from '../api/endpoints';
import {
  getCommandResponse,
  getConnectorsResponse,
  getGlobalMacrosResponse,
  getInstalledPluginsResponse,
  getListingResponse,
  getStandardMacrosResponse
} from './utils';

const initialize = (): RenderResult => {
  i18next.use(initReactI18next).init({
    lng: 'en',
    resources: {}
  });

  // The page is not reloaded between tests.
  window.history.pushState({}, '', window.location.pathname);

  const store = createStore();

  // The platform default page size, which listings wait for.
  store.set(defaultLimitPerPageAtom, 10);

  // Only what the module reads.
  store.set(userPermissionsAtom, {
    manage_check_commands: true,
    manage_discovery_commands: true,
    manage_miscellaneous_commands: true,
    manage_notification_commands: true
  } as UserPermissions);

  interceptAPIRequest({
    alias: 'getCommands',
    method: Method.GET,
    path: `**${commandsEndpoint}?**`,
    response: getListingResponse()
  });

  interceptAPIRequest({
    alias: 'getCommand',
    method: Method.GET,
    path: `**${getCommandEndpoint({ id: 1 })}`,
    response: getCommandResponse()
  });

  interceptAPIRequest({
    alias: 'createCommand',
    method: Method.POST,
    path: `**${commandsEndpoint}`,
    response: getCommandResponse()
  });

  interceptAPIRequest({
    alias: 'updateCommand',
    method: Method.PATCH,
    path: `**${getCommandEndpoint({ id: 1 })}`,
    response: {}
  });

  interceptAPIRequest({
    alias: 'listGlobalMacros',
    method: Method.GET,
    path: `**${globalMacrosEndpoint}?**`,
    response: getGlobalMacrosResponse()
  });

  interceptAPIRequest({
    alias: 'listStandardMacros',
    method: Method.GET,
    path: `**${standardMacrosEndpoint}?**`,
    response: getStandardMacrosResponse()
  });

  interceptAPIRequest({
    alias: 'listInstalledPlugins',
    method: Method.GET,
    path: `**${pluginsEndpoint}?**`,
    response: getInstalledPluginsResponse()
  });

  interceptAPIRequest({
    alias: 'getConnectors',
    method: Method.GET,
    path: `**${connectorsEndpoint}?**`,
    response: getConnectorsResponse()
  });

  // A client of its own, so no cache leaks from one test to the next.
  const queryClient = new QueryClient({
    defaultOptions: { queries: { gcTime: 0, retry: false } }
  });

  return render(
    <Router>
      <SnackbarProvider>
        <QueryClientProvider client={queryClient}>
          <Provider store={store}>
            <div style={{ height: '100vh' }}>
              <Commands />
            </div>
          </Provider>
        </QueryClientProvider>
      </SnackbarProvider>
    </Router>
  );
};

export default initialize;
