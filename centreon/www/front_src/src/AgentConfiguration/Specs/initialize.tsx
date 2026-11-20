import { SnackbarProvider } from '@centreon/ui';
import {
  type PlatformFeatures,
  platformFeaturesAtom,
  type User,
  userAtom
} from '@centreon/ui-context';

import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { interceptAPIRequest, Method } from '@test-setup/api';
import { render } from '@test-setup/render';
import type { RenderResult } from '@testing-library/react';
import i18next from 'i18next';
import { createStore, Provider } from 'jotai';
import { initReactI18next } from 'react-i18next';
import { BrowserRouter as Router } from 'react-router';

import pollers from '../../../../../cypress/fixtures/ACC/pollers-vmware.json';
import {
  agentConfigurationPollersEndpoint,
  getAgentConfigurationEndpoint,
  getAgentConfigurationsEndpoint,
  getInstallationCommandEndpoint,
  hostsConfigurationEndpoint,
  listTokensEndpoint,
  pollersEndpoint
} from '../api/endpoints';
import AgentConfigurationPage from '../Page';
import installationCommand from './installation-command.json';
import listing from './listing.json';

export const windowsCommandLine =
  'installcma.ps /FINGERPRINT=wwwww  /COMPONENTS=agent,plugins /HOST=host_1 /ENDPOINT=https://central/centreon:4318';
export const linuxCommandLine =
  'installcma.ps /FINGERPRINT=lllllll  /COMPONENTS=agent,plugins /HOST=host_1 /ENDPOINT=https://central/centreon:4317';

interface Props {
  isListingEmpty?: boolean;
}

const mockRequests = (isListingEmpty: boolean): void => {
  interceptAPIRequest({
    alias: isListingEmpty
      ? 'getEmptyAgentConfigurations'
      : 'getAgentConfigurations',
    method: Method.GET,
    path: `**/api/latest${getAgentConfigurationsEndpoint}`,
    response: isListingEmpty
      ? { meta: { limit: 10, page: 1, total: 0 }, result: [] }
      : listing
  });

  interceptAPIRequest({
    alias: 'getFilterPollers',
    method: Method.GET,
    path: `**/api/latest${pollersEndpoint}`,
    response: pollers
  });

  interceptAPIRequest({
    alias: 'getPollers',
    method: Method.GET,
    path: `**/api/latest${agentConfigurationPollersEndpoint}`,
    response: pollers
  });

  interceptAPIRequest({
    alias: 'deleteAgent',
    method: Method.DELETE,
    path: `**/api/latest${getAgentConfigurationsEndpoint}/0`
  });

  interceptAPIRequest({
    alias: 'deletePoller',
    method: Method.DELETE,
    path: `**/api/latest${getAgentConfigurationsEndpoint}/0/pollers/1`
  });

  interceptAPIRequest({
    alias: 'postAgentConfiguration',
    method: Method.POST,
    path: `**/api/latest${getAgentConfigurationsEndpoint}`
  });

  interceptAPIRequest({
    alias: 'patchAgentConfiguration',
    method: Method.PUT,
    path: `**/api/latest${getAgentConfigurationEndpoint(1)}`
  });

  interceptAPIRequest({
    alias: 'getAgentConfiguration',
    method: Method.GET,
    path: `**/api/latest${getAgentConfigurationEndpoint(1)}`,
    response: {
      configuration: {
        conf_certificate: '/sub/test.crt',
        conf_private_key: 'test.key',
        conf_server_port: 9090,
        otel_ca_certificate: 'test.crt',
        otel_private_key: 'test.key',
        otel_public_certificate: 'test.cer',
        otel_server_address: '127.0.0.1',
        otel_server_port: 8080,
        tokens: [
          { creator_id: 1, name: 'token 1' },
          { creator_id: 2, name: 'token 2' }
        ]
      },
      connection_mode: 'secure',
      id: 1,
      name: 'agent',
      pollers: [
        { id: 1, name: 'poller 1' },
        { id: 2, name: 'poller 2' }
      ],
      type: 'telegraf'
    }
  });

  interceptAPIRequest({
    alias: 'getHosts',
    method: Method.GET,
    path: `**/api/latest${hostsConfigurationEndpoint}`,
    response: {
      meta: { limit: 10, page: 1, total: 1 },
      result: [{ address: '127.0.0.2', id: 1, name: 'central' }]
    }
  });

  interceptAPIRequest({
    alias: 'getTokens',
    method: Method.GET,
    path: `**${listTokensEndpoint}`,
    response: {
      meta: { limit: 10, page: 1, total: 2 },
      result: [
        { creator: { id: 1, name: 'Admin' }, name: 'token 1' },
        { creator: { id: 1, name: 'Admin' }, name: 'token 2' }
      ]
    }
  });

  interceptAPIRequest({
    alias: 'getCommandDetails',
    method: Method.GET,
    path: `**/api/latest${getInstallationCommandEndpoint(1)}`,
    response: installationCommand
  });
};

const initialize = ({ isListingEmpty = false }: Props = {}): RenderResult => {
  const store = createStore();

  store.set(userAtom, {
    is_admin: true,
    locale: 'en',
    timezone: 'Europe/Paris'
  } as unknown as User);
  store.set(platformFeaturesAtom, {
    featureFlags: {},
    isCloudPlatform: false
  } as PlatformFeatures);

  i18next.use(initReactI18next).init({
    lng: 'en',
    resources: {}
  });

  mockRequests(isListingEmpty);

  // A client of its own, so no cache leaks from one test to the next.
  const queryClient = new QueryClient({
    defaultOptions: { queries: { gcTime: 0, retry: false } }
  });

  return render(
    <QueryClientProvider client={queryClient}>
      <Provider store={store}>
        <Router>
          <SnackbarProvider>
            <div style={{ display: 'grid', height: '100vh' }}>
              <AgentConfigurationPage />
            </div>
          </SnackbarProvider>
        </Router>
      </Provider>
    </QueryClientProvider>
  );
};

export default initialize;
