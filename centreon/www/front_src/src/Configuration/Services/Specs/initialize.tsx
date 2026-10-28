import { SnackbarProvider } from '@centreon/ui';
import {
  configurationLimitPerPageAtom,
  intervalLengthAtom,
  type PlatformFeatures,
  platformFeaturesAtom,
  type UserPermissions,
  userPermissionsAtom
} from '@centreon/ui-context';

import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { interceptAPIRequest, interceptRequest, Method } from '@test-setup/api';
import { render } from '@test-setup/render';
import type { RenderResult } from '@testing-library/react';
import i18next from 'i18next';
import { createStore, Provider } from 'jotai';
import { HttpResponse } from 'msw';
import { initReactI18next } from 'react-i18next';
import { BrowserRouter as Router } from 'react-router';

import Services from '..';
import {
  getDuplicateServiceEndpoint,
  getServiceEndpoint,
  servicesListEndpoint,
  serviceTemplatesEndpoint
} from '../api/endpoints';
import {
  emptyListingResponse,
  getListingResponse,
  getServiceResponse,
  getServiceTemplatesResponse
} from './utils';

interface Props {
  isEmpty?: boolean;
  hasWriteAccess?: boolean;
  intervalLength?: number;
  limitPerPage?: number;
  isCloudPlatform?: boolean;
  // Opens the page on a deep link, such as `?mode=edit&id=0`.
  searchParams?: string;
}

const initialize = ({
  isEmpty = false,
  hasWriteAccess = true,
  intervalLength = 60,
  limitPerPage,
  isCloudPlatform = false,
  searchParams = ''
}: Props = {}): RenderResult => {
  i18next.use(initReactI18next).init({
    lng: 'en',
    resources: {}
  });

  // The form is URL-driven, and the page is not reloaded between tests.
  window.history.pushState(
    {},
    '',
    `${window.location.pathname}${searchParams}`
  );

  const store = createStore();

  // Only what the module reads.
  store.set(userPermissionsAtom, {
    configuration_service_write: hasWriteAccess
  } as UserPermissions);
  store.set(intervalLengthAtom, intervalLength);
  // Unset, the listing keeps the default used before the platform parameters
  // are loaded.
  if (limitPerPage) {
    store.set(configurationLimitPerPageAtom, limitPerPage);
  }
  store.set(platformFeaturesAtom, { isCloudPlatform } as PlatformFeatures);

  interceptAPIRequest({
    alias: 'getAllServices',
    method: Method.GET,
    path: `**${servicesListEndpoint}?**`,
    response: isEmpty ? emptyListingResponse : getListingResponse()
  });

  interceptAPIRequest({
    alias: 'getServiceTemplates',
    method: Method.GET,
    path: `**${serviceTemplatesEndpoint}?**`,
    response: getServiceTemplatesResponse()
  });

  // Any row opens the form, so each one has a detail response of its own.
  [0, 1, 2].forEach((id) => {
    interceptAPIRequest({
      alias: `getService${id}`,
      method: Method.GET,
      path: `**${getServiceEndpoint({ id })}`,
      response: getServiceResponse(id)
    });
  });

  // Enable and disable are a partial update of the service, one request each.
  [0, 1, 2].forEach((id) => {
    interceptRequest(
      Method.PATCH,
      `**${getServiceEndpoint({ id })}`,
      () => HttpResponse.json({}),
      `patchService${id}`
    );
  });

  // Every write names one service, so each row has its own intercept.
  [0, 2].forEach((id) => {
    interceptAPIRequest({
      alias: `deleteService${id}`,
      method: Method.DELETE,
      path: `**${getServiceEndpoint({ id })}`,
      response: {}
    });
  });

  interceptAPIRequest({
    alias: 'duplicateService0',
    method: Method.POST,
    path: `**${getDuplicateServiceEndpoint({ id: 0 })}`,
    response: {}
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
              <Services />
            </div>
          </Provider>
        </QueryClientProvider>
      </SnackbarProvider>
    </Router>
  );
};

export default initialize;
