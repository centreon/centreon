import { SnackbarProvider } from '@centreon/ui';

import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { interceptAPIRequest, Method } from '@test-setup/api';
import { render } from '@test-setup/render';
import type { RenderResult } from '@testing-library/react';
import i18next from 'i18next';
import { createStore, Provider } from 'jotai';
import { initReactI18next } from 'react-i18next';
import { BrowserRouter as Router } from 'react-router';

import AdditionnalConnectors from '..';
import {
  additionalConnectorsEndpoint,
  getAdditionalConnectorEndpoint
} from '../api';
import { pollersEndpoint } from '../api/endpoints';
import {
  getConnectorResponse,
  getConnectorsResponse,
  getPollersResponse
} from './utils';

const initialize = (): RenderResult => {
  i18next.use(initReactI18next).init({
    lng: 'en',
    resources: {}
  });

  // The modal is URL-driven, and the page is not reloaded between tests.
  window.history.pushState({}, '', window.location.pathname);

  interceptAPIRequest({
    alias: 'getConnectors',
    method: Method.GET,
    path: `**${additionalConnectorsEndpoint}?**`,
    response: getConnectorsResponse()
  });

  interceptAPIRequest({
    alias: 'getConnector',
    method: Method.GET,
    path: `**${getAdditionalConnectorEndpoint({ id: 1 })}`,
    response: getConnectorResponse()
  });

  interceptAPIRequest({
    alias: 'createConnector',
    method: Method.POST,
    path: `**${additionalConnectorsEndpoint}`,
    response: getConnectorResponse()
  });

  interceptAPIRequest({
    alias: 'geAllowedPollers',
    method: Method.GET,
    path: `**${pollersEndpoint}?**`,
    response: getPollersResponse()
  });

  interceptAPIRequest({
    alias: 'updateConnector',
    method: Method.PUT,
    path: `**${getAdditionalConnectorEndpoint({ id: 1 })}`,
    response: {}
  });

  // A client and a store of their own, so nothing leaks from one test to the
  // next.
  const queryClient = new QueryClient({
    defaultOptions: { queries: { gcTime: 0, retry: false } }
  });

  return render(
    <Router>
      <SnackbarProvider>
        <QueryClientProvider client={queryClient}>
          <Provider store={createStore()}>
            <div style={{ height: '100vh' }}>
              <AdditionnalConnectors />
            </div>
          </Provider>
        </QueryClientProvider>
      </SnackbarProvider>
    </Router>
  );
};

export default initialize;
