import { Method, SnackbarProvider, TestQueryProvider } from '@centreon/ui';
import { platformFeaturesAtom, userAtom } from '@centreon/ui-context';

import i18next from 'i18next';
import { createStore, Provider } from 'jotai';
import { initReactI18next } from 'react-i18next';
import { BrowserRouter as Router } from 'react-router';

import {
  agentConfigurationPollersEndpoint,
  getAgentConfigurationEndpoint,
  getAgentConfigurationsEndpoint,
  getPollerAgentEndpoint,
  hostsConfigurationEndpoint,
  listTokensEndpoint,
  pollersEndpoint
} from '../api/endpoints';
import AgentConfigurationPage from '../Page';

const mockRequest = (isListingEmpty): void => {
  if (isListingEmpty) {
    cy.interceptAPIRequest({
      alias: 'getEmptyAgentConfigurations',
      method: Method.GET,
      path: `./api/latest${getAgentConfigurationsEndpoint}**`,
      response: { meta: { limit: 10, page: 1, total: 0 }, result: [] }
    });
  } else {
    cy.fixture('AgentConfigurations/listing.json').then((listing): void => {
      cy.interceptAPIRequest({
        alias: 'getAgentConfigurations',
        method: Method.GET,
        path: `./api/latest${getAgentConfigurationsEndpoint}?page=1**`,
        response: listing
      });
    });
  }

  cy.fixture('ACC/pollers-vmware.json').then((listing): void => {
    cy.interceptAPIRequest({
      alias: 'getFilterPollers',
      method: Method.GET,
      path: `./api/latest${pollersEndpoint}**`,
      response: listing
    });
  });

  cy.fixture('ACC/pollers-vmware.json').then((listing): void => {
    cy.interceptAPIRequest({
      alias: 'getPollers',
      method: Method.GET,
      path: `./api/latest${agentConfigurationPollersEndpoint}**`,
      response: listing
    });
  });

  cy.interceptAPIRequest({
    alias: 'deleteAgent',
    method: Method.DELETE,
    path: `./api/latest${getPollerAgentEndpoint({ agentId: 0 })}`
  });

  cy.interceptAPIRequest({
    alias: 'deletePoller',
    method: Method.DELETE,
    path: `./api/latest${getPollerAgentEndpoint({ agentId: 0, pollerId: 1 })}`
  });

  cy.interceptAPIRequest({
    alias: 'postAgentConfiguration',
    method: Method.POST,
    path: `./api/latest${getAgentConfigurationsEndpoint}`
  });

  cy.interceptAPIRequest({
    alias: 'patchAgentConfiguration',
    method: Method.PUT,
    path: `./api/latest${getAgentConfigurationEndpoint(1)}`
  });

  cy.interceptAPIRequest({
    alias: 'getAgentConfiguration',
    method: Method.GET,
    path: `./api/latest${getAgentConfigurationEndpoint(1)}`,
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
        {
          id: 1,
          name: 'poller 1'
        },
        {
          id: 2,
          name: 'poller 2'
        }
      ],
      type: 'telegraf'
    }
  });
  cy.interceptAPIRequest({
    alias: 'getHosts',
    method: Method.GET,
    path: `./api/latest${hostsConfigurationEndpoint}**`,
    response: {
      meta: { limit: 10, page: 1, total: 1 },
      result: [{ address: '127.0.0.2', id: 1, name: 'central' }]
    }
  });

  cy.interceptAPIRequest({
    alias: 'getTokens',
    method: Method.GET,
    path: `*${listTokensEndpoint}**`,
    response: {
      meta: { limit: 10, page: 1, total: 2 },
      result: [
        { creator: { id: 1, name: 'Admin' }, name: 'token 1' },
        { creator: { id: 1, name: 'Admin' }, name: 'token 2' }
      ]
    }
  });
};

const initialize = ({ isListingEmpty = false }) => {
  const store = createStore();

  store.set(userAtom, {
    is_admin: true,
    locale: 'en',
    timezone: 'Europe/Paris'
  });
  store.set(platformFeaturesAtom, {
    featureFlags: {},
    isCloudPlatform: false
  });

  i18next.use(initReactI18next).init({
    lng: 'en',
    resources: {}
  });

  mockRequest(isListingEmpty);

  cy.mount({
    Component: (
      <TestQueryProvider>
        <Provider store={store}>
          <Router>
            <SnackbarProvider>
              <div style={{ display: 'grid', height: '100vh' }}>
                <AgentConfigurationPage />
              </div>
            </SnackbarProvider>
          </Router>
        </Provider>
      </TestQueryProvider>
    )
  });
};

export default initialize;
