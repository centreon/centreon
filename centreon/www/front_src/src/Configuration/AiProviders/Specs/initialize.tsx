import { Method, SnackbarProvider, TestQueryProvider } from '@centreon/ui';

import i18next from 'i18next';
import { createStore, Provider } from 'jotai';
import { initReactI18next } from 'react-i18next';
import { BrowserRouter as Router } from 'react-router';

import AiProviders from '..';
import { aiProvidersEndpoint, getAiProviderEndpoint } from '../api';

const initialize = (): void => {
  i18next.use(initReactI18next).init({
    lng: 'en',
    resources: {}
  });

  const store = createStore();

  cy.fixture('AiProviders/listAiProviders.json').then((aiProviders) => {
    cy.interceptAPIRequest({
      alias: 'getAiProviders',
      method: Method.GET,
      path: `**${aiProvidersEndpoint}?**`,
      response: aiProviders
    });
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
