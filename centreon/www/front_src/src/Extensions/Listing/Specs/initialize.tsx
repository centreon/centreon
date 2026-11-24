import { SnackbarProvider } from '@centreon/ui';

import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { interceptAPIRequest, interceptRequest, Method } from '@test-setup/api';
import { render } from '@test-setup/render';
import type { RenderResult } from '@testing-library/react';
import i18next from 'i18next';
import { createStore, Provider } from 'jotai';
import { delay, HttpResponse } from 'msw';
import { initReactI18next } from 'react-i18next';

import ExtensionsListing from '..';
import {
  clearedCacheResponse,
  getListResponse,
  getRefusedResponse,
  getUpdatedResponse
} from './utils';

interface Props {
  // The module whose update answers a body that is not JSON.
  brokenUpdateId?: string;
  // The module whose update answers an HTTP error.
  failingUpdateId?: string;
  // Whether the cache clear answers a failure status.
  isClearCacheRefused?: boolean;
  // Whether the list refresh answers an HTTP error.
  isRefreshFailing?: boolean;
  // The module whose update answers a failure status.
  refusedUpdateId?: string;
  updateDelay?: number;
}

interface InitializeResult extends RenderResult {
  store: ReturnType<typeof createStore>;
}

// Every action the module endpoint received: `<action>[:<id>][:noClearCache]`.
export const sentActions: Array<string> = [];

const legacyModuleEndpoint = '**/api/internal.php';

const initialize = ({
  brokenUpdateId,
  failingUpdateId,
  isClearCacheRefused = false,
  isRefreshFailing = false,
  refusedUpdateId,
  updateDelay = 0
}: Props = {}): InitializeResult => {
  i18next.use(initReactI18next).init({
    lng: 'en',
    resources: {}
  });

  sentActions.length = 0;

  interceptAPIRequest({
    alias: 'getPlatformVersions',
    method: Method.GET,
    path: '**/api/latest/platform/versions',
    response: {
      modules: {},
      web: { fix: '0', major: '26', minor: '10', version: '26.10.0' },
      widgets: {}
    }
  });

  interceptRequest(
    Method.GET,
    legacyModuleEndpoint,
    () => {
      sentActions.push('list');
      const isRefresh =
        sentActions.filter((action) => action === 'list').length > 1;

      return isRefresh && isRefreshFailing
        ? HttpResponse.json({ message: 'Refresh failed' }, { status: 500 })
        : HttpResponse.json(getListResponse());
    },
    'getExtensions'
  );

  // Registered after the list, so it is matched first and leaves it the rest.
  interceptAPIRequest({
    alias: 'getNavigation',
    method: Method.GET,
    path: legacyModuleEndpoint,
    query: { name: 'action', value: 'navigationList' },
    response: { result: [], status: true }
  });

  interceptRequest(
    Method.POST,
    legacyModuleEndpoint,
    async ({ request }) => {
      const { searchParams } = request.url;
      const action = searchParams.get('action');

      if (action === 'clearCache') {
        sentActions.push('clearCache');

        return HttpResponse.json(
          isClearCacheRefused
            ? getRefusedResponse('Cache clear refused')
            : clearedCacheResponse
        );
      }

      const id = searchParams.get('id');
      sentActions.push(
        `${action}:${id}${searchParams.get('clearCache') === '0' ? ':noClearCache' : ''}`
      );
      await delay(updateDelay);

      if (id === brokenUpdateId) {
        return new HttpResponse('PHP Warning', {
          headers: { 'Content-Type': 'application/json' }
        });
      }
      if (id === failingUpdateId) {
        return HttpResponse.json({ message: 'Update failed' }, { status: 500 });
      }

      return HttpResponse.json(
        id === refusedUpdateId
          ? getRefusedResponse('Update refused')
          : getUpdatedResponse(id as string)
      );
    },
    'postExtensionAction'
  );

  const store = createStore();

  // A client of its own, so no cache leaks from one test to the next.
  const queryClient = new QueryClient({
    defaultOptions: { queries: { gcTime: 0, retry: false } }
  });

  const renderResult = render(
    <SnackbarProvider maxSnackbars={5}>
      <QueryClientProvider client={queryClient}>
        <Provider store={store}>
          <ExtensionsListing />
        </Provider>
      </QueryClientProvider>
    </SnackbarProvider>
  );

  return { ...renderResult, store };
};

export default initialize;
