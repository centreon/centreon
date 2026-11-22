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

import AiProviders from '..';
import {
  aiProvidersEndpoint,
  discoverModelsEndpoint,
  getAiProviderEndpoint,
  modelPricesEndpoint,
  testConnectionEndpoint
} from '../api';
import {
  connectionTestResponse,
  discoveredModelsResponse,
  getAiProviderResponse,
  getListingResponse,
  getProvidersCountResponse,
  priceListUnavailableResponse
} from './utils';

interface Props {
  // Whether the user may edit AI providers.
  hasWriteAccess?: boolean;
  // The details provider 1 answers, when not the default one.
  aiProvider?: Record<string, unknown>;
  // The number of providers the add form defaults the Enabled switch from.
  providersTotal?: number;
}

const initialize = ({
  hasWriteAccess = true,
  aiProvider = getAiProviderResponse(),
  providersTotal = 2
}: Props = {}): RenderResult => {
  i18next.use(initReactI18next).init({
    lng: 'en',
    resources: {}
  });

  // The form is URL-driven, and the page is not reloaded between tests.
  window.history.pushState({}, '', window.location.pathname);

  const store = createStore();

  // Only what the module reads.
  store.set(userPermissionsAtom, {
    administration_ai_provider_write: hasWriteAccess
  } as UserPermissions);
  // The listing waits for the platform page size before loading.
  store.set(defaultLimitPerPageAtom, 10);

  // Also answers the `name[lk]=` lookup of the name validation: MSW ignores
  // the query string, so this one mock serves every listing request but the
  // provider count below.
  interceptAPIRequest({
    alias: 'getAiProviders',
    method: Method.GET,
    path: `**${aiProvidersEndpoint}?**`,
    response: getListingResponse()
  });

  // The `limit=1` request the add form reads the provider total from.
  // Registered last, so it is matched first.
  interceptAPIRequest({
    alias: 'getAiProvidersCount',
    method: Method.GET,
    path: `**${aiProvidersEndpoint}?**`,
    query: { name: 'limit', value: '1' },
    response: getProvidersCountResponse(providersTotal)
  });

  interceptAPIRequest({
    alias: 'testConnection',
    method: Method.POST,
    path: `**${testConnectionEndpoint}`,
    response: connectionTestResponse
  });

  interceptAPIRequest({
    alias: 'fetchPrices',
    method: Method.POST,
    path: `**${modelPricesEndpoint}`,
    response: priceListUnavailableResponse,
    statusCode: 502
  });

  interceptAPIRequest({
    alias: 'discoverModels',
    method: Method.POST,
    path: `**${discoverModelsEndpoint}`,
    response: discoveredModelsResponse
  });

  interceptAPIRequest({
    alias: 'getAiProvider',
    method: Method.GET,
    path: `**${getAiProviderEndpoint({ id: 1 })}`,
    response: aiProvider
  });

  interceptAPIRequest({
    alias: 'createAiProvider',
    method: Method.POST,
    path: `**${aiProvidersEndpoint}`,
    response: aiProvider
  });

  interceptAPIRequest({
    alias: 'updateAiProvider',
    method: Method.PATCH,
    path: `**${getAiProviderEndpoint({ id: 1 })}`,
    response: aiProvider
  });

  interceptAPIRequest({
    alias: 'enableAiProvider',
    method: Method.PATCH,
    path: `**${getAiProviderEndpoint({ id: 2 })}`,
    response: aiProvider
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
              <AiProviders />
            </div>
          </Provider>
        </QueryClientProvider>
      </SnackbarProvider>
    </Router>
  );
};

export default initialize;
