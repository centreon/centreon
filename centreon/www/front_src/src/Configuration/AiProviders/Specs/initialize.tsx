import { Method, SnackbarProvider, TestQueryProvider } from '@centreon/ui';

import i18next from 'i18next';
import { createStore, Provider } from 'jotai';
import { initReactI18next } from 'react-i18next';
import { BrowserRouter as Router } from 'react-router';

import AiProviders from '..';
import {
  aiProvidersEndpoint,
  discoverModelsEndpoint,
  getAiProviderEndpoint
} from '../api';

const initialize = (): void => {
  i18next.use(initReactI18next).init({
    lng: 'en',
    resources: {}
  });

  const store = createStore();

  cy.fixture('AiProviders/listAiProviders.json').then((aiProviders) => {
    // the add form reads the provider total from a dedicated limit=1 request;
    // registered first (MSW first match wins) and under its own alias so it
    // does not shift the getAiProviders request sequence the tests rely on
    cy.interceptAPIRequest({
      alias: 'getAiProvidersCount',
      method: Method.GET,
      path: `**${aiProvidersEndpoint}?page=1&limit=1**`,
      response: aiProviders
    });

    cy.interceptAPIRequest({
      alias: 'getAiProviders',
      method: Method.GET,
      path: `**${aiProvidersEndpoint}?**`,
      response: aiProviders
    });
  });

  // registered before createAiProvider so MSW (first match wins) routes the
  // discover-models POST here rather than to the broad create intercept
  cy.interceptAPIRequest({
    alias: 'discoverModels',
    method: Method.POST,
    path: `**${discoverModelsEndpoint}**`,
    response: {
      models: ['claude-haiku-5-5', 'claude-opus-5-5', 'claude-sonnet-5-5']
    }
  });

  cy.fixture('AiProviders/aiProviderDetails.json').then((aiProvider) => {
    cy.interceptAPIRequest({
      alias: 'getAiProvider',
      method: Method.GET,
      path: `**${getAiProviderEndpoint({ id: 1 })}`,
      response: aiProvider
    });

    cy.interceptAPIRequest({
      alias: 'createAiProvider',
      method: Method.POST,
      path: `**${aiProvidersEndpoint}**`,
      response: aiProvider
    });

    cy.interceptAPIRequest({
      alias: 'updateAiProvider',
      method: Method.PATCH,
      path: `**${getAiProviderEndpoint({ id: 1 })}`,
      response: aiProvider
    });

    cy.interceptAPIRequest({
      alias: 'enableAiProvider',
      method: Method.PATCH,
      path: `**${getAiProviderEndpoint({ id: 2 })}`,
      response: aiProvider
    });
  });

  cy.mount({
    Component: (
      <Router>
        <SnackbarProvider>
          <TestQueryProvider>
            <Provider store={store}>
              <div style={{ height: '100vh' }}>
                <AiProviders />
              </div>
            </Provider>
          </TestQueryProvider>
        </SnackbarProvider>
      </Router>
    )
  });
};

export default initialize;
