import { Method, SnackbarProvider, TestQueryProvider } from '@centreon/ui';
import i18next from 'i18next';
import { Provider, createStore } from 'jotai';
import { initReactI18next } from 'react-i18next';
import { BrowserRouter as Router } from 'react-router';
import AgentConfigurationPage from '../Page';
import {
  agentConfigurationPollersEndpoint,
  getAgentConfigurationEndpoint,
  getAgentConfigurationsEndpoint,
  getPollerAgentEndpoint,
  hostsConfigurationEndpoint,
  listTokensEndpoint,
  pollersEndpoint
} from '../api/endpoints';

const mockRequest = (isListingEmpty): void => {
  if (isListingEmpty) {
    cy.interceptAPIRequest({
      alias: 'getEmptyAgentConfigurations',
      method: Method.GET,
      path: `./api/latest${getAgentConfigurationsEndpoint}**`,
      response: { result: [], meta: { limit: 10, page: 1, total: 0 } }
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
      id: 1,
      name: 'agent',
      connection_mode: 'secure',
      type: 'telegraf',
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
      configuration: {
        tokens: [
          { name: 'token 1', creator_id: 1 },
          { name: 'token 2', creator_id: 2 }
        ],
        otel_server_address: '127.0.0.1',
        otel_server_port: 8080,
        otel_public_certificate: 'test.cer',
        otel_ca_certificate: 'test.crt',
        otel_private_key: 'test.key',
        conf_server_port: 9090,
        conf_certificate: '/sub/test.crt',
        conf_private_key: 'test.crt'
      }
    }
  });
  cy.interceptAPIRequest({
    alias: 'getHosts',
    path: `./api/latest${hostsConfigurationEndpoint}**`,
    method: Method.GET,
    response: {
      result: [{ id: 1, name: 'central', address: '127.0.0.2' }],
      meta: { limit: 10, page: 1, total: 1 }
    }
  });

  cy.interceptAPIRequest({
    alias: 'getTokens',
    path: `*${listTokensEndpoint}**`,
    method: Method.GET,
    response: {
      result: [
        { creator: { id: 1, name: 'Admin' }, name: 'token 1' },
        { creator: { id: 1, name: 'Admin' }, name: 'token 2' }
      ],
      meta: { limit: 10, page: 1, total: 2 }
    }
  });
};

const initialize = ({ isListingEmpty = false }) => {
  const store = createStore();

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
              <div style={{ height: '100vh', display: 'grid' }}>
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
